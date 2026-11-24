<?php

namespace App\Providers;

use App\Contracts\AIProviderInterface;
use App\Contracts\SocialPlatformInterface;
use App\Contracts\TextToSpeechInterface;
use App\Contracts\VideoRendererInterface;
use App\Models\AffiliateProduct;
use App\Models\ContentIdea;
use App\Models\GeneratedContent;
use App\Models\MediaAsset;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\VideoProject;
use App\Modules\AICore\AIProviderManager;
use App\Modules\TextToSpeech\TTSProviderManager;
use App\Modules\TikTokIntegration\TikTokPlatformService;
use App\Modules\VideoProduction\RemotionVideoRenderer;
use App\Policies\AffiliateProductPolicy;
use App\Policies\ContentStudioPolicy;
use App\Policies\ProviderSettingPolicy;
use App\Policies\TikTokAccountPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AIProviderInterface::class, fn ($app) => $app->make(AIProviderManager::class)->driver());
        $this->app->bind(TextToSpeechInterface::class, fn ($app) => $app->make(TTSProviderManager::class)->driver());
        $this->app->bind(VideoRendererInterface::class, RemotionVideoRenderer::class);
        $this->app->bind(SocialPlatformInterface::class, TikTokPlatformService::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            $req = request();
            if ($req->isSecure() || strtolower((string) $req->headers->get('X-Forwarded-Proto')) === 'https') {
                URL::forceScheme('https');
            }
        }

        Gate::policy(SocialAccount::class, TikTokAccountPolicy::class);
        Gate::policy(AffiliateProduct::class, AffiliateProductPolicy::class);
        Gate::policy(ContentIdea::class, ContentStudioPolicy::class);
        Gate::policy(GeneratedContent::class, ContentStudioPolicy::class);
        Gate::policy(MediaAsset::class, ContentStudioPolicy::class);
        Gate::policy(VideoProject::class, ContentStudioPolicy::class);
        Gate::define('manage-providers', [ProviderSettingPolicy::class, 'manage']);
        Gate::define('access-studio', fn (User $user) => (bool) $user->is_active);
    }
}
