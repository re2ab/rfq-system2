{{--
  RFQ Core — application shell (v20.0 "Graphite & Copper")
  Design system: public/css/rfq-ui.css  (single source of truth, no page-level <style>)
  Sections a page may define: title, subtitle, breadcrumb, actions, highlights, content
--}}
@php
    $user = auth()->user();
    $setting = function (string $key, $default = null) {
        try { return \App\Models\AppSetting::get($key, $default); }
        catch (\Throwable $e) { return $default; }
    };
    $theme        = $setting('theme', 'light');
    $primaryColor = $setting('primary_color', null);
    $companyName  = $setting('company_name', 'شرکت');
    $systemSubtitle = $setting('system_subtitle', 'سیستم مدیریت درخواست خرید');
    $locale = app()->getLocale();
    $rtl = $locale === 'fa';

    $mod = function (string $key) {
        static $cache = null;
        if ($cache === null) {
            try { $cache = \Illuminate\Support\Facades\DB::table('modules')->pluck('is_enabled', 'key'); }
            catch (\Throwable $e) { $cache = collect(); }
        }
        if ($cache->isEmpty()) return true;
        return (bool) ($cache[$key] ?? true);
    };
    $is = function (string $name) {
        try { return request()->routeIs($name) || request()->routeIs($name . '.*'); }
        catch (\Throwable $e) { return false; }
    };

    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/><path d="M9 21V12h6v9"/></svg>',
        'workqueue' => '<svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
        'calendar'  => '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>',
        'tasks'     => '<svg viewBox="0 0 24 24"><path d="m3 17 2 2 4-4"/><path d="m3 7 2 2 4-4"/><path d="M13 6h8"/><path d="M13 12h8"/><path d="M13 18h8"/></svg>',
        'email'     => '<svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/></svg>',
        'mailbox'   => '<svg viewBox="0 0 24 24"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
        'reports'   => '<svg viewBox="0 0 24 24"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>',
        'cases'     => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>',
        'kanban'    => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="5" height="18" rx="1"/><rect x="10" y="3" width="5" height="12" rx="1"/><rect x="17" y="3" width="5" height="8" rx="1"/></svg>',
        'contacts'  => '<svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'orgs'      => '<svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/><path d="M9 10h.01"/><path d="M15 10h.01"/></svg>',
        'docs'      => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="M9 15h6"/></svg>',
        'profile'   => '<svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'settings'  => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6 1.65 1.65 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.14.35.4.65.74.85.3.18.65.27 1 .25H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
        'bell'      => '<svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'search'    => '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg>',
        'default'   => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>',
    ];

    $routeName = '';
    try { $routeName = request()->route()?->getName() ?? ''; } catch (\Throwable $e) {}
    $pageIcon = 'default';
    foreach ([
        'dashboard' => 'dashboard', 'workqueue' => 'workqueue', 'contacts' => 'contacts',
        'organizations' => 'orgs', 'cases' => 'cases', 'kanban' => 'kanban', 'tasks' => 'tasks',
        'calendar' => 'calendar', 'documents' => 'docs', 'emails' => 'email', 'mailbox' => 'mailbox',
        'reports' => 'reports', 'profile' => 'profile', 'settings' => 'settings',
        'custom-fields' => 'settings', 'backup' => 'settings', 'notifications' => 'bell', 'search' => 'search',
    ] as $prefix => $ico) {
        if (str_starts_with($routeName, $prefix)) { $pageIcon = $ico; break; }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#16191f">
    <title>@yield('title', __('app.dashboard')) — {{ $companyName }}</title>

    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      window.tailwind && tailwind.config && (tailwind.config = {
        theme: { extend: {
          colors: {
            brand: '#b8703c', 'brand-dark': '#8a4f27', graphite: '#16191f',
            surface: '#ffffff', canvas: '#f6f4f1',
          },
          fontFamily: { sans: ['Vazirmatn', 'Tahoma', 'sans-serif'] },
        }},
        corePlugins: { preflight: false },
      });
    </script>
    <link rel="stylesheet" href="{{ asset('css/rfq-ui.css') }}?v={{ @filemtime(public_path('css/rfq-ui.css')) ?: '21.1' }}">
    @if(!empty($primaryColor))
      <style>
        :root, body.theme-dark { --copper: {{ $primaryColor }}; --brand: {{ $primaryColor }}; }
      </style>
    @endif
    <script>
      /* apply the stored theme before first paint to avoid a light flash */
      (function () {
        try {
          var saved = localStorage.getItem('rfq_theme');
          var server = @json(($theme ?? 'light') === 'dark' ? 'dark' : 'light');
          var dark = saved ? saved === 'dark' : server === 'dark';
          document.documentElement.classList.toggle('theme-dark', dark);
          document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        } catch (e) {}
      })();
    </script>
    @stack('head')
</head>
<body class="{{ ($theme ?? 'light') === 'dark' ? 'theme-dark' : '' }}">
<script>
  /* keep <body> in sync with the class already resolved on <html> */
  (function () {
    var dark = document.documentElement.classList.contains('theme-dark');
    document.body.classList.toggle('theme-dark', dark);
    var m = document.querySelector('meta[name="theme-color"]');
    if (m) m.content = dark ? '#0b0d10' : '#16191f';
  })();
</script>

<header class="rfq-top">
  <button type="button" class="rfq-icon-btn md:hidden" id="mobileMenuBtn" aria-label="{{ __('app.menu') ?? 'منو' }}">
    <svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
  </button>

  <div class="rfq-top-brand md:hidden">
    <div class="mark">RFQ</div>
  </div>

  <form class="rfq-top-search" action="{{ route('search.index') }}" method="GET" role="search">
    <span class="ico">{!! $icons['search'] !!}</span>
    <input type="text" name="q" id="globalSearch" placeholder="{{ __('app.search') }}" value="{{ request('q') }}" aria-label="{{ __('app.search') }}">
  </form>

  <div class="rfq-top-actions">
    <button type="button" class="rfq-icon-btn" id="themeToggle" title="{{ __('app.theme') }}" aria-label="{{ __('app.theme') }}">
      <svg id="themeIconMoon" viewBox="0 0 24 24" style="display:none"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      <svg id="themeIconSun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
    </button>

    <form method="POST" action="{{ route('locale.switch', $rtl ? 'en' : 'fa') }}" class="desktop-only-action">@csrf
      <button type="submit" class="rfq-icon-btn" title="{{ $rtl ? 'English' : 'فارسی' }}" aria-label="language">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18"/></svg>
      </button>
    </form>

    <a href="{{ route('notifications.index') }}" class="rfq-icon-btn desktop-only-action" title="{{ __('app.notifications') ?? 'اعلان‌ها' }}" aria-label="notifications">
      {!! $icons['bell'] !!}
      @php $unread = 0; try { $unread = $user ? $user->unreadNotifications()->count() : 0; } catch (\Throwable $e) { $unread = 0; } @endphp
      @if($unread > 0)<span class="dot" aria-hidden="true"></span>@endif
    </a>

    <div class="rfq-user-menu" id="userMenu">
      <button type="button" class="rfq-user-avatar-btn" id="userMenuBtn" aria-haspopup="menu" aria-expanded="false" title="{{ $user->name ?? 'کاربر' }}">
        @if($user?->avatar)
          <img src="{{ asset('storage/'.$user->avatar) }}" alt="">
        @else
          <span>{{ mb_substr($user->name ?? 'U', 0, 1) }}</span>
        @endif
      </button>
      <div class="rfq-user-dropdown" id="userDropdown" role="menu">
        <div class="rfq-user-drop-head">{{ $user->name ?? 'کاربر' }}<small>{{ $user->email ?? '' }}</small></div>
        <a href="{{ route('profile.edit') }}" role="menuitem">{{ __('app.profile') }}</a>
        <a href="{{ route('notifications.index') }}" role="menuitem">{{ __('app.notifications') ?? 'اعلان‌ها' }}</a>
        <form method="POST" action="{{ route('locale.switch', $rtl ? 'en' : 'fa') }}">@csrf
          <button type="submit" role="menuitem">{{ $rtl ? 'English' : 'فارسی' }}</button>
        </form>
        <button type="button" role="menuitem" id="themeToggleMobile">{{ __('app.theme') }}</button>
        @can('settings.manage')
        <a href="{{ route('settings.index') }}" role="menuitem">{{ __('app.settings') }}</a>
        @endcan
        <div class="sep" style="height:1px;background:var(--border-soft);margin:4px 0"></div>
        <form method="POST" action="{{ route('logout') }}">@csrf
          <button type="submit" role="menuitem" class="danger">{{ __('app.logout') }}</button>
        </form>
      </div>
    </div>
  </div>
</header>

<div class="rfq-scrim" id="navScrim" aria-hidden="true"></div>

<div class="rfq-shell">
  <nav class="rfq-rail" aria-label="{{ __('app.dashboard') }}">
    <div class="rfq-rail-brand">
      <div class="mark">RFQ</div>
      <div class="txt">{{ $companyName }}<small>{{ $systemSubtitle }}</small></div>
    </div>

    <div class="rfq-rail-group">{{ $rtl ? 'کار روزانه' : 'Daily' }}</div>
    @can('dashboard.view')
    <a href="{{ route('dashboard') }}" class="rfq-rail-item {{ $is('dashboard') ? 'active' : '' }}" data-tip="{{ __('app.dashboard') }}">
      <span class="sym">{!! $icons['dashboard'] !!}</span><span class="lbl">{{ __('app.dashboard') }}</span>
    </a>
    @endcan
    <a href="{{ route('workqueue.index') }}" class="rfq-rail-item {{ $is('workqueue') ? 'active' : '' }}" data-tip="{{ __('app.workqueue') }}">
      <span class="sym">{!! $icons['workqueue'] !!}</span><span class="lbl">{{ __('app.workqueue') }}</span>
    </a>
    @if($mod('tasks'))
    <a href="{{ route('tasks.index') }}" class="rfq-rail-item {{ $is('tasks') ? 'active' : '' }}" data-tip="{{ __('app.tasks') }}">
      <span class="sym">{!! $icons['tasks'] !!}</span><span class="lbl">{{ __('app.tasks') }}</span>
    </a>
    @endif
    @if($mod('calendar'))
    <a href="{{ route('calendar.index') }}" class="rfq-rail-item {{ $is('calendar') ? 'active' : '' }}" data-tip="{{ __('app.calendar') }}">
      <span class="sym">{!! $icons['calendar'] !!}</span><span class="lbl">{{ __('app.calendar') }}</span>
    </a>
    @endif

    <div class="rfq-rail-group">{{ $rtl ? 'پرونده و پایپ‌لاین' : 'Pipeline' }}</div>
    @if($mod('core'))
    <a href="{{ route('cases.index') }}" class="rfq-rail-item {{ $is('cases') ? 'active' : '' }}" data-tip="{{ __('app.cases') }}">
      <span class="sym">{!! $icons['cases'] !!}</span><span class="lbl">{{ __('app.cases') }}</span>
    </a>
    @endif
    @if($mod('kanban'))
    <a href="{{ route('kanban.index') }}" class="rfq-rail-item {{ $is('kanban') ? 'active' : '' }}" data-tip="{{ __('app.pipeline') }}">
      <span class="sym">{!! $icons['kanban'] !!}</span><span class="lbl">{{ __('app.pipeline') }}</span>
    </a>
    @endif

    @if($mod('contacts'))
    <div class="rfq-rail-group">CRM</div>
    <a href="{{ route('contacts.index') }}" class="rfq-rail-item {{ $is('contacts') ? 'active' : '' }}" data-tip="{{ __('app.contacts') }}">
      <span class="sym">{!! $icons['contacts'] !!}</span><span class="lbl">{{ __('app.contacts') }}</span>
    </a>
    <a href="{{ route('organizations.index') }}" class="rfq-rail-item {{ $is('organizations') ? 'active' : '' }}" data-tip="{{ __('app.organizations') }}">
      <span class="sym">{!! $icons['orgs'] !!}</span><span class="lbl">{{ __('app.organizations') }}</span>
    </a>
    @endif

    <div class="rfq-rail-group">{{ $rtl ? 'ارتباطات و اسناد' : 'Comms & Docs' }}</div>
    @if($mod('email'))
    <a href="{{ route('emails.index') }}" class="rfq-rail-item {{ $is('emails') ? 'active' : '' }}" data-tip="{{ __('app.emails') }}">
      <span class="sym">{!! $icons['email'] !!}</span><span class="lbl">{{ __('app.emails') }}</span>
    </a>
    <a href="{{ route('mail.inbox') }}" class="rfq-rail-item {{ request()->is('mail*') || $is('mailbox') ? 'active' : '' }}" data-tip="{{ $rtl ? 'صندوق ایمیل' : 'Mail' }}">
      <span class="sym">{!! $icons['mailbox'] !!}</span><span class="lbl">{{ $rtl ? 'صندوق ایمیل' : 'Mail' }}</span>
    </a>
    @endif
    @if($mod('documents'))
    <a href="{{ route('documents.index') }}" class="rfq-rail-item {{ $is('documents') ? 'active' : '' }}" data-tip="{{ __('app.documents') }}">
      <span class="sym">{!! $icons['docs'] !!}</span><span class="lbl">{{ __('app.documents') }}</span>
    </a>
    @endif
    @can('report.view')
    @if($mod('reports'))
    <a href="{{ route('reports.index') }}" class="rfq-rail-item {{ $is('reports') ? 'active' : '' }}" data-tip="{{ __('app.reports') }}">
      <span class="sym">{!! $icons['reports'] !!}</span><span class="lbl">{{ __('app.reports') }}</span>
    </a>
    @endif
    @endcan

    @can('settings.manage')
    <a href="{{ route('settings.index') }}" class="rfq-rail-item {{ $is('settings') ? 'active' : '' }}" data-tip="{{ __('app.settings') }}">
      <span class="sym">{!! $icons['settings'] !!}</span><span class="lbl">{{ __('app.settings') }}</span>
    </a>
    @endcan

    <div class="rfq-rail-footer">
      <button type="button" class="rfq-rail-toggle" id="railToggle" title="{{ $rtl ? 'جمع/باز کردن منو' : 'Collapse menu' }}" aria-label="collapse">
        <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg>
      </button>
    </div>
  </nav>

  <div class="rfq-workspace">
    <div class="rfq-record-header">
      <div class="rfq-record-meta">
        <div class="rfq-record-icon">{!! $icons[$pageIcon] ?? $icons['default'] !!}</div>
        <div>
          @hasSection('breadcrumb')
            <div class="rfq-breadcrumb">@yield('breadcrumb')</div>
          @endif
          <h1 class="rfq-record-title">@yield('title')</h1>
          @hasSection('subtitle')
            <div class="rfq-page-sub">@yield('subtitle')</div>
          @endif
        </div>
      </div>
      <div class="rfq-record-actions">@yield('actions')</div>
    </div>

    @hasSection('highlights')
      <div class="rfq-highlights">@yield('highlights')</div>
    @endif

    <div class="rfq-content">
      @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="alert alert-error" role="alert">{{ session('error') }}</div>
      @endif
      @if(isset($errors) && $errors->any())
        <div class="alert alert-error" role="alert">
          <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
      @endif
      @yield('content')
    </div>
  </div>
</div>

<nav class="rfq-bottom-nav" aria-label="mobile">
  @can('dashboard.view')
  <a href="{{ route('dashboard') }}" class="{{ $is('dashboard') ? 'active' : '' }}">{!! $icons['dashboard'] !!}<span>{{ __('app.dashboard') }}</span></a>
  @endcan
  @if($mod('core'))
  <a href="{{ route('cases.index') }}" class="{{ $is('cases') ? 'active' : '' }}">{!! $icons['cases'] !!}<span>{{ __('app.cases') }}</span></a>
  @endif
  @if($mod('kanban'))
  <a href="{{ route('kanban.index') }}" class="{{ $is('kanban') ? 'active' : '' }}">{!! $icons['kanban'] !!}<span>{{ __('app.pipeline') }}</span></a>
  @endif
  @if($mod('tasks'))
  <a href="{{ route('tasks.index') }}" class="{{ $is('tasks') ? 'active' : '' }}">{!! $icons['tasks'] !!}<span>{{ __('app.tasks') }}</span></a>
  @endif
  <a href="{{ route('search.index') }}" class="{{ $is('search') ? 'active' : '' }}">{!! $icons['search'] !!}<span>{{ __('app.search_short') }}</span></a>
</nav>

@stack('scripts')

<script>
/* ---------- shell interactions ---------- */
(function () {
  var body = document.body;

  /* collapsible rail (persisted) */
  var RAIL_KEY = 'rfq_rail_collapsed';
  function applyRail(c) { body.classList.toggle('rail-collapsed', !!c); }
  try { applyRail(localStorage.getItem(RAIL_KEY) === '1'); } catch (e) {}
  var railToggle = document.getElementById('railToggle');
  if (railToggle) railToggle.addEventListener('click', function () {
    var next = !body.classList.contains('rail-collapsed');
    applyRail(next);
    try { localStorage.setItem(RAIL_KEY, next ? '1' : '0'); } catch (e) {}
  });

  /* mobile drawer */
  var menuBtn = document.getElementById('mobileMenuBtn');
  var scrim = document.getElementById('navScrim');
  function closeNav() { body.classList.remove('mobile-nav-open'); }
  if (menuBtn) menuBtn.addEventListener('click', function () { body.classList.toggle('mobile-nav-open'); });
  if (scrim) scrim.addEventListener('click', closeNav);

  /* user dropdown */
  var avatarBtn = document.getElementById('userMenuBtn');
  var dropdown = document.getElementById('userDropdown');
  if (avatarBtn && dropdown) {
    avatarBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = dropdown.classList.toggle('open');
      avatarBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    dropdown.addEventListener('click', function (e) { e.stopPropagation(); });
    document.addEventListener('click', function () {
      dropdown.classList.remove('open');
      avatarBtn.setAttribute('aria-expanded', 'false');
    });
  }

  /* keyboard: "/" focuses global search, Esc closes overlays */
  document.addEventListener('keydown', function (e) {
    var t = e.target || {};
    var typing = /INPUT|TEXTAREA|SELECT/.test(t.tagName || '') || t.isContentEditable;
    if (e.key === '/' && !typing) {
      var s = document.getElementById('globalSearch');
      if (s) { e.preventDefault(); s.focus(); }
    }
    if (e.key === 'Escape') {
      closeNav();
      if (dropdown) dropdown.classList.remove('open');
    }
  });

  /* confirm destructive actions */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f && f.dataset && f.dataset.confirm && !window.confirm(f.dataset.confirm)) e.preventDefault();
  });

  /* bulk-select toolbars (cases/contacts/organizations/tasks lists):
     row checkboxes live in the table but associate via the "form" attribute
     to a small toolbar form placed where the old tag filter used to be. */
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t.classList && t.classList.contains('bulk-select-all')) {
      var rows = document.querySelectorAll('input.bulk-row-check[form="' + t.dataset.formTarget + '"]');
      rows.forEach(function (c) { c.checked = t.checked; });
    }
  });
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.bulk-apply-btn');
    if (!btn) return;
    var formId = btn.getAttribute('data-form-id');
    var form = document.getElementById(formId);
    var action = form ? form.querySelector('[name="bulk_action"]') : null;
    var checked = document.querySelectorAll('input.bulk-row-check[form="' + formId + '"]:checked');
    if (!action || !action.value) { e.preventDefault(); alert('یک عملیات را انتخاب کنید.'); return; }
    if (!checked.length) { e.preventDefault(); alert('حداقل یک رکورد را انتخاب کنید.'); return; }
    if (!confirm('این عملیات روی ' + checked.length + ' رکورد اعمال می‌شود. ادامه می‌دهید؟')) { e.preventDefault(); }
  });

  /* report result tables: on mobile these stack as cards instead of
     scrolling horizontally — label each cell from its column header so
     the CSS (.rpt-stack) can show "label: value" per row. */
  if (/^\/reports(\/|$)/.test(location.pathname)) {
    document.querySelectorAll('table.tbl, table.w-full').forEach(function (table) {
      if (table.closest('.data-table-desktop') || table.classList.contains('rpt-stack')) return;
      var headRow = table.querySelector('thead tr');
      if (!headRow) return;
      var labels = Array.from(headRow.children).map(function (th) { return th.textContent.trim(); });
      table.querySelectorAll('tbody tr').forEach(function (row) {
        Array.from(row.children).forEach(function (td, i) {
          if (labels[i]) td.setAttribute('data-label', labels[i]);
        });
      });
      table.classList.add('rpt-stack');
    });
  }

})();

