<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-cyan-500/10 blur-3xl"></div>
        <div class="absolute -right-24 top-1/3 h-96 w-96 rounded-full bg-indigo-500/10 blur-3xl"></div>
    </div>

    @auth
        @php
            $unreadNotifications = auth()->user()->systemNotifications()->whereNull('read_at')->count();
            $menuGroups = [
                [
                    'label' => 'Günlük Üretim',
                    'items' => [
                        ['label' => '1. Haber Kaynağı Girişi', 'route' => 'source-trust.index', 'pattern' => 'source-trust.*', 'allowed' => auth()->user()->can('viewAny', App\Models\NewsSource::class)],
                        ['label' => '2. Ham Haber Toplama', 'route' => 'raw-news.index', 'pattern' => 'raw-news.*', 'allowed' => auth()->user()->can('viewAny', App\Models\RawNewsItem::class)],
                        ['label' => '3. AI Haber Üretimi', 'route' => 'content-batches.index', 'pattern' => 'content-batches.*', 'allowed' => auth()->user()->can('viewAny', App\Models\ContentBatch::class)],
                        ['label' => '4. AI Haber Görseli', 'route' => 'visual-assets.index', 'pattern' => 'visual-assets.*', 'allowed' => auth()->user()->can('viewAny', App\Models\VisualAsset::class)],
                        ['label' => '5. Yayın Merkezi', 'route' => 'publications.index', 'pattern' => 'publications.*', 'allowed' => auth()->user()->can('viewAny', App\Models\Publication::class)],
                        ['label' => '6. WordPress Hedefleri', 'route' => 'publishing-targets.index', 'pattern' => 'publishing-targets.*', 'allowed' => auth()->user()->can('viewAny', App\Models\PublishingTarget::class)],
                    ],
                ],
                [
                    'label' => 'Otomatik İçerikler',
                    'items' => [
                        ['label' => 'Mukaddes Abla', 'route' => 'advice-letters.index', 'pattern' => 'advice-letters.*', 'allowed' => auth()->user()->can('viewAny', App\Models\AdviceLetter::class)],
                        ['label' => 'Günlük Burçlar', 'route' => 'horoscopes.index', 'pattern' => 'horoscopes.*', 'allowed' => auth()->user()->can('viewAny', App\Models\HoroscopeForecast::class)],
                        ['label' => 'Özel Gün Takvimi', 'route' => 'schedules.index', 'pattern' => 'schedules.*', 'allowed' => auth()->user()->can('viewAny', App\Models\ScheduleEntry::class)],
                        ['label' => 'Trend Motoru', 'route' => 'trends.index', 'pattern' => 'trends.*', 'allowed' => auth()->user()->can('viewAny', App\Models\TrendTopic::class)],
                    ],
                ],
                [
                    'label' => 'Editoryal',
                    'items' => [
                        ['label' => 'Haberler', 'route' => 'articles.index', 'pattern' => 'articles.*', 'allowed' => auth()->user()->can('viewAny', App\Models\Article::class)],
                        ['label' => 'AI Yazarlar', 'route' => 'ai-columnists.index', 'pattern' => ['ai-columnists.*', 'columnist-drafts.*'], 'allowed' => auth()->user()->can('viewAny', App\Models\AiColumnist::class)],
                        ['label' => 'Promptlar', 'route' => 'prompts.index', 'pattern' => 'prompts.*', 'allowed' => auth()->user()->can('viewAny', App\Models\AiPrompt::class)],
                        ['label' => 'Kampanyalar', 'route' => 'campaigns.index', 'pattern' => ['campaigns.*', 'campaign-contents.*'], 'allowed' => auth()->user()->can('viewAny', App\Models\Campaign::class)],
                    ],
                ],
                [
                    'label' => 'Sosyal ve Gündem',
                    'items' => [
                        ['label' => 'Sosyal Akış', 'route' => 'social-feed-sources.index', 'pattern' => 'social-feed-*', 'allowed' => auth()->user()->can('viewAny', App\Models\SocialFeedSource::class)],
                        ['label' => 'Sosyal Dinleme', 'route' => 'social-listening.index', 'pattern' => ['social-listening.*', 'social-mentions.*'], 'allowed' => auth()->user()->can('viewAny', App\Models\SocialListeningWatch::class)],
                        ['label' => 'Sosyal Yayıncı', 'route' => 'social-publishing.index', 'pattern' => ['social-publishing.*', 'social-posts.*'], 'allowed' => auth()->user()->can('viewAny', App\Models\SocialPost::class)],
                    ],
                ],
                [
                    'label' => 'Operasyon',
                    'items' => [
                        ['label' => 'Analitik', 'route' => 'analytics.index', 'pattern' => 'analytics.*', 'allowed' => auth()->user()->can('viewAny', App\Models\AnalyticsSnapshot::class)],
                        ['label' => 'Bildirimler', 'route' => 'notifications.index', 'pattern' => 'notifications.*', 'allowed' => auth()->user()->can('viewAny', App\Models\SystemNotification::class), 'badge' => $unreadNotifications],
                        ['label' => 'Hata Kayıtları', 'route' => 'error-logs.index', 'pattern' => 'error-logs.*', 'allowed' => auth()->user()->can('viewAny', App\Models\ErrorLog::class)],
                        ['label' => 'Destek Talepleri', 'route' => 'support-tickets.index', 'pattern' => 'support-tickets.*', 'allowed' => true],
                        ['label' => 'S.S.S. / Kullanım', 'route' => 'faq.index', 'pattern' => 'faq.*', 'allowed' => true],
                    ],
                ],
                [
                    'label' => 'Yönetim',
                    'items' => [
                        ['label' => 'API Entegrasyonları', 'route' => 'api-integrations.index', 'pattern' => 'api-integrations.*', 'allowed' => auth()->user()->can('viewAny', App\Models\ApiIntegration::class)],
                        ['label' => 'Kara Liste', 'route' => 'blacklist-rules.index', 'pattern' => 'blacklist-rules.*', 'allowed' => auth()->user()->can('viewAny', App\Models\BlacklistRule::class)],
                        ['label' => 'Taksonomi', 'route' => 'taxonomy-mappings.index', 'pattern' => 'taxonomy-mappings.*', 'allowed' => auth()->user()->can('viewAny', App\Models\TaxonomyMapping::class)],
                        ['label' => 'Rota Öğrenici', 'route' => 'learned-routes.index', 'pattern' => 'learned-routes.*', 'allowed' => auth()->user()->can('viewAny', App\Models\LearnedRoute::class)],
                        ['label' => 'Yetki Matrisi', 'route' => 'authorization-matrix', 'pattern' => 'authorization-matrix', 'allowed' => auth()->user()->can('viewAuthorizationMatrix')],
                        ['label' => 'Veritabanı Yedekleri', 'route' => 'database-backups.index', 'pattern' => 'database-backups.*', 'allowed' => auth()->user()->can('viewAny', App\Models\DatabaseBackup::class)],
                        ['label' => 'Sistem Ayarları', 'route' => 'system-settings.index', 'pattern' => 'system-settings.*', 'allowed' => auth()->user()->can('viewAny', App\Models\SystemSetting::class)],
                        ['label' => 'Ajanslar', 'route' => 'agencies.index', 'pattern' => 'agencies.*', 'allowed' => auth()->user()->can('viewAny', App\Models\Agency::class)],
                        ['label' => 'Kullanıcılar', 'route' => 'users.index', 'pattern' => 'users.*', 'allowed' => auth()->user()->can('viewAny', App\Models\User::class)],
                    ],
                ],
            ];
        @endphp

        <aside class="fixed inset-y-0 left-0 z-30 hidden w-72 flex-col border-r border-white/10 bg-slate-950/90 backdrop-blur-xl lg:flex">
            <div class="border-b border-white/10 px-6 py-5">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-cyan-400 font-black text-slate-950">A</span>
                    <span class="min-w-0">
                        <strong class="block truncate tracking-[0.15em]">{{ config('app.name') }}</strong>
                        <small class="text-slate-400">Ajans Yönetim Sistemi</small>
                    </span>
                </a>
            </div>

            <nav class="min-h-0 flex-1 overflow-y-auto px-4 py-5" aria-label="Ana menü">
                <a href="{{ route('dashboard') }}" @class([
                    'mb-5 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                    'bg-cyan-400 text-slate-950 shadow-lg shadow-cyan-950/20' => request()->routeIs('dashboard'),
                    'text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs('dashboard'),
                ])>
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Genel Bakış
                </a>
                @include('components.layouts.sidebar-navigation', ['menuGroups' => $menuGroups])
            </nav>

            <div class="border-t border-white/10 p-4">
                <div class="mb-3 rounded-xl bg-white/[.04] px-3 py-3">
                    <strong class="block truncate text-sm">{{ auth()->user()->name }}</strong>
                    <small class="text-slate-400">{{ auth()->user()->role->label() }}</small>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="w-full rounded-xl border border-white/10 px-3 py-2.5 text-sm font-semibold text-slate-300 transition hover:border-rose-400/50 hover:bg-rose-400/5 hover:text-rose-300">Çıkış</button>
                </form>
            </div>
        </aside>

        <header class="sticky top-0 z-40 border-b border-white/10 bg-slate-950/90 backdrop-blur-xl lg:hidden">
            <div class="px-4 py-3">
                <details class="group">
                    <summary class="flex cursor-pointer list-none items-center justify-between">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-3" onclick="event.stopPropagation()">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-cyan-400 font-black text-slate-950">A</span>
                            <span><strong class="block tracking-[0.12em]">{{ config('app.name') }}</strong><small class="text-slate-400">Ajans Yönetim Sistemi</small></span>
                        </a>
                        <span class="rounded-lg border border-white/10 px-3 py-2 text-sm text-slate-300 group-open:bg-white/5">Menü</span>
                    </summary>
                    <nav class="mt-4 max-h-[70vh] overflow-y-auto border-t border-white/10 pt-4" aria-label="Mobil ana menü">
                        <a href="{{ route('dashboard') }}" class="mb-4 block rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('dashboard') ? 'bg-cyan-400 text-slate-950' : 'text-slate-300 hover:bg-white/5' }}">Genel Bakış</a>
                        @include('components.layouts.sidebar-navigation', ['menuGroups' => $menuGroups])
                        <div class="mt-5 border-t border-white/10 pt-4">
                            <p class="px-3 text-sm font-semibold">{{ auth()->user()->name }}</p>
                            <p class="px-3 text-xs text-slate-500">{{ auth()->user()->role->label() }}</p>
                            <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="w-full rounded-xl border border-white/10 px-3 py-2.5 text-sm text-rose-300">Çıkış</button></form>
                        </div>
                    </nav>
                </details>
            </div>
        </header>
    @endauth

    <main @class([
        'relative px-5 py-8 lg:px-10 lg:py-10',
        'lg:ml-72' => auth()->check(),
    ])>
        <div class="mx-auto max-w-7xl">
            @if (session('success'))<div class="mb-6 rounded-xl border border-emerald-400/25 bg-emerald-400/10 px-4 py-3 text-emerald-200">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="mb-6 rounded-xl border border-rose-400/25 bg-rose-400/10 px-4 py-3 text-rose-100">{{ session('error') }}</div>@endif
            @if ($errors->any())<div class="mb-6 rounded-xl border border-rose-400/25 bg-rose-400/10 px-4 py-3 text-rose-100"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            {{ $slot }}
        </div>
    </main>
</body>
</html>
