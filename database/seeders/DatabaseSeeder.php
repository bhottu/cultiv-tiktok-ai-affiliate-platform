<?php

namespace Database\Seeders;

use App\Models\AgentConfiguration;
use App\Models\AiProvider;
use App\Models\ApplicationSetting;
use App\Models\ContentPillar;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\VoiceProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Default local owner account (ganti password saat produksi)
        User::firstOrCreate(
            ['email' => 'owner@plantcoaction.local'],
            [
                'name' => 'Plant Coaction Owner',
                'password' => Hash::make('PlantCoaction2026!'),
                'role' => 'owner',
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ]
        );

        // 2. AI Providers default (OpenRouter & Google Gemini — belum terkonfigurasi hingga API key diisi)
        AiProvider::firstOrCreate(
            ['slug' => 'openrouter'],
            [
                'name' => 'OpenRouter',
                'driver' => 'openrouter',
                'base_url' => config('affiliate_ai.ai.openrouter.base_url', 'https://openrouter.ai/api/v1'),
                'is_active' => true,
                'is_default' => true,
                'connection_status' => 'not_configured',
                'settings' => ['timeout' => 30],
            ]
        );

        AiProvider::firstOrCreate(
            ['slug' => 'google_gemini'],
            [
                'name' => 'Google Gemini',
                'driver' => 'google_gemini',
                'base_url' => config('affiliate_ai.ai.google_gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'),
                'is_active' => true,
                'is_default' => false,
                'connection_status' => 'not_configured',
                'settings' => [
                    'timeout' => 30,
                    'max_retries' => 1,
                    'temperature' => 0.7,
                    'max_output_tokens' => 2048,
                ],
            ]
        );

        // 3. Voice Profile default (Google Cloud TTS — Bahasa Indonesia)
        VoiceProfile::firstOrCreate(
            ['provider_slug' => 'google_cloud_tts', 'is_default' => true],
            [
                'name' => 'Google Cloud TTS — Bahasa Indonesia',
                'language_code' => 'id-ID',
                'speaking_rate' => 1.00,
                'pitch' => 0.00,
                'volume_gain_db' => 0.00,
                'is_active' => true,
                'connection_status' => 'not_configured',
                'metadata' => ['endpoint' => 'https://texttospeech.googleapis.com/v1'],
            ]
        );

        // 4. Target Social Account placeholder (belum terhubung OAuth di Fase 1)
        SocialAccount::firstOrCreate(
            ['platform' => 'tiktok', 'handle' => '@plant.coaction'],
            [
                'display_name' => 'Plant Coaction',
                'status' => 'not_connected',
                'affiliate_status' => 'unverified',
            ]
        );

        // 5. Structural Content Pillars untuk Plant Coaction (bukan data statistik/produk palsu)
        $pillars = [
            ['slug' => 'tips-perawatan-tanaman', 'name' => 'Tips Perawatan Tanaman', 'goal' => 'Membangun kepercayaan audiens lewat edukasi perawatan harian.', 'ratio' => 20],
            ['slug' => 'problem-solving-tanaman', 'name' => 'Problem Solving Tanaman', 'goal' => 'Mengatasi akar busuk, hama, dan stres repotting secara praktis.', 'ratio' => 20],
            ['slug' => 'review-media-tanam', 'name' => 'Review & Racikan Media Tanam', 'goal' => 'Edukasi cocopeat, perlite, pumice, pakis, dan media aroid.', 'ratio' => 15],
            ['slug' => 'perawatan-aroid-anthurium', 'name' => 'Perawatan Aroid & Anthurium', 'goal' => 'Mempertahankan identitas inti Plant Coaction sebagai praktisi aroid.', 'ratio' => 15],
            ['slug' => 'review-alat-berkebun', 'name' => 'Review & Demo Alat Berkebun', 'goal' => 'Demonstrasi objektif sprayer, gunting pruning, dan meteran pH.', 'ratio' => 15],
            ['slug' => 'greenhouse-mikroklimat', 'name' => 'Greenhouse & Perlengkapan Kolektor', 'goal' => 'Membahas grow light, hygrometer, sirkulasi udara, dan pot transparan.', 'ratio' => 15],
        ];
        foreach ($pillars as $p) {
            ContentPillar::firstOrCreate(
                ['slug' => $p['slug']],
                [
                    'name' => $p['name'],
                    'goal' => $p['goal'],
                    'target_ratio_percent' => $p['ratio'],
                    'is_active' => true,
                ]
            );
        }

        // 6. Agent Registry (struktur siap digunakan Fase 2–6)
        $agents = [
            ['slug' => 'tiktok-product-researcher', 'name' => 'TikTok Affiliate Product Researcher', 'module' => 'affiliate_product_research', 'desc' => 'Menilai kandidat produk TikTok Shop relevan untuk Plant Coaction.'],
            ['slug' => 'tiktok-content-strategist', 'name' => 'TikTok Affiliate Content Strategist', 'module' => 'content_strategy', 'desc' => 'Menyusun ide, variasi hook, dan naskah edukasi-affiliate Bahasa Indonesia.'],
            ['slug' => 'remotion-video-producer', 'name' => 'Remotion Video Producer', 'module' => 'video_production', 'desc' => 'Menyiapkan komposisi Remotion 9:16, sinkronisasi voiceover & subtitle.'],
            ['slug' => 'content-qc-agent', 'name' => 'Content Quality Control Agent', 'module' => 'content_generation', 'desc' => 'Memeriksa kepatuhan klaim, disclosure affiliate, dan kualitas audio/visual.'],
            ['slug' => 'tiktok-publishing-agent', 'name' => 'TikTok Publishing Agent', 'module' => 'publishing_workflow', 'desc' => 'Mengelola antrean jadwal publikasi resmi TikTok dan status keranjang kuning.'],
        ];
        foreach ($agents as $ag) {
            AgentConfiguration::firstOrCreate(
                ['slug' => $ag['slug']],
                [
                    'name' => $ag['name'],
                    'module' => $ag['module'],
                    'role_description' => $ag['desc'],
                    'status' => 'draft',
                    'is_enabled' => true,
                ]
            );
        }

        // 7. Pengaturan aplikasi dasar
        $defaults = [
            ['group' => 'general', 'key' => 'brand.name', 'value_text' => 'Plant Coaction', 'description' => 'Nama brand utama'],
            ['group' => 'general', 'key' => 'brand.tiktok_handle', 'value_text' => '@plant.coaction', 'description' => 'Akun TikTok target'],
            ['group' => 'automation', 'key' => 'automation.mode', 'value_text' => 'semi_automatic', 'description' => 'Mode operasional (manual / semi_automatic / automatic)'],
            ['group' => 'automation', 'key' => 'automation.daily_target', 'value_text' => '1', 'description' => 'Target produksi video per hari'],
            ['group' => 'automation', 'key' => 'automation.timezone', 'value_text' => 'Asia/Jakarta', 'description' => 'Zona waktu penjadwalan'],
        ];
        foreach ($defaults as $item) {
            ApplicationSetting::firstOrCreate(
                ['key' => $item['key']],
                $item
            );
        }

        // 8. Universal Product Categories (Fase 3.1)
        $this->call(ProductCategorySeeder::class);
    }
}
