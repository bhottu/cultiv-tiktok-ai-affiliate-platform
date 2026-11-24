# DATABASE SCHEMA — PostgreSQL 18 (`affiliate_ai_tiktok`)

Seluruh tabel menggunakan encoding `UTF8`, tipe timestamp dengan timezone (`timestampTz`), foreign key eksplisit, index pada kolom pencarian/status, serta tipe **`JSONB`** PostgreSQL untuk metadata fleksibel.

## Daftar 18 Tabel Inti (+ Tabel Bawaan Laravel Queue/Cache/Sessions)

| # | Tabel | Deskripsi & Catatan Keamanan |
|---|---|---|
| 1 | `users` | Akun pengguna studio (`role`, `timezone`, `is_active`, password ter-hash bcrypt). |
| 2 | `ai_providers` | Konfigurasi AI provider (`api_key_encrypted` terenkripsi, `connection_status`, `settings` JSONB). |
| 3 | `ai_model_configurations` | Daftar model terpilih per provider (`is_free`, `is_default`, `is_fallback`, `pricing_meta` JSONB). |
| 4 | `agent_configurations` | Konfigurasi peran AI agent (`slug`, `module`, `system_prompt`, `config` JSONB). |
| 5 | `affiliate_products` | Database produk TikTok Shop (`approval_status`, `quality_score`, `metadata` JSONB, `softDeletesTz`). Kosong pada Fase 1 (tanpa data palsu). |
| 6 | `product_research_logs` | Riwayat eksekusi riset produk (`source`, `status`, `candidates_found`, `metadata` JSONB). |
| 7 | `content_pillars` | Pilar konten Plant Coaction (`target_ratio_percent`, `goal`, `softDeletesTz`). |
| 8 | `content_ideas` | Bank ide konten terkait pilar & produk (`format_type`, `status`, `priority`, `softDeletesTz`). |
| 9 | `campaigns` | Manajemen campaign harian (`target_videos_per_day`, `status`, `settings` JSONB, `softDeletesTz`). |
| 10 | `generated_contents` | Naskah, hook, caption, `hashtags` JSONB, `disclosure_text`, `qc_report` JSONB, status persetujuan (`softDeletesTz`). |
| 11 | `media_assets` | Aset rekaman asli, foto seller berizin, dan voiceover (`license_status`, `source_type`, `softDeletesTz`). |
| 12 | `voice_profiles` | Profil suara TTS (`provider_slug`, `language_code`, `speaking_rate`, `pitch`, `volume_gain_db`, `credentials_encrypted`). |
| 13 | `video_projects` | Proyek video Remotion 9:16 (`1080×1920`, `30 fps`, `composition_props` JSONB, `qc_status`, `softDeletesTz`). |
| 14 | `video_render_jobs` | Antrean render dengan `idempotency_key` unik, `progress_percent`, `attempts`, `error_message`. |
| 15 | `publishing_schedules` | Jadwal publikasi (`scheduled_for`, `timezone`, `idempotency_key` unik, `product_tag_status`, `external_publish_id`, `softDeletesTz`). |
| 16 | `social_accounts` | Akun sosial terhubung (`access_token_encrypted`, `refresh_token_encrypted`, `affiliate_status`, `scopes` JSONB, `softDeletesTz`). |
| 17 | `activity_logs` | Jejak audit ter-redaksi (`module`, `action`, `level`, `context` JSONB, `created_at`). |
| 18 | `application_settings` | Pengaturan global & heartbeat scheduler (`key` unik, `value_text`, `value_json` JSONB, `is_encrypted`). |

## File Migration
1. `0001_01_01_000000_create_users_table.php` (bawaan Laravel)
2. `0001_01_01_000001_create_cache_table.php` (bawaan Laravel)
3. `0001_01_01_000002_create_jobs_table.php` (bawaan Laravel — database queue & failed jobs)
4. `2026_11_20_100001_create_ai_core_tables.php`
5. `2026_11_20_100002_create_affiliate_and_content_tables.php`
6. `2026_11_20_100003_create_production_publishing_and_system_tables.php`