/* ---------- theme ---------- */
(function () {
  var KEY = 'rfq_theme';
  function isDark() { return document.body.classList.contains('theme-dark'); }
  function syncIcons() {
    var moon = document.getElementById('themeIconMoon');
    var sun = document.getElementById('themeIconSun');
    if (moon) moon.style.display = isDark() ? 'none' : '';
    if (sun) sun.style.display = isDark() ? '' : 'none';
  }
  function apply(dark, persist) {
    document.body.classList.toggle('theme-dark', !!dark);
    document.documentElement.classList.toggle('theme-dark', !!dark);
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.content = dark ? '#0b0d10' : '#16191f';
    syncIcons();
    if (!persist) return;
    try { localStorage.setItem(KEY, dark ? 'dark' : 'light'); } catch (e) {}
    try {
      var csrf = document.querySelector('meta[name="csrf-token"]');
      if (csrf) fetch('{{ url('/theme') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.content, 'Accept': 'application/json' },
        body: JSON.stringify({ theme: dark ? 'dark' : 'light' })
      }).catch(function () {});
    } catch (e) {}
  }
  try {
    var saved = localStorage.getItem(KEY);
    if (saved === 'dark') apply(true, false);
    else if (saved === 'light') apply(false, false);
  } catch (e) {}
  syncIcons();
  ['themeToggle', 'themeToggleMobile'].forEach(function (id) {
    var b = document.getElementById(id);
    if (b) b.addEventListener('click', function () { apply(!isDark(), true); });
  });
})();

