<?php

namespace App\Jobs;

use App\Contracts\TextToSpeechInterface;
use App\Enums\WorkflowStatus;
use App\Models\MediaAsset;
use App\Models\VideoProject;
use App\Modules\ActivityLogs\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SynthesizeVoiceoverJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $videoProjectId,
        public readonly string $narrationText,
    ) {}

    public function handle(TextToSpeechInterface $tts, ActivityLogger $logger): void
    {
        $project = VideoProject::find($this->videoProjectId);
        if (! $project) {
            return;
        }

        if (! $tts->isConfigured()) {
            $project->update(['status' => WorkflowStatus::WAITING_CREDENTIALS->value]);
            $logger->log(
                'tts',
                'synthesize_skipped',
                'Sintesis voiceover ditunda karena kredensial Google Cloud TTS belum dikonfigurasi.',
                'warning',
                ['video_project_id' => $project->id],
                VideoProject::class,
                $project->id,
                $project->user_id,
            );

            return;
        }

        $project->update(['status' => WorkflowStatus::PROCESSING->value]);
        $result = $tts->synthesize($this->narrationText, ['storage_subdir' => 'tts/voiceovers']);

        if (! ($result['ok'] ?? false)) {
            $project->update(['status' => WorkflowStatus::FAILED->value]);
            throw new \RuntimeException($result['message'] ?? 'Sintesis audio TTS gagal.');
        }

        if (! empty($result['master_wav_path']) && Storage::disk('local')->exists((string) $result['master_wav_path'])) {
            $relativePath = (string) $result['master_wav_path'];
            $mimeType = 'audio/wav';
            $fileSize = (int) Storage::disk('local')->size($relativePath);
        } elseif (! empty($result['audio_base64'])) {
            $relativePath = 'tts/project-' . $project->id . '-' . now()->format('YmdHis') . '.mp3';
            $binary = base64_decode((string) $result['audio_base64']);
            Storage::disk('local')->put($relativePath, $binary);
            $mimeType = $result['mime_type'] ?? 'audio/mpeg';
            $fileSize = strlen($binary);
        } else {
            $project->update(['status' => WorkflowStatus::FAILED->value]);
            throw new \RuntimeException('Provider TTS tidak mengembalikan file maupun data audio.');
        }

        $asset = MediaAsset::create([
            'user_id' => $project->user_id,
            'generated_content_id' => $project->generated_content_id,
            'asset_type' => 'voiceover',
            'source_type' => 'tts_generated',
            'license_status' => 'cleared',
            'disk' => 'local',
            'file_path' => $relativePath,
            'mime_type' => $mimeType,
            'file_size_bytes' => $fileSize,
            'duration_seconds' => data_get($result, 'metadata.duration_seconds'),
            'metadata' => [
                'provider' => $tts->getSlug(),
                'model_id' => $result['model_id'] ?? null,
                'voice_id' => $result['voice_id'] ?? null,
                'preview_mp3_path' => $result['file_path'] ?? null,
            ],
        ]);

        $project->update([
            'voiceover_asset_id' => $asset->id,
            'status' => WorkflowStatus::DRAFT->value,
        ]);

        $logger->log(
            'tts',
            'synthesize_completed',
            "Audio voiceover untuk VideoProject #{$project->id} berhasil dibuat.",
            'info',
            ['asset_id' => $asset->id],
            VideoProject::class,
            $project->id,
            $project->user_id,
        );
    }

    public function failed(Throwable $exception): void
    {
        $project = VideoProject::find($this->videoProjectId);
        if ($project) {
            $project->update(['status' => WorkflowStatus::FAILED->value]);
        }

        app(ActivityLogger::class)->log(
            'tts',
            'synthesize_failed',
            'Job sintesis TTS gagal: ' . $exception->getMessage(),
            'error',
            ['video_project_id' => $this->videoProjectId],
            VideoProject::class,
            $this->videoProjectId,
        );
    }
}
