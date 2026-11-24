<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Modules\TikTokIntegration\TikTokApiClient;
use App\Modules\TikTokIntegration\TikTokOAuthService;
use App\Modules\TikTokIntegration\TikTokPlatformService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TikTokAccountController extends Controller
{
    public function __construct(
        private readonly TikTokPlatformService $platform,
        private readonly TikTokOAuthService $oauth,
        private readonly TikTokApiClient $apiClient,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $account = $this->platform->resolveAccountForUser($user);
        if ($account) {
            Gate::authorize('view', $account);
        }

        return view('tiktok.account', [
            'account' => $account,
            'oauthConfigured' => $this->oauth->isConfigured(),
            'environment' => (string) config('affiliate_ai.tiktok.environment', 'sandbox'),
            'redirectUri' => (string) config('affiliate_ai.tiktok.redirect_uri'),
            'configuredScopes' => array_values(array_filter(array_map('trim', explode(',', (string) config('affiliate_ai.tiktok.scopes', ''))))),
            'analyticsAvailability' => $this->platform->getAnalyticsAvailability($account),
        ]);
    }

    public function analytics(Request $request): View
    {
        $user = $request->user();
        $account = $this->platform->resolveAccountForUser($user);
        if ($account) {
            Gate::authorize('view', $account);
        }

        return view('tiktok.analytics', [
            'account' => $account,
            'analyticsAvailability' => $this->platform->getAnalyticsAvailability($account),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $res = $this->oauth->createAuthorizationUrl($request->user());
        if (! $res['ok'] || empty($res['url'])) {
            return redirect()
                ->route('tiktok.account')
                ->withErrors(['tiktok_oauth' => $res['message']]);
        }

        return redirect()->away($res['url']);
    }

    public function callback(Request $request): RedirectResponse
    {
        $res = $this->oauth->handleCallback(
            $request->user(),
            $request->query('code'),
            $request->query('state'),
            $request->query('error'),
            $request->query('error_description'),
        );

        if (! $res['ok']) {
            return redirect()
                ->route('tiktok.account')
                ->withErrors(['tiktok_oauth' => $res['message']]);
        }

        return redirect()
            ->route('tiktok.account')
            ->with('status', $res['message']);
    }

    public function refresh(Request $request, SocialAccount $account): RedirectResponse
    {
        Gate::authorize('manage', $account);

        $res = $this->oauth->refreshToken($account);
        if (! $res['ok']) {
            return redirect()
                ->route('tiktok.account')
                ->withErrors(['tiktok_oauth' => $res['message']]);
        }

        return redirect()
            ->route('tiktok.account')
            ->with('status', $res['message']);
    }

    public function sync(Request $request, SocialAccount $account): RedirectResponse
    {
        Gate::authorize('manage', $account);

        $res = $this->apiClient->fetchAccountInfo($account);
        if (! $res['ok']) {
            return redirect()
                ->route('tiktok.account')
                ->withErrors(['tiktok_api' => $res['message']]);
        }

        return redirect()
            ->route('tiktok.account')
            ->with('status', $res['message']);
    }

    public function disconnect(Request $request, SocialAccount $account): RedirectResponse
    {
        Gate::authorize('manage', $account);

        $res = $this->oauth->disconnect($account);

        return redirect()
            ->route('tiktok.account')
            ->with('status', $res['message']);
    }
}