/* ---------- Persian digits in rendered text ---------- */
(function () {
  @if($rtl)
  var map = { '0':'۰','1':'۱','2':'۲','3':'۳','4':'۴','5':'۵','6':'۶','7':'۷','8':'۸','9':'۹' };
  function fa(s) { return String(s).replace(/[0-9]/g, function (d) { return map[d]; }); }
  function walk(node) {
    if (!node) return;
    if (node.nodeType === 3) {
      var p = node.parentElement;
      if (!p || p.isContentEditable) return;
      if (/^(SCRIPT|STYLE|TEXTAREA|CODE|PRE)$/.test(p.tagName)) return;
      if (p.closest('[data-no-fa-num]')) return;
      if (/[0-9]/.test(node.nodeValue)) node.nodeValue = fa(node.nodeValue);
      return;
    }
    if (node.nodeType !== 1) return;
    if (node.matches('input,textarea,select,code,pre,script,style,[data-no-fa-num]')) return;
    for (var i = 0; i < node.childNodes.length; i++) walk(node.childNodes[i]);
  }
  function run() { walk(document.querySelector('.rfq-workspace') || document.body); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
  @endif
})();
</script>

@include('partials.jalali_assets')

<script>
(function(){
  function rfqFixSelects(){
    document.querySelectorAll('select:not([multiple])').forEach(function(s){
      try { s.size = 1; } catch(e){}
      s.style.height = '40px';
      s.style.maxHeight = '40px';
      s.style.minHeight = '40px';
      s.style.lineHeight = '40px';
      s.style.boxSizing = 'border-box';
      s.style.overflow = 'hidden';
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', rfqFixSelects);
  else rfqFixSelects();
})();
</script>

<script>
(function(){
  // rfq-f-select: size the box to the text of the currently selected
  // option (not the widest option in the list), with a min/max clamp.
  var canvas, ctx;
  function measure(sel){
    if (!ctx) { canvas = document.createElement('canvas'); ctx = canvas.getContext('2d'); }
    var opt = sel.options[sel.selectedIndex];
    var text = opt ? opt.textContent.trim() : '';
    var cs = getComputedStyle(sel);
    ctx.font = cs.fontWeight + ' ' + cs.fontSize + ' ' + cs.fontFamily;
    var textW = ctx.measureText(text).width;
    var w = Math.ceil(textW + 12 /* text-side padding */ + 26 /* arrow-side padding */ + 4 /* buffer */);
    w = Math.max(90, Math.min(240, w));
    sel.style.width = w + 'px';
  }
  function rfqSizeFSelects(){
    document.querySelectorAll('select.rfq-f-select').forEach(function(sel){
      measure(sel);
      if (!sel.dataset.rfqSized) {
        sel.dataset.rfqSized = '1';
        sel.addEventListener('change', function(){ measure(sel); });
      }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', rfqSizeFSelects);
  else rfqSizeFSelects();
  window.rfqSizeFSelects = rfqSizeFSelects;
})();
</script>

</body>
</html>
