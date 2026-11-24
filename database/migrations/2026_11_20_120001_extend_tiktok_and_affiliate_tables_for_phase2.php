<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->timestampTz('refresh_token_expires_at')->nullable()->after('token_expires_at');
            $table->timestampTz('connected_at')->nullable()->after('last_synced_at');
            $table->timestampTz('disconnected_at')->nullable()->after('connected_at');
            $table->text('last_error')->nullable()->after('disconnected_at');
            $table->jsonb('oauth_metadata')->nullable()->after('profile_stats');
        });

        Schema::table('affiliate_products', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->foreignId('social_account_id')->nullable()->after('user_id')->constrained('social_accounts')->nullOnDelete();
            $table->string('source_platform', 64)->default('tiktok_shop_api')->after('external_product_id')->index();
            $table->text('image_url')->nullable()->after('product_url');
            $table->jsonb('analysis_result')->nullable()->after('quality_score');
            $table->jsonb('analysis_score_breakdown')->nullable()->after('analysis_result');
            $table->timestampTz('analyzed_at')->nullable()->after('analysis_score_breakdown');
            $table->foreignId('approved_by')->nullable()->after('analyzed_at')->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('social_account_id')->nullable()->after('user_id')->constrained('social_accounts')->nullOnDelete();
            $table->foreignId('affiliate_product_id')->nullable()->after('social_account_id')->constrained('affiliate_products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_product_id');
            $table->dropConstrainedForeignId('social_account_id');
        });

        Schema::table('affiliate_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('social_account_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn([
                'source_platform',
                'image_url',
                'analysis_result',
                'analysis_score_breakdown',
                'analyzed_at',
                'approved_at',
                'approval_notes',
            ]);
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'refresh_token_expires_at',
                'connected_at',
                'disconnected_at',
                'last_error',
                'oauth_metadata',
            ]);
        });
    }
};
