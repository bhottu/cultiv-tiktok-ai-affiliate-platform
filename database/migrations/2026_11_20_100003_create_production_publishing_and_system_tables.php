<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('provider_slug', 64)->index();
            $table->string('name', 140);
            $table->string('language_code', 20)->default('id-ID')->index();
            $table->string('voice_id', 120)->nullable();
            $table->string('gender', 20)->nullable();
            $table->decimal('speaking_rate', 4, 2)->default(1.00);
            $table->decimal('pitch', 5, 2)->default(0.00);
            $table->decimal('volume_gain_db', 5, 2)->default(0.00);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('credentials_encrypted')->nullable();
            $table->string('connection_status', 40)->default('not_configured')->index();
            $table->timestampTz('last_tested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('affiliate_product_id')->nullable()->constrained('affiliate_products')->nullOnDelete();
            $table->foreignId('generated_content_id')->nullable()->constrained('generated_contents')->nullOnDelete();
            $table->string('asset_type', 40)->index();
            $table->string('source_type', 40)->default('original')->index();
            $table->string('license_status', 40)->default('unverified')->index();
            $table->string('disk', 40)->default('local');
            $table->string('file_path', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration_seconds', 8, 2)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('video_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('generated_content_id')->nullable()->constrained('generated_contents')->nullOnDelete();
            $table->foreignId('voice_profile_id')->nullable()->constrained('voice_profiles')->nullOnDelete();
            $table->foreignId('voiceover_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('title', 220);
            $table->string('composition_id', 100)->default('PlantAffiliateVertical');
            $table->string('aspect_ratio', 16)->default('9:16');
            $table->unsignedSmallInteger('width')->default(1080);
            $table->unsignedSmallInteger('height')->default(1920);
            $table->unsignedTinyInteger('fps')->default(30);
            $table->decimal('duration_seconds', 6, 2)->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->string('qc_status', 40)->default('pending')->index();
            $table->string('subtitle_path', 255)->nullable();
            $table->string('output_video_path', 255)->nullable();
            $table->jsonb('composition_props')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('video_render_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_project_id')->constrained('video_projects')->cascadeOnDelete();
            $table->string('renderer', 40)->default('remotion');
            $table->string('idempotency_key', 120)->unique();
            $table->string('status', 40)->default('pending')->index();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('render_metrics')->nullable();
            $table->timestampsTz();
        });

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('platform', 40)->default('tiktok')->index();
            $table->string('handle', 120)->index();
            $table->string('external_account_id', 120)->nullable()->index();
            $table->string('display_name', 160)->nullable();
            $table->text('avatar_url')->nullable();
            $table->string('status', 40)->default('not_connected')->index();
            $table->string('affiliate_status', 40)->default('unverified')->index();
            $table->text('access_token_encrypted')->nullable();
            $table->text('refresh_token_encrypted')->nullable();
            $table->timestampTz('token_expires_at')->nullable();
            $table->jsonb('scopes')->nullable();
            $table->jsonb('profile_stats')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['platform', 'handle']);
        });

        Schema::create('publishing_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('video_project_id')->nullable()->constrained('video_projects')->nullOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained('social_accounts')->nullOnDelete();
            $table->string('platform', 40)->default('tiktok')->index();
            $table->timestampTz('scheduled_for')->index();
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->string('status', 40)->default('draft')->index();
            $table->string('idempotency_key', 120)->unique();
            $table->string('external_publish_id', 160)->nullable()->index();
            $table->text('external_post_url')->nullable();
            $table->string('product_tag_status', 40)->default('not_attached')->index();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module', 64)->index();
            $table->string('action', 80)->index();
            $table->string('level', 20)->default('info')->index();
            $table->string('subject_type', 160)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('description');
            $table->jsonb('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('application_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 64)->default('general')->index();
            $table->string('key', 120)->unique();
            $table->text('value_text')->nullable();
            $table->jsonb('value_json')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->text('description')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_settings');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('publishing_schedules');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('video_render_jobs');
        Schema::dropIfExists('video_projects');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('voice_profiles');
    }
};
