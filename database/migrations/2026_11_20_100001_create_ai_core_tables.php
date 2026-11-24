<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 40)->default('owner')->after('email');
            $table->string('timezone', 64)->default('Asia/Jakarta')->after('role');
            $table->boolean('is_active')->default(true)->after('timezone');
        });

        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('driver', 64)->index();
            $table->string('base_url', 255)->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->string('connection_status', 40)->default('not_configured')->index();
            $table->timestampTz('last_tested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestampsTz();
        });

        Schema::create('ai_model_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('model_id', 180)->index();
            $table->string('display_name', 180);
            $table->string('task_role', 64)->default('general')->index();
            $table->boolean('is_free')->default(false)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_fallback')->default(false)->index();
            $table->integer('context_length')->nullable();
            $table->jsonb('pricing_meta')->nullable();
            $table->jsonb('parameters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['ai_provider_id', 'model_id', 'task_role'], 'ai_model_provider_role_unique');
        });

        Schema::create('agent_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 140);
            $table->string('module', 64)->index();
            $table->text('role_description')->nullable();
            $table->foreignId('ai_model_configuration_id')->nullable()->constrained('ai_model_configurations')->nullOnDelete();
            $table->text('system_prompt')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->jsonb('config')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_configurations');
        Schema::dropIfExists('ai_model_configurations');
        Schema::dropIfExists('ai_providers');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'timezone', 'is_active']);
        });
    }
};
