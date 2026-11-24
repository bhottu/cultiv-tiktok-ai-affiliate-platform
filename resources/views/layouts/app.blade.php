<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Plant Coaction Affiliate AI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ sidebarOpen: false }" class="min-h-screen flex bg-[#f6f8f6]">
    <!-- Mobile backdrop -->
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"></div>

    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           class="fixed inset-y-0 left-0 z-40 w-64 bg-forest-950 text-slate-200 flex flex-col transition-transform duration-200 lg:static lg:flex-shrink-0">
        <div class="px-5 py-4 border-b border-forest-800/80 flex items-center justify-between">
            <div>
                <div class="text-sm font-semibold tracking-tight text-white flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                    Plant Coaction
                </div>
                <div class="text-[11px] text-emerald-200/70 mt-0.5">Universal Affiliate Studio · @plant.coaction</div>
            </div>
            <button @click="sidebarOpen = false" class="lg:hidden text-slate-400 hover:text-white text-sm">✕</button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-4 text-sm">
            <div>
                <a href="{{ route('dashboard') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('dashboard') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                    Dashboard
                </a>
            </div>

            <div>
                <div class="px-2.5 mb-1 text-[11px] font-semibold uppercase tracking-wider text-emerald-200/50">TikTok</div>
                <div class="space-y-0.5">
                    <a href="{{ route('tiktok.account') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('tiktok.account') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                        Akun TikTok
                    </a>
                    <a href="{{ route('tiktok.analytics') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('tiktok.analytics') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                        Analytics
                    </a>
                </div>
            </div>

            <div>
                <div class="px-2.5 mb-1 text-[11px] font-semibold uppercase tracking-wider text-emerald-200/50">Affiliate</div>
                <div class="space-y-0.5">
                    <a href="{{ route('affiliate.readiness') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('affiliate.readiness') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                        Kesiapan Affiliate
                    </a>
                    <a href="{{ route('affiliate.products') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('affiliate.products') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                        Riset Produk
                    </a>
                    <a href="{{ route('affiliate.products.approved') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('affiliate.products.approved') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                        Produk Disetujui
                    </a>
                </div>
            </div>

            <div>
                <div class="px-2.5 mb-1 text-[11px] font-semibold uppercase tracking-wider text-emerald-200/50">Content &amp; Video Studio</div>
                <div class="space-y-0.5">
                    <a href="{{ route('content.library') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('content.library') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                        Content Library &amp; Approval
                    </a>
                    <a href="{{ route('content.research') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('content.research*') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                        AI Content Research
                    </a>
                    <a href="{{ route('content.scripts') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('content.scripts*') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                        AI Script &amp; Voiceover
                    </a>
                    <a href="{{ route('content.assets') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('content.assets*') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                        Visual Asset Manager
                    </a>
                </div>
            </div>

            <div>
                <div class="px-2.5 mb-1 text-[11px] font-semibold uppercase tracking-wider text-emerald-200/50">Operasional &amp; Modul</div>
                <div class="space-y-0.5">
                    <a href="{{ route('modules.strategy') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('modules.strategy') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                        Strategi &amp; Pilar Konten
                    </a>
                    <a href="{{ route('modules.agents') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('modules.agents') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                        AI Agent
                    </a>
                </div>
            </div>

            <div>
                <div class="px-2.5 mb-1 text-[11px] font-semibold uppercase tracking-wider text-emerald-200/50">AI Provider &amp; Pengaturan</div>
                <div class="space-y-0.5">
                    <a href="{{ route('settings.ai.index') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('settings.ai.*', 'settings.openrouter.*', 'settings.gemini.*') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                        AI Provider (OpenRouter / Gemini)
                    </a>
                    <a href="{{ route('settings.tts.index') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('settings.tts.*') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                        Text-to-Speech (Gemini / Cloud)
                    </a>
                    <a href="{{ route('settings.general.index') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('settings.general.*') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                        Pengaturan Umum &amp; Queue
                    </a>
                    <a href="{{ route('modules.activity-logs') }}" class="flex items-center px-2.5 py-2 rounded-lg {{ request()->routeIs('modules.activity-logs') ? 'bg-forest-800 text-white font-medium' : 'text-slate-300 hover:bg-forest-900 hover:text-white' }}">
                        Log Aktivitas
                    </a>
                </div>
            </div>
        </nav>

        <div class="px-4 py-3 border-t border-forest-800/80 text-xs text-emerald-200/70 flex items-center justify-between">
            <span class="truncate">{{ auth()->user()?->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-rose-300 hover:text-rose-200 font-medium">Keluar</button>
            </form>
        </div>
    </aside>

    <!-- Content Area -->
    <div class="flex-1 flex flex-col min-w-0">
        <header class="bg-white border-b border-slate-200/80 px-5 py-3 flex items-center justify-between gap-4 sticky top-0 z-20">
            <div class="flex items-center gap-3 min-w-0">
                <button @click="sidebarOpen = true" class="lg:hidden btn-secondary !px-2.5 !py-1.5">Menu</button>
                <div>
                    <h1 class="text-base font-semibold text-slate-900 truncate">@yield('header', 'Dashboard')</h1>
                    @hasSection('subheader')
                        <p class="text-xs text-slate-500 truncate">@yield('subheader')</p>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 font-medium">
                    WIB · {{ now('Asia/Jakarta')->format('d M Y H:i') }}
                </span>
                <span class="badge-muted">Fase 3.1</span>
            </div>
        </header>

        <main class="flex-1 p-5 max-w-7xl w-full mx-auto space-y-5">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <div class="font-semibold mb-1">Periksa kembali input Anda:</div>
                    <ul class="list-disc pl-5 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
