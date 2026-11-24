<?php

use App\Http\Controllers\AffiliateDashboardController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ContentStudio\ContentLibraryController;
use App\Http\Controllers\ContentStudio\ContentResearchController;
use App\Http\Controllers\ContentStudio\ContentScriptController;
use App\Http\Controllers\ContentStudio\VideoEditorController;
use App\Http\Controllers\ContentStudio\VisualAssetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Settings\AiProviderOverviewController;
use App\Http\Controllers\Settings\GeminiSettingController;
use App\Http\Controllers\Settings\GeminiTtsSettingController;
use App\Http\Controllers\Settings\GeneralSettingController;
use App\Http\Controllers\Settings\GoogleTtsSettingController;
use App\Http\Controllers\Settings\OpenRouterSettingController;
use App\Http\Controllers\TikTokAccountController;
use App\Http\Controllers\WorkspaceModuleController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Fase 2 — TikTok Official OAuth & Account Dashboard
    Route::prefix('dashboard/tiktok')->name('tiktok.')->group(function () {
        Route::get('/', [TikTokAccountController::class, 'index'])->name('account');
        Route::get('/analytics', [TikTokAccountController::class, 'analytics'])->name('analytics');
        Route::post('/connect', [TikTokAccountController::class, 'connect'])
            ->middleware('throttle:15,1')
            ->name('connect');
        Route::get('/callback', [TikTokAccountController::class, 'callback'])
            ->middleware('throttle:20,1')
            ->name('callback');
        Route::post('/{account}/refresh', [TikTokAccountController::class, 'refresh'])
            ->middleware('throttle:15,1')
            ->name('refresh');
        Route::post('/{account}/sync', [TikTokAccountController::class, 'sync'])
            ->middleware('throttle:20,1')
            ->name('sync');
        Route::post('/{account}/disconnect', [TikTokAccountController::class, 'disconnect'])
            ->middleware('throttle:15,1')
            ->name('disconnect');
    });

    // Fase 2 — TikTok Shop Affiliate Readiness, Product Research, AI Analysis & Approval Workflow
    Route::prefix('dashboard/affiliate')->name('affiliate.')->group(function () {
        Route::get('/', [AffiliateDashboardController::class, 'readiness'])->name('readiness');
        Route::get('/products', [AffiliateDashboardController::class, 'products'])->name('products');
        Route::get('/products/approved', [AffiliateDashboardController::class, 'approvedProducts'])->name('products.approved');
        Route::post('/products/search', [AffiliateDashboardController::class, 'search'])
            ->middleware('throttle:20,1')
            ->name('products.search');
        Route::post('/products/manual', [AffiliateDashboardController::class, 'storeManual'])
            ->middleware('throttle:30,1')
            ->name('products.store-manual');
        Route::post('/products/{product}/analyze', [AffiliateDashboardController::class, 'analyze'])
            ->middleware('throttle:15,1')
            ->name('products.analyze');
        Route::post('/products/{product}/status', [AffiliateDashboardController::class, 'updateStatus'])
            ->middleware('throttle:30,1')
            ->name('products.status');
    });

    // Fase 3 — AI Content Generator, Visual Asset Manager, Remotion Video Editor & Approval Studio
    Route::prefix('dashboard/content')->name('content.')->group(function () {
        Route::get('/', [ContentLibraryController::class, 'index'])->name('library');
        Route::patch('/{content}/metadata', [ContentLibraryController::class, 'updateMetadata'])
            ->name('metadata.update');
        Route::post('/{content}/approval', [ContentLibraryController::class, 'decideApproval'])
            ->middleware('throttle:30,1')
            ->name('approval');
        Route::get('/video/{project}/stream', [ContentLibraryController::class, 'streamVideo'])->name('video.stream');
        Route::get('/video/{project}/export', [ContentLibraryController::class, 'exportVideo'])->name('video.export');
        Route::get('/video/{project}/thumbnail', [ContentLibraryController::class, 'thumbnail'])->name('video.thumbnail');

        // AI Content Research
        Route::get('/research', [ContentResearchController::class, 'index'])->name('research');
        Route::post('/research/generate', [ContentResearchController::class, 'generate'])
            ->middleware('throttle:15,1')
            ->name('research.generate');
        Route::post('/categories', [ContentResearchController::class, 'createCategory'])
            ->middleware('throttle:20,1')
            ->name('categories.store');
        Route::post('/research/{idea}/save', [ContentResearchController::class, 'saveToLibrary'])
            ->middleware('throttle:30,1')
            ->name('research.save');
        Route::delete('/research/{idea}', [ContentResearchController::class, 'destroy'])
            ->name('research.destroy');

        // AI Script Generator & Voiceover
        Route::get('/scripts', [ContentScriptController::class, 'index'])->name('scripts');
        Route::post('/scripts/generate', [ContentScriptController::class, 'generate'])
            ->middleware('throttle:15,1')
            ->name('scripts.generate');
        Route::patch('/scripts/{content}', [ContentScriptController::class, 'update'])
            ->name('scripts.update');
        Route::post('/scripts/{content}/regenerate-section', [ContentScriptController::class, 'regenerateSection'])
            ->middleware('throttle:20,1')
            ->name('scripts.regenerate-section');
        Route::post('/scripts/{content}/rewrite', [ContentScriptController::class, 'rewrite'])
            ->middleware('throttle:15,1')
            ->name('scripts.rewrite');
        Route::post('/scripts/{content}/voiceover', [ContentScriptController::class, 'generateVoiceover'])
            ->middleware('throttle:10,1')
            ->name('scripts.voiceover');
        Route::get('/scripts/{content}/continue-editor', [ContentScriptController::class, 'continueToEditor'])
            ->name('scripts.continue-editor');

        // Visual Asset Manager
        Route::get('/assets', [VisualAssetController::class, 'index'])->name('assets');
        Route::post('/assets/upload', [VisualAssetController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('assets.upload');
        Route::patch('/assets/{asset}', [VisualAssetController::class, 'update'])
            ->name('assets.update');
        Route::delete('/assets/{asset}', [VisualAssetController::class, 'destroy'])
            ->name('assets.destroy');
        Route::get('/assets/{asset}/preview', [VisualAssetController::class, 'preview'])
            ->name('assets.preview');

        // Video Preview & Remotion Editor
        Route::get('/editor/{project}', [VideoEditorController::class, 'show'])->name('editor.show');
        Route::post('/editor/{project}/save', [VideoEditorController::class, 'saveDraft'])->name('editor.save');
        Route::post('/editor/{project}/regenerate-subtitles', [VideoEditorController::class, 'regenerateSubtitles'])->name('editor.regenerate-subtitles');
        Route::post('/editor/{project}/render', [VideoEditorController::class, 'dispatchRender'])
            ->middleware('throttle:10,1')
            ->name('editor.render');
        Route::post('/editor/{project}/render/{renderJob}/retry', [VideoEditorController::class, 'retryRender'])
            ->middleware('throttle:10,1')
            ->name('editor.render.retry');
        Route::post('/editor/{project}/render/{renderJob}/cancel', [VideoEditorController::class, 'cancelRender'])
            ->name('editor.render.cancel');
        Route::get('/editor/{project}/render-status', [VideoEditorController::class, 'renderStatus'])
            ->name('editor.render-status');
    });

    // Modul Operasional (tetap dipertahankan untuk kompatibilitas Fase 1)
    Route::get('/agents', [WorkspaceModuleController::class, 'agents'])->name('modules.agents');
    Route::get('/products', [WorkspaceModuleController::class, 'products'])->name('modules.products');
    Route::get('/strategy', [WorkspaceModuleController::class, 'strategy'])->name('modules.strategy');
    Route::get('/content', [WorkspaceModuleController::class, 'content'])->name('modules.content');
    Route::get('/video', [WorkspaceModuleController::class, 'video'])->name('modules.video');
    Route::get('/publishing', [WorkspaceModuleController::class, 'publishing'])->name('modules.publishing');
    Route::get('/analytics', [WorkspaceModuleController::class, 'analytics'])->name('modules.analytics');
    Route::get('/activity-logs', [GeneralSettingController::class, 'activityLogs'])->name('modules.activity-logs');

    // Pengaturan Multi-Provider AI (OpenRouter + Google Gemini)
    Route::prefix('settings/ai-providers')->name('settings.ai.')->group(function () {
        Route::get('/', [AiProviderOverviewController::class, 'index'])->name('index');
        Route::post('/routing', [AiProviderOverviewController::class, 'updateRouting'])->name('routing');
    });

    // Pengaturan OpenRouter AI Provider (Tahap D — dipertahankan utuh)
    Route::prefix('settings/openrouter')->name('settings.openrouter.')->group(function () {
        Route::get('/', [OpenRouterSettingController::class, 'index'])->name('index');
        Route::post('/', [OpenRouterSettingController::class, 'update'])->name('update');
        Route::post('/test', [OpenRouterSettingController::class, 'testConnection'])
            ->middleware('throttle:15,1')
            ->name('test');
        Route::get('/models', [OpenRouterSettingController::class, 'models'])
            ->middleware('throttle:30,1')
            ->name('models');
        Route::post('/select-model', [OpenRouterSettingController::class, 'selectModel'])->name('select-model');
    });

    // Pengaturan Google Gemini Developer API
    Route::prefix('settings/gemini')->name('settings.gemini.')->group(function () {
        Route::post('/', [GeminiSettingController::class, 'update'])->name('update');
        Route::delete('/key', [GeminiSettingController::class, 'removeKey'])->name('remove-key');
        Route::post('/test', [GeminiSettingController::class, 'testConnection'])
            ->middleware('throttle:15,1')
            ->name('test');
        Route::get('/models', [GeminiSettingController::class, 'models'])
            ->middleware('throttle:30,1')
            ->name('models');
        Route::post('/select-model', [GeminiSettingController::class, 'selectModel'])->name('select-model');
    });

    // Pengaturan Text-to-Speech (Google Gemini TTS & Google Cloud TTS)
    Route::prefix('settings/tts')->name('settings.tts.')->group(function () {
        Route::get('/', [GoogleTtsSettingController::class, 'index'])->name('index');
        Route::post('/', [GoogleTtsSettingController::class, 'update'])->name('update');
        Route::post('/provider', [GeminiTtsSettingController::class, 'updateProviderSelection'])->name('provider');
        Route::post('/test', [GoogleTtsSettingController::class, 'testConnection'])
            ->middleware('throttle:15,1')
            ->name('test');
        Route::get('/voices', [GoogleTtsSettingController::class, 'voices'])
            ->middleware('throttle:30,1')
            ->name('voices');
        Route::post('/preview', [GoogleTtsSettingController::class, 'preview'])
            ->middleware('throttle:10,1')
            ->name('preview');

        // Google Gemini TTS (menggunakan Gemini Developer API Key yang sudah ada)
        Route::post('/gemini', [GeminiTtsSettingController::class, 'update'])->name('gemini.update');
        Route::post('/gemini/test', [GeminiTtsSettingController::class, 'testConnection'])
            ->middleware('throttle:15,1')
            ->name('gemini.test');
        Route::post('/gemini/preview', [GeminiTtsSettingController::class, 'preview'])
            ->middleware('throttle:10,1')
            ->name('gemini.preview');
        Route::get('/audio/{asset}', [GeminiTtsSettingController::class, 'downloadAudio'])
            ->name('audio.download');
    });

    // Pengaturan Umum, Queue & Scheduler
    Route::prefix('settings/general')->name('settings.general.')->group(function () {
        Route::get('/', [GeneralSettingController::class, 'index'])->name('index');
        Route::post('/', [GeneralSettingController::class, 'update'])->name('update');
        Route::post('/health-check', [GeneralSettingController::class, 'dispatchHealthCheck'])->name('health-check');
    });
});
