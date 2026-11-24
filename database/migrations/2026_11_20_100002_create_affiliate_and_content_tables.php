<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_products', function (Blueprint $table) {
            $table->id();
            $table->string('external_product_id', 120)->nullable()->index();
            $table->string('name', 220);
            $table->string('category', 100)->index();
            $table->string('seller_name', 160)->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->decimal('promo_price', 14, 2)->nullable();
            $table->decimal('commission_amount', 14, 2)->nullable();
            $table->decimal('commission_rate', 6, 2)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('review_count')->nullable();
            $table->integer('sales_count')->nullable();
            $table->string('stock_status', 40)->default('unknown')->index();
            $table->text('product_url')->nullable();
            $table->text('affiliate_link')->nullable();
            $table->string('approval_status', 40)->default('candidate')->index();
            $table->decimal('quality_score', 5, 2)->nullable()->index();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('fetched_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('product_research_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 80)->index();
            $table->string('query_term', 180)->nullable();
            $table->string('category', 100)->nullable()->index();
            $table->string('status', 40)->default('pending')->index();
            $table->integer('candidates_found')->default(0);
            $table->text('summary')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('content_pillars', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->text('goal')->nullable();
            $table->unsignedTinyInteger('target_ratio_percent')->default(10);
            $table->boolean('is_active')->default(true)->index();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 180);
            $table->string('slug', 120)->unique();
            $table->text('objective')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->unsignedTinyInteger('target_videos_per_day')->default(1);
            $table->jsonb('settings')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('content_ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('content_pillar_id')->nullable()->constrained('content_pillars')->nullOnDelete();
            $table->foreignId('affiliate_product_id')->nullable()->constrained('affiliate_products')->nullOnDelete();
            $table->string('title', 220);
            $table->text('angle')->nullable();
            $table->string('format_type', 60)->default('problem_solution')->index();
            $table->text('hook_concept')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->unsignedSmallInteger('priority')->default(50);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('generated_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('content_idea_id')->nullable()->constrained('content_ideas')->nullOnDelete();
            $table->foreignId('affiliate_product_id')->nullable()->constrained('affiliate_products')->nullOnDelete();
            $table->foreignId('ai_model_configuration_id')->nullable()->constrained('ai_model_configurations')->nullOnDelete();
            $table->string('title', 220);
            $table->text('hook')->nullable();
            $table->longText('script_text')->nullable();
            $table->text('caption')->nullable();
            $table->jsonb('hashtags')->nullable();
            $table->text('cta')->nullable();
            $table->text('disclosure_text')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->jsonb('qc_report')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_contents');
        Schema::dropIfExists('content_ideas');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('content_pillars');
        Schema::dropIfExists('product_research_logs');
        Schema::dropIfExists('affiliate_products');
    }
};
