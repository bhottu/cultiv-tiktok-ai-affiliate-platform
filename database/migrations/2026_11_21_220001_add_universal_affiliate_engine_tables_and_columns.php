<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_categories')) {
            Schema::create('product_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
                $table->string('name', 120);
                $table->string('slug', 120)->unique();
                $table->string('theme_preset', 40)->default('neutral_studio');
                $table->string('accent_color', 20)->default('#0EA5E9');
                $table->string('description', 300)->nullable();
                $table->unsignedInteger('sort_order')->default(100);
                $table->boolean('is_active')->default(true);
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                $table->index(['parent_id', 'is_active', 'sort_order']);
            });
        }

        Schema::table('affiliate_products', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliate_products', 'product_category_id')) {
                $table->foreignId('product_category_id')->nullable()->after('category')->constrained('product_categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('affiliate_products', 'subcategory')) {
                $table->string('subcategory', 120)->nullable()->after('product_category_id');
            }
            if (! Schema::hasColumn('affiliate_products', 'brand')) {
                $table->string('brand', 120)->nullable()->after('subcategory');
            }
            if (! Schema::hasColumn('affiliate_products', 'description')) {
                $table->text('description')->nullable()->after('brand');
            }
            if (! Schema::hasColumn('affiliate_products', 'features')) {
                $table->jsonb('features')->nullable()->after('description');
            }
            if (! Schema::hasColumn('affiliate_products', 'specifications')) {
                $table->jsonb('specifications')->nullable()->after('features');
            }
            if (! Schema::hasColumn('affiliate_products', 'target_audience')) {
                $table->string('target_audience', 255)->nullable()->after('specifications');
            }
            if (! Schema::hasColumn('affiliate_products', 'research_notes')) {
                $table->text('research_notes')->nullable()->after('target_audience');
            }
            if (! Schema::hasColumn('affiliate_products', 'data_source_type')) {
                $table->string('data_source_type', 40)->default('manual_input')->after('source_platform');
            }
            if (! Schema::hasColumn('affiliate_products', 'verification_status')) {
                $table->string('verification_status', 40)->default('unverified')->after('data_source_type');
            }
            if (! Schema::hasColumn('affiliate_products', 'affiliate_eligibility_status')) {
                $table->string('affiliate_eligibility_status', 40)->default('unverified')->after('verification_status');
            }
        });

        Schema::table('content_ideas', function (Blueprint $table) {
            if (! Schema::hasColumn('content_ideas', 'product_category_id')) {
                $table->foreignId('product_category_id')->nullable()->after('category')->constrained('product_categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('content_ideas', 'subcategory')) {
                $table->string('subcategory', 120)->nullable()->after('product_category_id');
            }
            if (! Schema::hasColumn('content_ideas', 'product_name')) {
                $table->string('product_name', 220)->nullable()->after('affiliate_product_id');
            }
            if (! Schema::hasColumn('content_ideas', 'content_format')) {
                $table->string('content_format', 60)->nullable()->after('format_type');
            }
            if (! Schema::hasColumn('content_ideas', 'marketing_angle')) {
                $table->string('marketing_angle', 60)->nullable()->after('content_format');
            }
            if (! Schema::hasColumn('content_ideas', 'experience_mode')) {
                $table->string('experience_mode', 30)->default('research_based')->after('marketing_angle');
            }
            if (! Schema::hasColumn('content_ideas', 'experience_notes')) {
                $table->jsonb('experience_notes')->nullable()->after('experience_mode');
            }
            if (! Schema::hasColumn('content_ideas', 'research_analysis')) {
                $table->jsonb('research_analysis')->nullable()->after('experience_notes');
            }
        });

        Schema::table('generated_contents', function (Blueprint $table) {
            if (! Schema::hasColumn('generated_contents', 'product_category_id')) {
                $table->foreignId('product_category_id')->nullable()->after('category')->constrained('product_categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('generated_contents', 'subcategory')) {
                $table->string('subcategory', 120)->nullable()->after('product_category_id');
            }
            if (! Schema::hasColumn('generated_contents', 'content_format')) {
                $table->string('content_format', 60)->nullable()->after('narration_style');
            }
            if (! Schema::hasColumn('generated_contents', 'marketing_angle')) {
                $table->string('marketing_angle', 60)->nullable()->after('content_format');
            }
            if (! Schema::hasColumn('generated_contents', 'experience_mode')) {
                $table->string('experience_mode', 30)->default('research_based')->after('marketing_angle');
            }
            if (! Schema::hasColumn('generated_contents', 'experience_notes')) {
                $table->jsonb('experience_notes')->nullable()->after('experience_mode');
            }
        });

        Schema::table('media_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('media_assets', 'campaign_id')) {
                $table->foreignId('campaign_id')->nullable()->after('generated_content_id')->constrained('campaigns')->nullOnDelete();
            }
            if (! Schema::hasColumn('media_assets', 'product_category_id')) {
                $table->foreignId('product_category_id')->nullable()->after('campaign_id')->constrained('product_categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('media_assets', 'category_tag')) {
                $table->string('category_tag', 60)->nullable()->after('product_category_id');
            }
        });

        Schema::table('video_projects', function (Blueprint $table) {
            if (! Schema::hasColumn('video_projects', 'product_category_id')) {
                $table->foreignId('product_category_id')->nullable()->after('template_slug')->constrained('product_categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('video_projects', 'visual_theme_preset')) {
                $table->string('visual_theme_preset', 40)->nullable()->after('product_category_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('video_projects', function (Blueprint $table) {
            if (Schema::hasColumn('video_projects', 'visual_theme_preset')) {
                $table->dropColumn('visual_theme_preset');
            }
            if (Schema::hasColumn('video_projects', 'product_category_id')) {
                $table->dropConstrainedForeignId('product_category_id');
            }
        });

        Schema::table('media_assets', function (Blueprint $table) {
            if (Schema::hasColumn('media_assets', 'category_tag')) {
                $table->dropColumn('category_tag');
            }
            if (Schema::hasColumn('media_assets', 'product_category_id')) {
                $table->dropConstrainedForeignId('product_category_id');
            }
            if (Schema::hasColumn('media_assets', 'campaign_id')) {
                $table->dropConstrainedForeignId('campaign_id');
            }
        });

        Schema::table('generated_contents', function (Blueprint $table) {
            foreach (['experience_notes', 'experience_mode', 'marketing_angle', 'content_format', 'subcategory'] as $col) {
                if (Schema::hasColumn('generated_contents', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (Schema::hasColumn('generated_contents', 'product_category_id')) {
                $table->dropConstrainedForeignId('product_category_id');
            }
        });

        Schema::table('content_ideas', function (Blueprint $table) {
            foreach (['research_analysis', 'experience_notes', 'experience_mode', 'marketing_angle', 'content_format', 'product_name', 'subcategory'] as $col) {
                if (Schema::hasColumn('content_ideas', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (Schema::hasColumn('content_ideas', 'product_category_id')) {
                $table->dropConstrainedForeignId('product_category_id');
            }
        });

        Schema::table('affiliate_products', function (Blueprint $table) {
            foreach ([
                'affiliate_eligibility_status', 'verification_status', 'data_source_type',
                'research_notes', 'target_audience', 'specifications', 'features',
                'description', 'brand', 'subcategory',
            ] as $col) {
                if (Schema::hasColumn('affiliate_products', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (Schema::hasColumn('affiliate_products', 'product_category_id')) {
                $table->dropConstrainedForeignId('product_category_id');
            }
        });

        Schema::dropIfExists('product_categories');
    }
};
