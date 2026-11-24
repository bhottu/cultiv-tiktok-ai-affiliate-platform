# PROJECT ARCHITECTURE — Plant Coaction Affiliate AI

## 1. Prinsip Arsitektur
- **Modular Service Layer:** Logika bisnis dipisahkan dari HTTP Controller ke dalam `app/Modules/*`.
- **Interface-Driven Providers (`app/Contracts/`):** Provider AI, TTS, Video Renderer, dan Platform Sosial dapat diganti tanpa mengubah alur inti aplikasi.
- **Keamanan Kredensial:** Seluruh API key dan OAuth token disimpan menggunakan enkripsi `AES-256-CBC` (`Illuminate\Support\Facades\Crypt`), disembunyikan dari serialisasi model (`$hidden`), dan disaring oleh `ActivityLogger::redact()`.
- **Zona Waktu & Bahasa:** Seluruh penjadwalan dan tampilan menggunakan `Asia/Jakarta` (WIB) dan antarmuka Bahasa Indonesia.

## 2. Kontrak Interface (`app/Contracts/`)
| Interface | Implementasi Fase 1 | Extensibility |
|---|---|---|
| `AIProviderInterface` | `App\Modules\AIProviders\OpenRouterProvider` | Siap ditambah provider lain via `AIProviderManager` |
| `TextToSpeechInterface` | `App\Modules\TextToSpeech\GoogleCloudTTSProvider` | Siap ditambah `AzureSpeechTTSProvider` & `ElevenLabsTTSProvider` via `TTSProviderManager` |
| `VideoRendererInterface` | `App\Modules\VideoProduction\RemotionVideoRenderer` | Pemeriksaan readiness lokal aktif; render end-to-end pada Fase 5 |
| `SocialPlatformInterface` | `App\Modules\TikTokIntegration\TikTokPlatformService` | OAuth & Content Posting API resmi pada Fase 2 & 6 |

## 3. Pemetaan 15 Modul Domain (`app/Modules/`)
1. `Dashboard` — `DashboardMetricsService` (metrik aktual database + status provider & queue)
2. `AICore` — `AIProviderManager`
3. `AIAgents` — `AgentRegistryService`
4. `AIProviders` — `OpenRouterProvider`
5. `TikTokIntegration` — `TikTokPlatformService`
6. `AffiliateProductResearch` — `ProductResearchService`
7. `ContentStrategy` — `ContentStrategyService`
8. `ContentGeneration` — `ContentGenerationService`
9. `TextToSpeech` — `TTSProviderManager`, `GoogleCloudTTSProvider`
10. `VideoProduction` — `RemotionVideoRenderer`
11. `CampaignManagement` — `CampaignService`
12. `PublishingWorkflow` — `PublishingWorkflowService`
13. `Analytics` — `AnalyticsService`
14. `Settings` — `ApplicationSettingService`
15. `ActivityLogs` — `ActivityLogger`

## 4. Queue, Jobs & Scheduler
- **Queue Driver:** `database` (disimpan di tabel `jobs` & `failed_jobs` pada PostgreSQL 18).
- **Jobs Dasar (`app/Jobs/`):**
  - `GenerateContentJob` (`$tries = 3`, `$backoff = [10, 30, 60]`)
  - `SynthesizeVoiceoverJob` (`$tries = 3`, `$backoff = [10, 30, 60]`)
  - `RenderRemotionVideoJob` (`$tries = 2`, `$backoff = [30, 90]`, idempotency key)
  - `SystemHealthCheckJob` (job internal scheduler per jam pada `Asia/Jakarta` — tanpa publikasi TikTok di Fase 1)
