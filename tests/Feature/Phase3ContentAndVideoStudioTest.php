<?php

namespace Tests\Feature;

use App\Enums\ContentPipelineStatus;
use App\Enums\RenderJobStatus;
use App\Enums\VideoTemplateType;
use App\Jobs\Video\PrepareVideoCompositionJob;
use App\Models\AiModelConfiguration;
use App\Models\AiProvider;
use App\Models\ContentApprovalLog;
use App\Models\ContentIdea;
use App\Models\GeneratedContent;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\VideoProject;
use App\Models\VideoRenderJob;
use App\Modules\VideoProduction\VideoProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase3ContentAndVideoStudioTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Owner Plant Coaction',
            'email' => 'owner@plantcoaction.test',
            'password' => bcrypt('Password123!'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->otherUser = User::create([
            'name' => 'Other Operator',
            'email' => 'other@plantcoaction.test',
            'password' => bcrypt('Password123!'),
            'role' => 'operator',
            'is_active' => true,
        ]);
    }

    private function seedConfiguredGeminiProvider(): void
    {
        $provider = AiProvider::updateOrCreate(
            ['slug' => 'google_gemini'],
            [
                'name' => 'Google Gemini Developer API',
                'driver' => 'google_gemini',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'api_key_encrypted' => Crypt::encryptString('AIzaSyFakeTestKeyForUnitTestsOnly12345'),
                'is_active' => true,
                'is_default' => true,
                'connection_status' => 'connected',
                'settings' => [
                    'default_model' => 'gemini-2.5-flash',
                    'timeout_seconds' => 30,
                ],
            ]
        );

        AiModelConfiguration::updateOrCreate(
            ['ai_provider_id' => $provider->id, 'model_id' => 'gemini-2.5-flash'],
            [
                'display_name' => 'Gemini 2.5 Flash',
                'is_default' => true,
                'is_enabled' => true,
            ]
        );
    }

    public function test_1_ai_content_research_generates_saves_and_deletes_ideas(): void
    {
        $this->seedConfiguredGeminiProvider();

        $ideasJson = json_encode([
            'ideas' => [
                [
                    'title' => '3 Tanda Akar Anthurium Mulai Kekurangan Oksigen',
                    'hook_concept' => 'Jangan tunggu daun menguning baru cek akar Anthurium kamu!',
                    'concept_summary' => 'Penjelasan praktis ciri media padat dan cara mengganti ke chunky mix.',
                    'visual_recommendation' => 'Close-up akar sehat vs akar cokelat + pot bening.',
                    'estimated_duration_seconds' => 30,
                    'cta_recommendation' => 'Simpan video ini untuk panduan repotting berikutnya.',
                    'needs_fact_verification' => false,
                ],
                [
                    'title' => 'Kenapa Daun Velvet Anthurium Tidak Boleh Sering Disemprot',
                    'hook_concept' => 'Masih sering misting daun velvet? Hati-hati bercak jamur!',
                    'concept_summary' => 'Bahas sirkulasi udara vs air menggenang di permukaan daun velvet.',
                    'visual_recommendation' => 'Macro tekstur daun velvet Anthurium Warocqueanum.',
                    'estimated_duration_seconds' => 45,
                    'cta_recommendation' => 'Tulis pengalamanmu di kolom komentar.',
                    'needs_fact_verification' => true,
                ],
            ],
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*:generateContent*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => $ideasJson]]],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->owner)
            ->get(route('content.research'))
            ->assertOk()
            ->assertSee('AI Content Research');

        $this->actingAs($this->owner)
            ->post(route('content.research.generate'), [
                'category' => 'Plant Education',
                'target_audience' => 'Kolektor Anthurium Indonesia',
                'content_goal' => 'Edukasi perawatan akar',
                'count' => 2,
                'preferred_provider' => 'google_gemini',
            ])
            ->assertRedirect(route('content.research'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('content_ideas', 2);
        $idea = ContentIdea::firstOrFail();
        $this->assertFalse($idea->saved_to_library);

        // Save to library
        $this->actingAs($this->owner)
            ->post(route('content.research.save', $idea))
            ->assertRedirect();
        $this->assertTrue($idea->fresh()->saved_to_library);

        // Cross-user isolation check (403)
        $this->actingAs($this->otherUser)
            ->post(route('content.research.save', $idea))
            ->assertForbidden();

        $this->actingAs($this->otherUser)
            ->delete(route('content.research.destroy', $idea))
            ->assertForbidden();

        // Owner delete works
        $this->actingAs($this->owner)
            ->delete(route('content.research.destroy', $idea))
            ->assertRedirect();
        $this->assertSoftDeleted('content_ideas', ['id' => $idea->id]);
    }

    public function test_2_ai_script_generation_manual_edit_and_single_section_regeneration_preserves_other_sections(): void
    {
        $this->seedConfiguredGeminiProvider();

        $scriptJson = json_encode([
            'title' => 'Cara Aman Repotting Anthurium Tanpa Stres',
            'hook' => 'Baru beli Anthurium tapi takut langsung repotting?',
            'opening' => 'Banyak tanaman stres bukan karena potnya, tapi karena waktunya belum tepat.',
            'main_narration' => 'Biarkan tanaman adaptasi minimal tujuh hari dulu di tempat teduh.',
            'supporting_explanation' => 'Saat media mulai mengering, pindahkan perlahan tanpa merobek akar sehat.',
            'closing' => 'Dengan transisi lembut, daun baru akan tumbuh lebih stabil.',
            'cta' => 'Simpan tips ini sebelum kamu repotting minggu ini.',
            'caption' => 'Panduan repotting Anthurium anti stres untuk pemula.',
            'hashtags' => ['#plantcoaction', '#anthurium', '#repotting'],
            'disclosure_text' => '',
            'suggested_visual_scenes' => [
                ['scene_order' => 1, 'title' => 'Hook', 'narration_segment' => 'Baru beli Anthurium?', 'overlay_text' => 'Jangan Langsung Repotting!', 'visual_recommendation' => 'Unboxing pot tanaman', 'highlight_keywords' => ['Anthurium']],
                ['scene_order' => 2, 'title' => 'Adaptasi', 'narration_segment' => 'Adaptasi 7 hari.', 'overlay_text' => 'Adaptasi 7 Hari Dulu', 'visual_recommendation' => 'Tanaman di rak teduh', 'highlight_keywords' => ['7 hari']],
                ['scene_order' => 3, 'title' => 'Penutup', 'narration_segment' => 'Simpan tips ini.', 'overlay_text' => 'Simpan Tips Ini', 'visual_recommendation' => 'Daun sehat', 'highlight_keywords' => ['Tips']],
            ],
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*:generateContent*' => Http::sequence()
                ->push(['candidates' => [['content' => ['parts' => [['text' => $scriptJson]]]]]], 200)
                ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode(['text' => 'HOOK BARU: Kesalahan paling sering setelah beli Anthurium baru!'])]]]]]], 200),
        ]);

        $this->actingAs($this->owner)
            ->post(route('content.scripts.generate'), [
                'title' => 'Cara Aman Repotting Anthurium',
                'category' => 'Plant Care Tips',
                'target_audience' => 'Pemula tanaman hias',
                'narration_style' => 'edukatif',
                'duration_seconds' => 30,
                'preferred_provider' => 'google_gemini',
            ])
            ->assertRedirect();

        $content = GeneratedContent::firstOrFail();
        $originalOpening = $content->opening;
        $originalMain = $content->main_narration;
        $originalClosing = $content->closing;
        $originalCta = $content->cta;

        // Regenerate ONLY 'hook' — other sections must remain identical
        $this->actingAs($this->owner)
            ->post(route('content.scripts.regenerate-section', $content), [
                'section' => 'hook',
                'instruction' => 'Buat lebih tajam',
                'preferred_provider' => 'google_gemini',
            ])
            ->assertRedirect();

        $content->refresh();
        $this->assertStringContainsString('HOOK BARU', $content->hook);
        $this->assertSame($originalOpening, $content->opening);
        $this->assertSame($originalMain, $content->main_narration);
        $this->assertSame($originalClosing, $content->closing);
        $this->assertSame($originalCta, $content->cta);

        // Manual edit draft
        $this->actingAs($this->owner)
            ->patch(route('content.scripts.update', $content), [
                'title' => 'Judul Naskah Revisi Manual',
                'hook' => $content->hook,
                'opening' => 'Opening hasil suntingan manual.',
                'main_narration' => $originalMain,
                'supporting_explanation' => $content->supporting_explanation,
                'closing' => $originalClosing,
                'cta' => $originalCta,
                'caption' => 'Caption manual Plant Coaction',
                'hashtags_text' => '#plantcoaction #aroid',
                'target_duration_seconds' => 45,
            ])
            ->assertRedirect();

        $content->refresh();
        $this->assertSame('Judul Naskah Revisi Manual', $content->title);
        $this->assertSame('Opening hasil suntingan manual.', $content->opening);
        $this->assertSame(45, $content->target_duration_seconds);
    }

    public function test_3_visual_asset_upload_validation_preview_and_owner_only_isolation(): void
    {
        Storage::fake('local');

        $content = GeneratedContent::create([
            'user_id' => $this->owner->id,
            'title' => 'Konten Tes Aset',
            'category' => 'Plant Showcase',
            'status' => ContentPipelineStatus::SCRIPT_DRAFT->value,
        ]);

        // Reject unsupported file extension / MIME
        $badFile = UploadedFile::fake()->create('script.php', 5, 'application/x-php');
        $this->actingAs($this->owner)
            ->post(route('content.assets.upload'), [
                'asset_file' => $badFile,
                'category_tag' => 'plant_photo',
            ])
            ->assertSessionHasErrors('asset_file');

        // Accept valid PNG image
        $png = UploadedFile::fake()->image('anthurium-leaf.png', 1080, 1920);
        $this->actingAs($this->owner)
            ->post(route('content.assets.upload'), [
                'asset_file' => $png,
                'category_tag' => 'plant_photo',
                'generated_content_id' => $content->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $asset = MediaAsset::firstOrFail();
        $this->assertSame('image', $asset->asset_type);
        $this->assertSame($content->id, $asset->generated_content_id);
        Storage::disk('local')->assertExists($asset->file_path);

        // Owner preview OK, index page OK, Other user 403
        $this->actingAs($this->owner)
            ->get(route('content.assets'))
            ->assertOk()
            ->assertSee('Visual Asset Manager')
            ->assertSee('anthurium-leaf.png');

        $this->actingAs($this->owner)
            ->get(route('content.assets.preview', $asset))
            ->assertOk();

        $this->actingAs($this->otherUser)
            ->get(route('content.assets.preview', $asset))
            ->assertForbidden();

        $this->actingAs($this->otherUser)
            ->delete(route('content.assets.destroy', $asset))
            ->assertForbidden();

        // Owner delete removes record & file
        $path = $asset->file_path;
        $this->actingAs($this->owner)
            ->delete(route('content.assets.destroy', $asset))
            ->assertRedirect();
        $this->assertSoftDeleted('media_assets', ['id' => $asset->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_4_voiceover_generation_via_gemini_tts_and_error_preserves_script(): void
    {
        $this->seedConfiguredGeminiProvider();

        $content = GeneratedContent::create([
            'user_id' => $this->owner->id,
            'title' => 'Tes Voiceover Gemini TTS',
            'category' => 'Plant Education',
            'hook' => 'Kenapa daun Anthurium bisa menguning?',
            'main_narration' => 'Cek kelembapan media dan aliran udara di sekitar pot.',
            'cta' => 'Simpan tips ini.',
            'script_text' => 'Kenapa daun Anthurium bisa menguning? Cek kelembapan media dan aliran udara di sekitar pot. Simpan tips ini.',
            'target_duration_seconds' => 15,
            'status' => ContentPipelineStatus::SCRIPT_DRAFT->value,
        ]);

        // 24kHz 16-bit mono PCM silence (1 second = 48000 bytes)
        $fakePcm = str_repeat("\x00\x00", 24000);

        Http::fake([
            'https://generativelanguage.googleapis.com/*:generateContent*' => Http::sequence()
                // 1st call fails (503) — script must remain untouched
                ->push(['error' => ['message' => 'Service temporarily overloaded']], 503)
                ->push(['error' => ['message' => 'Service temporarily overloaded']], 503)
                // 2nd call succeeds with valid PCM audio
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'inlineData' => [
                                    'mimeType' => 'audio/L16;codec=pcm;rate=24000',
                                    'data' => base64_encode($fakePcm),
                                ],
                            ]],
                        ],
                    ]],
                ], 200),
        ]);

        // Failure case preserves script
        $this->actingAs($this->owner)
            ->post(route('content.scripts.voiceover', $content), [
                'tts_provider' => 'google_gemini_tts',
                'voice_id' => 'Kore',
            ])
            ->assertSessionHasErrors('voiceover');

        $this->assertSame('Kenapa daun Anthurium bisa menguning?', $content->fresh()->hook);

        // Success case creates voiceover asset, updates project & subtitles
        $this->actingAs($this->owner)
            ->post(route('content.scripts.voiceover', $content), [
                'tts_provider' => 'google_gemini_tts',
                'voice_id' => 'Kore',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $content->refresh();
        $this->assertSame(ContentPipelineStatus::VOICEOVER_READY->value, $content->status);
        $project = $content->videoProject;
        $this->assertNotNull($project);
        $this->assertNotNull($project->voiceover_asset_id);
        $this->assertGreaterThan(0, $project->subtitles()->count());
        $this->assertTrue(Storage::disk('local')->exists($project->voiceoverAsset->file_path));

        // Cleanup generated test audio files
        Storage::disk('local')->delete($project->voiceoverAsset->file_path);
        $previewMp3 = data_get($project->voiceoverAsset->metadata, 'preview_mp3_path');
        if ($previewMp3) {
            Storage::disk('local')->delete($previewMp3);
        }
    }

    public function test_5_video_editor_save_render_queue_idempotency_retry_cancel_approval_and_isolation(): void
    {
        Queue::fake();

        $content = GeneratedContent::create([
            'user_id' => $this->owner->id,
            'title' => 'Panduan Media Porous Aroid',
            'category' => 'Plant Education',
            'hook' => 'Rahasia media tanam porous ala Plant Coaction.',
            'main_narration' => 'Campurkan sekam bakar, perlite, dan pakis cacah secara seimbang.',
            'closing' => 'Akar lebih lega dan tidak mudah busuk.',
            'cta' => 'Follow @plant.coaction untuk tips lainnya.',
            'caption' => 'Media porous untuk aroid sehat.',
            'hashtags' => ['#plantcoaction', '#aroid'],
            'target_duration_seconds' => 15,
            'status' => ContentPipelineStatus::VOICEOVER_READY->value,
        ]);

        /** @var VideoProject $project */
        $project = app(VideoProjectService::class)->getOrCreateForContent($content, $this->owner);

        // Editor view renders
        $this->actingAs($this->owner)
            ->get(route('content.editor.show', $project))
            ->assertOk()
            ->assertSee('Video Preview & Remotion Editor')
            ->assertSee('PlantEducation');

        // Cross-user cannot access editor
        $this->actingAs($this->otherUser)
            ->get(route('content.editor.show', $project))
            ->assertForbidden();

        // Save draft edits (template switch + scene edit + manual subtitle edit)
        $firstScene = $project->scenes()->firstOrFail();
        $firstSub = $project->subtitles()->firstOrFail();

        $this->actingAs($this->owner)
            ->post(route('content.editor.save', $project), [
                'title' => 'Panduan Media Porous Updated',
                'template_slug' => VideoTemplateType::PROBLEM_SOLUTION->value,
                'cta_text' => 'Simpan & bagikan tips ini.',
                'subtitle_enabled' => true,
                'subtitle_position' => 'bottom',
                'subtitle_font_size' => 46,
                'subtitle_highlight_color' => '#34D399',
                'voiceover_volume' => 1.0,
                'bg_music_volume' => 0.12,
                'scenes' => [
                    [
                        'id' => $firstScene->id,
                        'scene_order' => 1,
                        'title' => 'Hook Revisi',
                        'overlay_text' => 'Akar Aroid Busuk?',
                        'narration_segment' => 'Rahasia media tanam porous.',
                        'duration_seconds' => 5.0,
                        'motion_effect' => 'zoom_in',
                        'transition_type' => 'fade',
                    ],
                ],
                'subtitles' => [
                    [
                        'id' => $firstSub->id,
                        'start_seconds' => 0.0,
                        'end_seconds' => 3.5,
                        'text' => 'Subtitle hasil edit manual.',
                    ],
                ],
            ])
            ->assertRedirect(route('content.editor.show', $project));

        $project->refresh();
        $this->assertSame('problem_solution', $project->template_slug);
        $this->assertSame('ProblemSolution', $project->composition_id);
        $this->assertSame('manual', $firstSub->fresh()->timing_source);
        $this->assertSame('Subtitle hasil edit manual.', $firstSub->fresh()->text);

        // Dispatch render job -> pushes PrepareVideoCompositionJob
        $this->actingAs($this->owner)
            ->post(route('content.editor.render', $project))
            ->assertRedirect(route('content.editor.show', $project));

        Queue::assertPushed(PrepareVideoCompositionJob::class, 1);
        $this->assertDatabaseCount('video_render_jobs', 1);
        $renderJob = VideoRenderJob::firstOrFail();

        // Second render click while active is idempotent (no duplicate job created)
        $this->actingAs($this->owner)
            ->post(route('content.editor.render', $project))
            ->assertRedirect();
        $this->assertDatabaseCount('video_render_jobs', 1);

        // Polling JSON endpoint returns status
        $this->actingAs($this->owner)
            ->getJson(route('content.editor.render-status', $project))
            ->assertOk()
            ->assertJsonPath('job.id', $renderJob->id)
            ->assertJsonPath('job.status', RenderJobStatus::QUEUED->value);

        // Cancel render
        $this->actingAs($this->owner)
            ->post(route('content.editor.render.cancel', [$project, $renderJob]))
            ->assertRedirect();
        $this->assertSame(RenderJobStatus::CANCELLED->value, $renderJob->fresh()->status);

        // Retry reuses SAME VideoRenderJob record & increments attempts
        $this->actingAs($this->owner)
            ->post(route('content.editor.render.retry', [$project, $renderJob]))
            ->assertRedirect();
        $this->assertDatabaseCount('video_render_jobs', 1);
        $this->assertSame(2, $renderJob->fresh()->attempts);
        $this->assertSame(RenderJobStatus::QUEUED->value, $renderJob->fresh()->status);

        // Approval workflow (reject -> approve) + ContentApprovalLog + cross-user 403
        $this->actingAs($this->otherUser)
            ->post(route('content.approval', $content), ['decision' => 'approve'])
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post(route('content.approval', $content), [
                'decision' => 'reject',
                'notes' => 'Perbesar teks subtitle sedikit.',
            ])
            ->assertRedirect();
        $this->assertSame(ContentPipelineStatus::REJECTED->value, $content->fresh()->status);

        $this->actingAs($this->owner)
            ->post(route('content.approval', $content), [
                'decision' => 'approve',
                'notes' => 'Siap diupload manual ke @plant.coaction.',
            ])
            ->assertRedirect();
        $this->assertSame(ContentPipelineStatus::APPROVED->value, $content->fresh()->status);
        $this->assertSame(2, ContentApprovalLog::where('generated_content_id', $content->id)->count());

        // Library & inspect page renders cleanly
        $this->actingAs($this->owner)
            ->get(route('content.library', ['inspect' => $content->id]))
            ->assertOk()
            ->assertSee('Panduan Media Porous Aroid');
    }
}
