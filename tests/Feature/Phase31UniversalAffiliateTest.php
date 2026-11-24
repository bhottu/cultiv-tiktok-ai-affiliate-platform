<?php

namespace Tests\Feature;

use App\Enums\ContentFormatType;
use App\Enums\MarketingAngleType;
use App\Enums\VideoTemplateType;
use App\Models\AffiliateProduct;
use App\Models\AiModelConfiguration;
use App\Models\AiProvider;
use App\Models\Campaign;
use App\Models\ContentIdea;
use App\Models\GeneratedContent;
use App\Models\MediaAsset;
use App\Models\ProductCategory;
use App\Models\User;
use App\Modules\AICore\UniversalAffiliatePromptBuilder;
use App\Modules\VideoProduction\VideoProjectService;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase31UniversalAffiliateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductCategorySeeder::class);

        $this->owner = User::create([
            'name' => 'Owner Universal Studio',
            'email' => 'owner@universal.test',
            'password' => bcrypt('Password123!'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@universal.test',
            'password' => bcrypt('Password123!'),
            'role' => 'operator',
            'is_active' => true,
        ]);
    }

    private function seedConfiguredGemini(): void
    {
        $provider = AiProvider::updateOrCreate(
            ['slug' => 'google_gemini'],
            [
                'name' => 'Google Gemini Developer API',
                'driver' => 'google_gemini',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'api_key_encrypted' => Crypt::encryptString('AIzaSyFakeUniversalTestKey123456'),
                'is_active' => true,
                'is_default' => true,
                'connection_status' => 'connected',
                'settings' => ['default_model' => 'gemini-2.5-flash', 'timeout_seconds' => 30],
            ]
        );

        AiModelConfiguration::updateOrCreate(
            ['ai_provider_id' => $provider->id, 'model_id' => 'gemini-2.5-flash'],
            ['display_name' => 'Gemini 2.5 Flash', 'is_default' => true, 'is_enabled' => true]
        );
    }

    public function test_1_universal_product_categories_subcategories_extensibility_and_non_plant_themes(): void
    {
        $expectedSlugs = [
            'electronics-gadgets' => 'tech_dark',
            'fashion-apparel' => 'fashion_editorial',
            'beauty-skincare' => 'beauty_soft',
            'home-living' => 'home_warm',
            'plants-gardening' => 'botanical_green',
            'other-general' => 'neutral_studio',
        ];

        foreach ($expectedSlugs as $slug => $expectedPreset) {
            $cat = ProductCategory::where('slug', $slug)->first();
            $this->assertNotNull($cat, "Missing category {$slug}");
            $this->assertSame($expectedPreset, $cat->theme_preset);
            if ($slug !== 'plants-gardening') {
                $this->assertNotSame('botanical_green', $cat->theme_preset);
            }
            $this->assertGreaterThan(0, $cat->children()->count(), "Expected subcategories for {$slug}");
        }

        // Dynamic category addition via UI endpoint without code change
        $electronics = ProductCategory::where('slug', 'electronics-gadgets')->firstOrFail();
        $this->actingAs($this->owner)
            ->post(route('content.categories.store'), [
                'name' => 'Drone & Action Cam',
                'parent_id' => $electronics->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_categories', [
            'name' => 'Drone & Action Cam',
            'parent_id' => $electronics->id,
            'theme_preset' => 'tech_dark',
        ]);
    }

    public function test_2_manual_universal_product_input_and_universal_ai_analysis(): void
    {
        $this->seedConfiguredGemini();

        $electronics = ProductCategory::where('slug', 'electronics-gadgets')->firstOrFail();

        $this->actingAs($this->owner)
            ->post(route('affiliate.products.store-manual'), [
                'name' => 'TWS Bluetooth 5.3 ANC Compact',
                'product_category_id' => $electronics->id,
                'subcategory' => 'Audio & TWS',
                'brand' => 'AudioLab',
                'description' => 'Earbuds nirkabel dengan ANC dasar dan pengisian USB-C.',
                'features_text' => "Active Noise Cancellation\nUSB-C Charging",
                'specifications_text' => "Bluetooth: 5.3\nBerat: 42g",
                'price' => 249000,
                'commission_rate' => 8.5,
                'verification_status' => 'partially_verified',
            ])
            ->assertRedirect(route('affiliate.products'));

        $product = AffiliateProduct::firstOrFail();
        $this->assertSame('manual_input', $product->data_source_type);
        $this->assertSame('Electronics & Gadgets', $product->category);
        $this->assertSame('AudioLab', $product->brand);
        $this->assertCount(2, $product->features);
        $this->assertSame('5.3', $product->specifications['Bluetooth']);

        // Analyze with universal prompt (assert prompt does NOT restrict to plants only)
        Http::fake([
            'https://generativelanguage.googleapis.com/*:generateContent*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'relevansi_niche' => 'Sangat relevan untuk kategori Electronics & Gadgets.',
                            'kesesuaian_audiens' => 'Pekerja remote dan komuter harian.',
                            'potensi_ide_konten' => ['Cek fitur ANC di harga 200 ribuan'],
                            'keunggulan' => ['USB-C', 'Bluetooth 5.3'],
                            'kekurangan_atau_risiko' => ['Rating & kapasitas baterai mAh belum tersedia'],
                            'sudut_edukasi' => 'Hal yang perlu dicek sebelum beli TWS budget',
                            'ide_demonstrasi' => 'Tunjukkan ukuran casing dan port USB-C',
                            'potensi_storytelling' => 'Kebutuhan fokus kerja di kafe ramai',
                            'informasi_belum_tersedia' => ['rating', 'jumlah_ulasan', 'garansi'],
                            'rekomendasi_kelayakan' => 'layak',
                            'alasan_rekomendasi' => 'Data dasar jelas, tinggal verifikasi garansi resmi.',
                            'faktor_penilaian' => [
                                'relevansi_niche_skor' => 85,
                                'nilai_edukasi_skor' => 80,
                                'kemudahan_demonstrasi_skor' => 82,
                                'kelengkapan_data_skor' => 70,
                            ],
                        ]),
                    ]]],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->owner)
            ->post(route('affiliate.products.analyze', $product), ['provider' => 'google_gemini'])
            ->assertRedirect(route('affiliate.products'));

        Http::assertSent(function ($req) {
            $body = json_encode($req->data());
            return str_contains($body, 'Electronics & Gadgets')
                && str_contains($body, 'TWS Bluetooth 5.3 ANC Compact');
        });

        $product->refresh();
        $this->assertSame('analyzed', $product->approval_status);
        $this->assertNotNull($product->quality_score);

        // Cross-user isolation still holds
        $this->actingAs($this->otherUser)
            ->post(route('affiliate.products.analyze', $product))
            ->assertForbidden();
    }

    public function test_3_universal_ai_product_research_complete_limited_and_honesty_modes(): void
    {
        $this->seedConfiguredGemini();

        $beauty = ProductCategory::where('slug', 'beauty-skincare')->firstOrFail();

        $researchResponse = json_encode([
            'analysis' => [
                'product_summary' => 'Sunscreen gel ringan untuk pemakaian harian.',
                'functions_and_uses' => ['Melindungi kulit dari paparan UV harian'],
                'target_users' => ['Pengguna dengan aktivitas luar ruangan'],
                'problems_solved' => ['Rasa lengket saat memakai sunscreen'],
                'main_advantages' => ['Tekstur gel ringan berdasarkan deskripsi manual'],
                'limitations_to_note' => ['Nomor BPOM dan daftar bahan lengkap belum tersedia'],
                'unique_selling_point' => 'Ringan untuk aktivitas harian',
                'selling_angles' => ['educational', 'problem_solving'],
                'recommended_video_formats' => ['buying_guide', 'product_review'],
                'recommended_narration_styles' => ['edukatif'],
                'recommended_ctas' => ['Cek daftar bahan sesuai jenis kulitmu sebelum beli.'],
                'unknown_information' => ['Harga produk', 'Daftar komposisi resmi', 'Foto produk'],
                'claims_needing_verification' => ['Klaim SPF 50 PA++++ wajib diverifikasi dari kemasan/BPOM'],
            ],
            'ideas' => [
                [
                    'title' => 'Hal yang Wajib Dicek Sebelum Pilih Sunscreen Gel',
                    'hook_concept' => 'Sebelum beli sunscreen gel, cek dulu 3 informasi penting ini.',
                    'concept_summary' => 'Panduan objektif membaca informasi produk tanpa klaim berlebihan.',
                    'content_format' => 'buying_guide',
                    'marketing_angle' => 'educational',
                    'narration_style' => 'edukatif',
                    'visual_recommendation' => 'Close-up kemasan sunscreen dan daftar komposisi resmi.',
                    'estimated_duration_seconds' => 30,
                    'cta_recommendation' => 'Simpan panduan ini sebelum memilih sunscreen.',
                    'needs_fact_verification' => true,
                ],
            ],
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*:generateContent*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => $researchResponse]]]]],
            ], 200),
        ]);

        // Test limited info product: no price, no specs, no image, research_based mode
        $this->actingAs($this->owner)
            ->post(route('content.research.generate'), [
                'product_category_id' => $beauty->id,
                'subcategory' => 'Sunscreen & Serum',
                'product_name' => 'Aqua Gel Sunscreen SPF 50',
                'product_description' => 'Sunscreen tekstur gel ringan.',
                'target_audience' => 'Pengguna skincare pemula di Indonesia',
                'content_goal' => 'Edukasi jujur berbasis data sebelum membeli',
                'count' => 1,
                'experience_mode' => 'research_based',
                'preferred_provider' => 'google_gemini',
            ])
            ->assertRedirect(route('content.research'));

        // Verify prompt enforces RESEARCH-BASED honesty rules (prohibits fake personal usage claims)
        Http::assertSent(function ($req) {
            $body = json_encode($req->data());
            return str_contains($body, 'RESEARCH-BASED PRODUCT CONTENT')
                && str_contains($body, 'Saya sudah mencoba produk ini')
                && str_contains($body, 'Beauty & Skincare');
        });

        $idea = ContentIdea::firstOrFail();
        $this->assertSame('Beauty & Skincare', $idea->category);
        $this->assertSame('research_based', $idea->experience_mode);
        $this->assertSame('buying_guide', $idea->content_format);
        $this->assertTrue($idea->needs_fact_verification);
        $this->assertNotEmpty($idea->research_analysis['unknown_information']);
        $this->assertNotEmpty($idea->research_analysis['claims_needing_verification']);
    }

    public function test_4_universal_script_generator_formats_angles_and_first_hand_vs_research_based_prompts(): void
    {
        $this->seedConfiguredGemini();

        $builder = app(UniversalAffiliatePromptBuilder::class);

        // Verify research_based rules block fake personal claims
        $researchRules = $builder->experienceHonestyRules('research_based');
        $this->assertStringContainsString('RESEARCH-BASED', $researchRules);
        $this->assertStringContainsString('DILARANG KERAS', $researchRules);

        // Verify first_hand rules carry ONLY user-provided experience facts
        $firstHandRules = $builder->experienceHonestyRules('first_hand', [
            'usage_experience' => 'Dipakai 2 minggu untuk kerja WFH',
            'felt_pros' => 'Bantalan telinga nyaman',
            'found_cons' => 'Kabel agak pendek',
        ]);
        $this->assertStringContainsString('FIRST-HAND PRODUCT EXPERIENCE', $firstHandRules);
        $this->assertStringContainsString('Dipakai 2 minggu untuk kerja WFH', $firstHandRules);
        $this->assertStringContainsString('Kabel agak pendek', $firstHandRules);

        // Test multiple formats via controller (Fashion First-Hand + Home Problem/Solution)
        $fashion = ProductCategory::where('slug', 'fashion-apparel')->firstOrFail();

        $scriptPayload = json_encode([
            'title' => 'Review Jujur Jaket Parasut Ringan Setelah 2 Minggu Pakai',
            'selected_angle' => 'practical_demonstration',
            'hook' => 'Dipakai 2 minggu untuk naik motor, ini kelebihan dan kekurangan jaket ini.',
            'opening' => 'Buat kamu yang sering berkendara, detail kecil di jaket itu penting.',
            'main_narration' => 'Bahannya terasa ringan dan mudah dilipat masuk tas.',
            'supporting_explanation' => 'Catatannya, kantong dalam belum memiliki resleting.',
            'closing' => 'Cocok untuk harian ringan selama sesuai kebutuhanmu.',
            'cta' => 'Cek detail ukuran di deskripsi produk sebelum memilih.',
            'caption' => 'Review pengalaman nyata jaket parasut harian.',
            'hashtags' => ['#fashion', '#jaketharian', '#reviewjujur'],
            'disclosure_text' => 'Tautan produk tersedia di keranjang kuning (affiliate).',
            'suggested_visual_scenes' => [
                ['scene_order' => 1, 'title' => 'Hook', 'narration_segment' => 'Hook', 'overlay_text' => 'Review 2 Minggu Pakai', 'visual_recommendation' => 'Close-up tekstur kain jaket', 'highlight_keywords' => ['Jaket']],
                ['scene_order' => 2, 'title' => 'Kelebihan', 'narration_segment' => 'Main', 'overlay_text' => 'Ringan & Mudah Dilipat', 'visual_recommendation' => 'Demo melipat jaket', 'highlight_keywords' => ['Ringan']],
                ['scene_order' => 3, 'title' => 'Catatan', 'narration_segment' => 'Close', 'overlay_text' => 'Cek Detail Ukuran Dulu', 'visual_recommendation' => 'Tampilan pemakaian harian', 'highlight_keywords' => ['Ukuran']],
            ],
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*:generateContent*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => $scriptPayload]]]]],
            ], 200),
        ]);

        $this->actingAs($this->owner)
            ->post(route('content.scripts.generate'), [
                'title' => 'Review Jaket Parasut Ringan',
                'product_category_id' => $fashion->id,
                'subcategory' => 'Atasan & Outerwear',
                'content_format' => ContentFormatType::PRODUCT_REVIEW->value,
                'marketing_angle' => MarketingAngleType::PRACTICAL_DEMO->value,
                'experience_mode' => 'first_hand',
                'experience_usage' => 'Dipakai 2 minggu untuk naik motor',
                'experience_pros' => 'Ringan dan mudah dilipat',
                'experience_cons' => 'Kantong dalam tanpa resleting',
                'target_audience' => 'Pengendara motor harian',
                'narration_style' => 'review_produk',
                'duration_seconds' => 30,
                'preferred_provider' => 'google_gemini',
            ])
            ->assertRedirect();

        $content = GeneratedContent::firstOrFail();
        $this->assertSame('Fashion & Apparel', $content->category);
        $this->assertSame('product_review', $content->content_format);
        $this->assertSame('first_hand', $content->experience_mode);
        $this->assertSame('Dipakai 2 minggu untuk naik motor', $content->experience_notes['usage_experience']);

        // Creating VideoProject for Fashion picks non-plant visual theme preset!
        $project = app(VideoProjectService::class)->getOrCreateForContent($content, $this->owner);
        $this->assertSame('fashion_editorial', $project->visual_theme_preset);
        $this->assertSame(VideoTemplateType::PRODUCT_REVIEW->value, $project->template_slug);
    }

    public function test_5_universal_asset_grouping_library_filters_metadata_edit_and_legacy_plant_compatibility(): void
    {
        Storage::fake('local');

        $home = ProductCategory::where('slug', 'home-living')->firstOrFail();
        $plants = ProductCategory::where('slug', 'plants-gardening')->firstOrFail();

        $product = AffiliateProduct::create([
            'user_id' => $this->owner->id,
            'external_product_id' => 'manual-home-1',
            'source_platform' => 'manual_input',
            'data_source_type' => 'manual_input',
            'name' => 'Rak Organizer Dapur 3 Susun',
            'category' => 'Home & Living',
            'product_category_id' => $home->id,
            'approval_status' => 'approved',
        ]);

        $campaign = Campaign::create([
            'user_id' => $this->owner->id,
            'affiliate_product_id' => $product->id,
            'name' => 'Campaign Home Q4',
            'slug' => 'campaign-home-q4',
            'status' => 'active',
        ]);

        $homeContent = GeneratedContent::create([
            'user_id' => $this->owner->id,
            'campaign_id' => $campaign->id,
            'affiliate_product_id' => $product->id,
            'product_category_id' => $home->id,
            'category' => 'Home & Living',
            'content_format' => ContentFormatType::PROBLEM_SOLUTION->value,
            'title' => 'Solusi Meja Dapur Berantakan',
            'caption' => 'Caption awal',
            'hashtags' => ['#homeliving'],
            'status' => 'video_ready',
        ]);

        // Legacy Phase 3 plant content remains 100% compatible
        $legacyPlantContent = GeneratedContent::create([
            'user_id' => $this->owner->id,
            'product_category_id' => $plants->id,
            'category' => 'Plant Education',
            'title' => 'Tips Media Porous Anthurium (Legacy)',
            'status' => 'approved',
        ]);

        // Upload asset grouped by Product + Campaign + Category
        $img = UploadedFile::fake()->image('rak-dapur.png', 1080, 1920);
        $this->actingAs($this->owner)
            ->post(route('content.assets.upload'), [
                'asset_file' => $img,
                'category_tag' => 'product_photo',
                'generated_content_id' => $homeContent->id,
                'affiliate_product_id' => $product->id,
                'campaign_id' => $campaign->id,
                'product_category_id' => $home->id,
            ])
            ->assertRedirect();

        $asset = MediaAsset::firstOrFail();
        $this->assertSame($product->id, $asset->affiliate_product_id);
        $this->assertSame($campaign->id, $asset->campaign_id);
        $this->assertSame($home->id, $asset->product_category_id);

        // Filter assets by product, campaign, category
        $this->actingAs($this->owner)
            ->get(route('content.assets', [
                'product_id' => $product->id,
                'campaign_id' => $campaign->id,
                'category_id' => $home->id,
                'type' => 'image',
            ]))
            ->assertOk()
            ->assertSee('rak-dapur.png');

        // Filter Content Library by category, format, campaign, product
        $this->actingAs($this->owner)
            ->get(route('content.library', [
                'category_id' => $home->id,
                'product_id' => $product->id,
                'content_format' => ContentFormatType::PROBLEM_SOLUTION->value,
                'campaign_id' => $campaign->id,
            ]))
            ->assertOk()
            ->assertSee('Solusi Meja Dapur Berantakan')
            ->assertDontSee('Tips Media Porous Anthurium (Legacy)');

        // Legacy plant content still accessible and editable
        $this->actingAs($this->owner)
            ->get(route('content.library', ['inspect' => $legacyPlantContent->id]))
            ->assertOk()
            ->assertSee('Tips Media Porous Anthurium (Legacy)');

        // Inline edit caption, hashtags, and disclosure in library review panel
        $this->actingAs($this->owner)
            ->patch(route('content.metadata.update', $homeContent), [
                'caption' => 'Caption revisi final untuk TikTok',
                'hashtags_text' => '#homeliving #dapurrapi #tiktokshop',
                'disclosure_text' => 'Tautan produk tersedia di keranjang kuning (affiliate).',
            ])
            ->assertRedirect();

        $homeContent->refresh();
        $this->assertSame('Caption revisi final untuk TikTok', $homeContent->caption);
        $this->assertContains('#dapurrapi', $homeContent->hashtags);
        $this->assertStringContainsString('affiliate', $homeContent->disclosure_text);
    }
}
