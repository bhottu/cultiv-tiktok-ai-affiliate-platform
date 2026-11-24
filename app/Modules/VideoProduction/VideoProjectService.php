<?php

namespace App\Modules\VideoProduction;

use App\Enums\ContentFormatType;
use App\Enums\ContentPipelineStatus;
use App\Enums\VideoTemplateType;
use App\Models\ContentApprovalLog;
use App\Models\GeneratedContent;
use App\Models\MediaAsset;
use App\Models\ProductCategory;
use App\Models\SubtitleSegment;
use App\Models\User;
use App\Models\VideoProject;
use App\Models\VideoScene;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\TextToSpeech\TTSProviderManager;
use Illuminate\Support\Facades\Storage;

class VideoProjectService
{
    public function __construct(
        private readonly SubtitleGeneratorService $subtitleService,
        private readonly TTSProviderManager $ttsManager,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Get or initialize a VideoProject with scenes and phrase subtitles for a GeneratedContent.
     */
    public function getOrCreateForContent(GeneratedContent $content, User $owner): VideoProject
    {
        $existing = $content->videoProject()->with(['scenes.mediaAsset', 'subtitles', 'voiceoverAsset', 'bgMusicAsset', 'latestRenderJob'])->first();
        if ($existing) {
            return $existing;
        }

        $template = $this->defaultTemplateForContent($content);
        $duration = max(10.0, (float) ($content->target_duration_seconds ?: 30));
        $preset = $content->productCategory?->theme_preset
            ?: ProductCategory::resolvePresetFromName($content->category);
        $themeTokens = ProductCategory::themeTokensForPreset($preset, $content->productCategory?->accent_color);

        $project = VideoProject::create([
            'user_id' => $content->user_id ?? $owner->id,
            'generated_content_id' => $content->id,
            'title' => $content->title,
            'composition_id' => $template->compositionId(),
            'template_slug' => $template->value,
            'product_category_id' => $content->product_category_id,
            'visual_theme_preset' => $preset,
            'aspect_ratio' => '9:16',
            'width' => 1080,
            'height' => 1920,
            'fps' => 30,
            'duration_seconds' => $duration,
            'status' => 'draft',
            'qc_status' => 'pending',
            'approval_status' => 'draft',
            'subtitle_enabled' => true,
            'subtitle_style' => [
                'position' => 'bottom',
                'fontSize' => 44,
                'highlightColor' => $themeTokens['accentColor'],
            ],
            'cta_text' => $content->cta ?: 'Simpan video ini dan diskusi di komentar.',
        ]);

        $this->seedInitialScenes($project, $content, $duration);
        $this->subtitleService->generateForProject($project, $content->buildFullNarration(), $duration);

        return $project->load(['scenes.mediaAsset', 'subtitles', 'voiceoverAsset', 'bgMusicAsset', 'latestRenderJob']);
    }

    /**
     * Generate Indonesian voiceover via active TTSProviderManager (Gemini TTS by default) without losing script/assets on failure.
     *
     * @param  array<string, mixed>  $ttsOptions
     * @return array{ok: bool, asset?: MediaAsset, project?: VideoProject, message: string}
     */
    public function generateVoiceover(
        VideoProject $project,
        User $actor,
        ?string $providerSlug = null,
        array $ttsOptions = [],
    ): array {
        $content = $project->content;
        $narration = $content ? $content->buildFullNarration() : trim($project->title);
        if (trim($narration) === '') {
            return ['ok' => false, 'message' => 'Naskah narasi kosong. Lengkapi naskah terlebih dahulu sebelum membuat voiceover.'];
        }

        $driver = $this->ttsManager->driver($providerSlug);
        if (! $driver->isConfigured()) {
            return ['ok' => false, 'message' => "Provider TTS {$driver->getName()} belum dikonfigurasi. Periksa halaman Pengaturan AI/TTS."];
        }

        $res = $driver->synthesize($narration, array_merge($ttsOptions, ['storage_subdir' => 'tts/voiceovers']));
        if (! ($res['ok'] ?? false)) {
            $this->logger->log(
                'tts',
                'voiceover_generation_failed',
                'Voiceover gagal dibuat: ' . ($res['message'] ?? 'Unknown error'),
                'warning',
                ['video_project_id' => $project->id, 'provider' => $driver->getSlug()],
                VideoProject::class,
                $project->id,
                $actor->id,
            );

            return ['ok' => false, 'message' => (string) ($res['message'] ?? 'Gagal menghasilkan voiceover.')];
        }

        $disk = Storage::disk('local');
        if (! empty($res['master_wav_path']) && $disk->exists((string) $res['master_wav_path'])) {
            $relPath = (string) $res['master_wav_path'];
            $mime = 'audio/wav';
            $size = (int) $disk->size($relPath);
        } elseif (! empty($res['audio_base64'])) {
            $relPath = 'tts/voiceovers/project-' . $project->id . '-' . now()->format('YmdHis') . '.mp3';
            $bytes = base64_decode((string) $res['audio_base64']);
            $disk->put($relPath, $bytes);
            $mime = (string) ($res['mime_type'] ?? 'audio/mpeg');
            $size = strlen($bytes);
        } else {
            return ['ok' => false, 'message' => 'Provider TTS tidak mengembalikan file audio yang valid.'];
        }

        $durationSec = (float) data_get($res, 'metadata.duration_seconds', 0);
        if ($durationSec <= 0.5) {
            $durationSec = max(5.0, (float) ($project->duration_seconds ?: 15));
        }

        $asset = MediaAsset::create([
            'user_id' => $actor->id,
            'generated_content_id' => $project->generated_content_id,
            'asset_type' => 'voiceover',
            'source_type' => 'tts_generated',
            'license_status' => 'cleared',
            'disk' => 'local',
            'file_path' => $relPath,
            'original_name' => 'voiceover-project-' . $project->id . ($mime === 'audio/wav' ? '.wav' : '.mp3'),
            'mime_type' => $mime,
            'file_size_bytes' => $size,
            'duration_seconds' => round($durationSec, 2),
            'metadata' => [
                'provider' => $driver->getSlug(),
                'model_id' => $res['model_id'] ?? null,
                'voice_id' => $res['voice_id'] ?? null,
                'preview_mp3_path' => $res['file_path'] ?? null,
                'sample_rate' => data_get($res, 'metadata.sample_rate', 24000),
            ],
        ]);

        $project->update([
            'voiceover_asset_id' => $asset->id,
            'duration_seconds' => round($durationSec, 2),
        ]);

        // Re-distribute scene durations and subtitle timing to match actual voiceover length
        $this->rebalanceScenesToDuration($project, $durationSec);
        $this->subtitleService->generateForProject($project, $narration, $durationSec);

        if ($content) {
            $content->update(['status' => ContentPipelineStatus::VOICEOVER_READY->value]);
        }

        $this->logger->log(
            'tts',
            'voiceover_generated',
            "Voiceover Bahasa Indonesia ({$durationSec}s) berhasil dibuat untuk VideoProject #{$project->id} via {$driver->getName()}.",
            'info',
            ['video_project_id' => $project->id, 'media_asset_id' => $asset->id, 'duration' => $durationSec],
            VideoProject::class,
            $project->id,
            $actor->id,
        );

        return [
            'ok' => true,
            'asset' => $asset,
            'project' => $project->fresh(['scenes.mediaAsset', 'subtitles', 'voiceoverAsset']),
            'message' => "Voiceover Bahasa Indonesia berhasil dibuat ({$durationSec} detik) via {$driver->getName()}.",
        ];
    }

    /**
     * Save manual editor edits (template, title, CTA, scenes, subtitles, audio volumes) as draft.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveEditorDraft(VideoProject $project, array $data): VideoProject
    {
        $template = VideoTemplateType::tryFrom((string) ($data['template_slug'] ?? $project->template_slug))
            ?? VideoTemplateType::PRODUCT_REVIEW;

        $project->update([
            'title' => trim((string) ($data['title'] ?? $project->title)),
            'template_slug' => $template->value,
            'composition_id' => $template->compositionId(),
            'visual_theme_preset' => ! empty($data['visual_theme_preset']) ? (string) $data['visual_theme_preset'] : $project->visual_theme_preset,
            'cta_text' => trim((string) ($data['cta_text'] ?? $project->cta_text)),
            'subtitle_enabled' => (bool) ($data['subtitle_enabled'] ?? $project->subtitle_enabled),
            'subtitle_style' => [
                'position' => in_array($data['subtitle_position'] ?? 'bottom', ['top', 'center', 'bottom'], true)
                    ? $data['subtitle_position'] : 'bottom',
                'fontSize' => max(28, min(64, (int) ($data['subtitle_font_size'] ?? 44))),
                'highlightColor' => (string) ($data['subtitle_highlight_color'] ?? '#6EE7B7'),
            ],
            'voiceover_volume' => max(0.1, min(1.5, (float) ($data['voiceover_volume'] ?? $project->voiceover_volume ?? 1.0))),
            'bg_music_asset_id' => array_key_exists('bg_music_asset_id', $data) ? ($data['bg_music_asset_id'] ?: null) : $project->bg_music_asset_id,
            'bg_music_volume' => max(0.0, min(0.5, (float) ($data['bg_music_volume'] ?? $project->bg_music_volume ?? 0.15))),
        ]);

        if (! empty($data['scenes']) && is_array($data['scenes'])) {
            $sumDuration = 0.0;
            foreach (array_values($data['scenes']) as $idx => $s) {
                $sceneId = isset($s['id']) ? (int) $s['id'] : null;
                $scene = $sceneId ? $project->scenes()->find($sceneId) : null;
                $dur = max(1.0, min(45.0, (float) ($s['duration_seconds'] ?? 5.0)));
                $sumDuration += $dur;

                $payload = [
                    'scene_order' => (int) ($s['scene_order'] ?? ($idx + 1)),
                    'title' => mb_substr(trim((string) ($s['title'] ?? 'Scene ' . ($idx + 1))), 0, 180),
                    'overlay_text' => trim((string) ($s['overlay_text'] ?? '')),
                    'narration_segment' => trim((string) ($s['narration_segment'] ?? '')),
                    'media_asset_id' => ! empty($s['media_asset_id']) ? (int) $s['media_asset_id'] : null,
                    'duration_seconds' => $dur,
                    'motion_effect' => in_array($s['motion_effect'] ?? 'zoom_in', ['zoom_in', 'zoom_out', 'pan_left', 'pan_right', 'static'], true)
                        ? $s['motion_effect'] : 'zoom_in',
                    'transition_type' => in_array($s['transition_type'] ?? 'fade', ['fade', 'slide', 'zoom', 'cut'], true)
                        ? $s['transition_type'] : 'fade',
                ];

                if ($scene) {
                    $scene->update($payload);
                } else {
                    $project->scenes()->create($payload);
                }
            }
            if ($sumDuration > 0) {
                $project->update(['duration_seconds' => round($sumDuration, 2)]);
            }
        }

        if (! empty($data['subtitles']) && is_array($data['subtitles'])) {
            foreach (array_values($data['subtitles']) as $idx => $sub) {
                $subId = isset($sub['id']) ? (int) $sub['id'] : null;
                $seg = $subId ? $project->subtitles()->find($subId) : null;
                $start = max(0.0, (float) ($sub['start_seconds'] ?? 0));
                $end = max($start + 0.3, (float) ($sub['end_seconds'] ?? ($start + 2.0)));
                $text = trim((string) ($sub['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $subPayload = [
                    'segment_order' => $idx + 1,
                    'start_seconds' => round($start, 2),
                    'end_seconds' => round($end, 2),
                    'text' => $text,
                    'highlight_words' => $this->subtitleService->pickHighlightWords($text),
                    'timing_source' => 'manual',
                ];
                if ($seg) {
                    $seg->update($subPayload);
                } else {
                    $project->subtitles()->create($subPayload);
                }
            }
            $srt = $this->subtitleService->exportSrt($project);
            $project->update(['subtitle_path' => $srt]);
        }

        if ($project->content && in_array($project->content->status, [ContentPipelineStatus::SCRIPT_DRAFT->value, ContentPipelineStatus::VOICEOVER_READY->value], true)) {
            $project->content->update(['status' => ContentPipelineStatus::READY_TO_RENDER->value]);
        }

        return $project->fresh(['scenes.mediaAsset', 'subtitles', 'voiceoverAsset', 'bgMusicAsset']);
    }

    /**
     * Record approval / rejection / archive decision for a content & video project.
     */
    public function decideApproval(
        GeneratedContent $content,
        User $actor,
        string $decision,
        ?string $notes = null,
    ): GeneratedContent {
        $from = $content->status;
        $to = match ($decision) {
            'approve' => ContentPipelineStatus::APPROVED->value,
            'reject' => ContentPipelineStatus::REJECTED->value,
            'submit' => ContentPipelineStatus::PENDING_APPROVAL->value,
            'archive' => ContentPipelineStatus::ARCHIVED->value,
            default => throw new \InvalidArgumentException("Keputusan approval '{$decision}' tidak valid."),
        };

        $content->update([
            'status' => $to,
            'approved_by' => $actor->id,
            'approved_at' => $decision === 'approve' ? now() : null,
            'approval_notes' => $notes ? trim($notes) : $content->approval_notes,
        ]);

        $project = $content->videoProject;
        if ($project) {
            $project->update([
                'approval_status' => $to,
                'approved_by' => $actor->id,
                'approved_at' => $decision === 'approve' ? now() : null,
                'approval_notes' => $notes ? trim($notes) : $project->approval_notes,
            ]);
        }

        ContentApprovalLog::create([
            'user_id' => $actor->id,
            'generated_content_id' => $content->id,
            'video_project_id' => $project?->id,
            'from_status' => $from,
            'to_status' => $to,
            'decision' => $decision,
            'notes' => $notes ? trim($notes) : null,
            'created_at' => now(),
        ]);

        $this->logger->log(
            'content_approval',
            'decision_' . $decision,
            "Konten '{$content->title}' diperbarui dari '{$from}' menjadi '{$to}'.",
            'info',
            ['generated_content_id' => $content->id, 'decision' => $decision],
            GeneratedContent::class,
            $content->id,
            $actor->id,
        );

        return $content->fresh(['videoProject', 'approvalLogs']);
    }

    private function seedInitialScenes(VideoProject $project, GeneratedContent $content, float $totalDuration): void
    {
        $suggested = is_array($content->suggested_visual_scenes) ? $content->suggested_visual_scenes : [];
        if (empty($suggested)) {
            $suggested = [
                ['scene_order' => 1, 'title' => 'Hook', 'overlay_text' => $content->hook ?: $content->title, 'narration_segment' => $content->hook ?: $content->title, 'visual_recommendation' => 'Close-up utama produk atau konteks masalah'],
                ['scene_order' => 2, 'title' => 'Penjelasan Utama', 'overlay_text' => mb_substr((string) ($content->main_narration ?: $content->title), 0, 90), 'narration_segment' => (string) $content->main_narration, 'visual_recommendation' => 'Detail fitur, bahan, atau cara penggunaan'],
                ['scene_order' => 3, 'title' => 'Ringkasan & CTA', 'overlay_text' => $content->closing ?: $content->cta ?: 'Ringkasan & rekomendasi', 'narration_segment' => trim($content->closing . ' ' . $content->cta), 'visual_recommendation' => 'Tampilan produk utuh dan CTA'],
            ];
        }

        $attachedAssets = $content->mediaAssets()->whereIn('asset_type', ['image', 'video'])->get()->values();
        $count = max(1, count($suggested));
        $perScene = round($totalDuration / $count, 2);
        $effects = ['zoom_in', 'pan_right', 'zoom_out', 'pan_left'];

        foreach (array_values($suggested) as $idx => $s) {
            $asset = $attachedAssets->get($idx);
            VideoScene::create([
                'video_project_id' => $project->id,
                'media_asset_id' => $asset?->id,
                'scene_order' => $idx + 1,
                'title' => mb_substr(trim((string) ($s['title'] ?? 'Scene ' . ($idx + 1))), 0, 180),
                'overlay_text' => trim((string) ($s['overlay_text'] ?? $s['narration_segment'] ?? $content->title)),
                'narration_segment' => trim((string) ($s['narration_segment'] ?? '')),
                'visual_recommendation' => trim((string) ($s['visual_recommendation'] ?? '')),
                'duration_seconds' => $perScene,
                'transition_type' => $idx === 0 ? 'fade' : 'slide',
                'motion_effect' => $effects[$idx % count($effects)],
                'highlight_keywords' => is_array($s['highlight_keywords'] ?? null) ? $s['highlight_keywords'] : [],
            ]);
        }
    }

    private function rebalanceScenesToDuration(VideoProject $project, float $targetDuration): void
    {
        $scenes = $project->scenes()->orderBy('scene_order')->get();
        if ($scenes->isEmpty()) {
            return;
        }
        $per = round(max(1.5, $targetDuration / $scenes->count()), 2);
        $accum = 0.0;
        foreach ($scenes as $idx => $scene) {
            $isLast = $idx === $scenes->count() - 1;
            $dur = $isLast ? round(max(1.5, $targetDuration - $accum), 2) : $per;
            $scene->update(['duration_seconds' => $dur]);
            $accum += $dur;
        }
    }

    private function defaultTemplateForContent(GeneratedContent $content): VideoTemplateType
    {
        if (! empty($content->content_format)) {
            $fmt = ContentFormatType::tryFrom((string) $content->content_format);
            if ($fmt) {
                return $fmt->recommendedTemplate();
            }
        }

        return match ($content->category) {
            'Plant Education', 'Plant Care Tips', 'Rare Plant Facts' => VideoTemplateType::PLANT_EDUCATION,
            'Plant Showcase' => VideoTemplateType::PLANT_SHOWCASE,
            'Product Review', 'Affiliate Product Promotion' => VideoTemplateType::PRODUCT_REVIEW,
            'Problem & Solution' => VideoTemplateType::PROBLEM_SOLUTION,
            'Storytelling' => VideoTemplateType::STORYTELLING,
            default => VideoTemplateType::PRODUCT_SHOWCASE,
        };
    }
}
