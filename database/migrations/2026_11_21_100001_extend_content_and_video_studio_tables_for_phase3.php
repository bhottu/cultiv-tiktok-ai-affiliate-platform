<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->string('category', 80)->default('Plant Education')->after('title')->index();
            $table->string('target_audience', 180)->nullable()->after('category');
            $table->string('content_goal', 180)->nullable()->after('target_audience');
            $table->text('concept_summary')->nullable()->after('hook_concept');
            $table->text('visual_recommendation')->nullable()->after('concept_summary');
            $table->string('cta_recommendation', 255)->nullable()->after('visual_recommendation');
            $table->unsignedSmallInteger('estimated_duration_seconds')->default(30)->after('cta_recommendation');
            $table->boolean('needs_fact_verification')->default(false)->after('estimated_duration_seconds');
            $table->boolean('saved_to_library')->default(false)->after('needs_fact_verification')->index();
        });

        Schema::table('generated_contents', function (Blueprint $table) {
            $table->string('category', 80)->default('Plant Education')->after('title')->index();
            $table->string('target_audience', 180)->nullable()->after('category');
            $table->string('narration_style', 60)->default('edukatif')->after('target_audience');
            $table->unsignedSmallInteger('target_duration_seconds')->default(30)->after('narration_style');
            $table->text('opening')->nullable()->after('hook');
            $table->longText('main_narration')->nullable()->after('opening');
            $table->longText('supporting_explanation')->nullable()->after('main_narration');
            $table->text('closing')->nullable()->after('supporting_explanation');
            $table->jsonb('suggested_visual_scenes')->nullable()->after('disclosure_text');
            $table->text('approval_notes')->nullable()->after('approved_at');
            $table->unsignedSmallInteger('revision_count')->default(0)->after('approval_notes');
        });

        Schema::table('media_assets', function (Blueprint $table) {
            $table->string('original_name', 255)->nullable()->after('file_path');
            $table->unsignedInteger('display_order')->default(0)->after('duration_seconds');
        });

        Schema::table('video_projects', function (Blueprint $table) {
            $table->string('template_slug', 64)->default('plant_education')->after('composition_id')->index();
            $table->foreignId('bg_music_asset_id')->nullable()->after('voiceover_asset_id')->constrained('media_assets')->nullOnDelete();
            $table->decimal('bg_music_volume', 3, 2)->default(0.15)->after('bg_music_asset_id');
            $table->decimal('voiceover_volume', 3, 2)->default(1.00)->after('bg_music_volume');
            $table->boolean('subtitle_enabled')->default(true)->after('voiceover_volume');
            $table->jsonb('subtitle_style')->nullable()->after('subtitle_enabled');
            $table->text('cta_text')->nullable()->after('subtitle_style');
            $table->string('thumbnail_path', 255)->nullable()->after('output_video_path');
            $table->string('approval_status', 40)->default('draft')->after('qc_status')->index();
            $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
        });

        Schema::table('video_render_jobs', function (Blueprint $table) {
            $table->string('stage', 64)->default('queued')->after('status');
            $table->string('output_file_path', 255)->nullable()->after('error_message');
            $table->unsignedBigInteger('file_size_bytes')->nullable()->after('output_file_path');
            $table->timestampTz('cancelled_at')->nullable()->after('completed_at');
        });

        Schema::create('video_scenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_project_id')->constrained('video_projects')->cascadeOnDelete();
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedSmallInteger('scene_order')->default(1);
            $table->string('title', 180)->nullable();
            $table->text('overlay_text')->nullable();
            $table->text('narration_segment')->nullable();
            $table->text('visual_recommendation')->nullable();
            $table->decimal('duration_seconds', 6, 2)->default(5.00);
            $table->string('transition_type', 40)->default('fade');
            $table->string('motion_effect', 40)->default('zoom_in');
            $table->jsonb('highlight_keywords')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['video_project_id', 'scene_order']);
        });

        Schema::create('subtitle_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_project_id')->constrained('video_projects')->cascadeOnDelete();
            $table->unsignedSmallInteger('segment_order')->default(1);
            $table->decimal('start_seconds', 7, 2)->default(0.00);
            $table->decimal('end_seconds', 7, 2)->default(2.00);
            $table->text('text');
            $table->jsonb('highlight_words')->nullable();
            $table->string('timing_source', 32)->default('estimated');
            $table->timestampsTz();

            $table->index(['video_project_id', 'segment_order']);
        });

        Schema::create('content_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('generated_content_id')->nullable()->constrained('generated_contents')->cascadeOnDelete();
            $table->foreignId('video_project_id')->nullable()->constrained('video_projects')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('decision', 40);
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_approval_logs');
        Schema::dropIfExists('subtitle_segments');
        Schema::dropIfExists('video_scenes');

        Schema::table('video_render_jobs', function (Blueprint $table) {
            $table->dropColumn(['stage', 'output_file_path', 'file_size_bytes', 'cancelled_at']);
        });

        Schema::table('video_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('bg_music_asset_id');
            $table->dropColumn([
                'template_slug', 'bg_music_volume', 'voiceover_volume', 'subtitle_enabled',
                'subtitle_style', 'cta_text', 'thumbnail_path', 'approval_status',
                'approved_at', 'approval_notes',
            ]);
        });

        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropColumn(['original_name', 'display_order']);
        });

        Schema::table('generated_contents', function (Blueprint $table) {
            $table->dropColumn([
                'category', 'target_audience', 'narration_style', 'target_duration_seconds',
                'opening', 'main_narration', 'supporting_explanation', 'closing',
                'suggested_visual_scenes', 'approval_notes', 'revision_count',
            ]);
        });

        Schema::table('content_ideas', function (Blueprint $table) {
            $table->dropColumn([
                'category', 'target_audience', 'content_goal', 'concept_summary',
                'visual_recommendation', 'cta_recommendation', 'estimated_duration_seconds',
                'needs_fact_verification', 'saved_to_library',
            ]);
        });
    }
};
