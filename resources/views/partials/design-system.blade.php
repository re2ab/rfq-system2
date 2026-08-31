{{-- ==========================================================================
  RFQ Core — Enterprise Luxury Design System (UI Redesign Layer)
  یک لایه‌ی طراحی واحد بر پایه‌ی همان کلاس‌های موجود پروژه.
  فقط استایل را بازطراحی می‌کند؛ هیچ markup یا منطقی تغییر نمی‌کند.
  Reference feel: Zoho CRM / Salesforce Lightning / Linear — Luxury Minimalism
========================================================================== --}}
<style>
  /* ===================== 1) Tokens ===================== */
  :root{
    /* Brand — deep emerald + warm brass accent (luxury, calm, enterprise) */
    --brand:#0e6b62;
    --brand-hover:#0b5b54;
    --brand-dark:#08453f;
    --brand-soft:#e8f2f0;
    --brand-soft-2:#f4f8f7;
    --accent:#a97c3f;
    --accent-soft:#f7f0e5;

    /* Neutral canvas */
    --bg:#f6f7f8;
    --surface:#ffffff;
    --surface-2:#fbfcfc;
    --text:#111c1a;
    --text-2:#3b4a48;
    --muted:#6d7c79;
    --border:#e3e8e7;
    --border-soft:#edf1f0;
    --border-strong:#d3dbd9;

    /* Semantic */
    --success:#0d7a53; --success-soft:#e8f6ef;
    --warning:#a4680b;  --warning-soft:#fbf2e3;
    --danger:#a8322a;   --danger-soft:#fbeeed;
    --info:#12608f;     --info-soft:#e8f2f8;

    /* Sidebar (deep graphite-green, not pure black) */
    --nav:#0d1a19;
    --nav-2:#122423;
    --nav-fg:rgba(255,255,255,.74);
    --nav-fg-strong:#ffffff;
    --nav-border:rgba(255,255,255,.08);

    /* Shape & elevation — restrained, single light source */
    --radius:14px;
    --radius-sm:10px;
    --radius-xs:8px;
    --shadow-sm:0 1px 2px rgba(11,26,24,.05);
    --shadow:0 2px 6px rgba(11,26,24,.05), 0 8px 24px -12px rgba(11,26,24,.10);
    --shadow-lg:0 12px 40px -12px rgba(11,26,24,.22);
    --focus:0 0 0 3px rgba(14,107,98,.20);

    /* Layout */
    --header-h:60px;
    --rail-w:248px;

    /* Typography scale */
    --fs-11:11px; --fs-12:12px; --fs-13:13.5px; --fs-15:15px;
    --fs-18:18px; --fs-22:22px; --fs-28:28px;

    /* Spacing scale (4pt) */
    --sp-1:4px; --sp-2:8px; --sp-3:12px; --sp-4:16px; --sp-5:20px; --sp-6:24px; --sp-8:32px;
  }
  body.theme-dark{
    --bg:#0c1211;
    --surface:#141d1c;
    --surface-2:#182221;
    --text:#e8efed;
    --text-2:#c3cfcc;
    --muted:#8ea19d;
    --border:#233230;
    --border-soft:#1d2b29;
    --border-strong:#2c3d3a;
    --brand:#22a294;
    --brand-hover:#2bb5a5;
    --brand-dark:#8fd8ce;
    --brand-soft:#13302d;
    --brand-soft-2:#101a19;
    --accent:#d0a061;
    --accent-soft:#2a2116;
    --nav:#0a1211; --nav-2:#0e1a19;
    --success:#4ec295; --success-soft:#122f25;
    --warning:#e0ab55; --warning-soft:#2e2413;
    --danger:#ef8f86;  --danger-soft:#301a18;
    --info:#6fb6e4;    --info-soft:#12242f;
    --shadow-sm:0 1px 2px rgba(0,0,0,.4);
    --shadow:0 2px 8px rgba(0,0,0,.35);
    --shadow-lg:0 16px 44px -12px rgba(0,0,0,.6);
  }

  /* ===================== 2) Base ===================== */
  html{ -webkit-text-size-adjust:100%; }
  body{
    background:var(--bg); color:var(--text);
    font-size:var(--fs-13); line-height:1.6; letter-spacing:0;
    -webkit-font-smoothing:antialiased;
  }
  a{ color:var(--brand); text-decoration:none; }
  a:hover{ color:var(--brand-hover); }
  ::selection{ background:rgba(14,107,98,.16); }
  :focus-visible{ outline:2px solid var(--brand); outline-offset:2px; border-radius:6px; }
  hr,.soft-divider{ border:0; height:1px; background:var(--border-soft); margin:var(--sp-4) 0; }
  h1,h2,h3,h4{ letter-spacing:-.015em; color:var(--text); }

  /* ===================== 3) Top bar ===================== */
  .rfq-top{
    height:var(--header-h);
    background:var(--surface);
    backdrop-filter:none;
    border-bottom:1px solid var(--border);
    box-shadow:none;
    padding:0 var(--sp-4);
    gap:var(--sp-3);
  }
  body.theme-dark .rfq-top{ background:var(--surface) !important; }
  .rfq-top-brand{ gap:var(--sp-3); }
  .rfq-top-brand .mark{
    width:34px;height:34px;border-radius:11px;
    background:linear-gradient(160deg,var(--brand),var(--brand-dark) 78%);
    box-shadow:0 4px 14px -6px rgba(14,107,98,.55);
    font-size:10.5px;letter-spacing:.02em;
  }
  .rfq-top-brand .name{ font-size:13.5px;font-weight:700;line-height:1.25;color:var(--text); }
  .rfq-top-brand .name small{ font-size:10px;font-weight:500;color:var(--muted); }
  .rfq-top-search{ max-width:460px; }
  .rfq-top-search input{
    height:36px;border-radius:var(--radius-sm) !important;
    background:var(--surface-2) !important;border:1px solid var(--border) !important;
    padding:0 var(--sp-4) !important;
  }
  .rfq-top-search input::placeholder{ color:var(--muted); }
  .rfq-top-search input:focus{
    background:var(--surface) !important;border-color:var(--brand) !important;box-shadow:var(--focus) !important;
  }
  .rfq-icon-btn{
    width:36px;height:36px;border-radius:var(--radius-sm);color:var(--muted);
    transition:background .15s ease,color .15s ease;
  }
  .rfq-icon-btn:hover{ background:var(--brand-soft);color:var(--brand); }
  .rfq-rail-toggle{
    width:36px;height:36px;border-radius:var(--radius-sm);
    background:var(--surface);border:1px solid var(--border);color:var(--text-2);
  }
  .rfq-rail-toggle:hover{ background:var(--brand-soft);border-color:var(--brand);color:var(--brand); }
  .rfq-user-avatar-btn{
    width:34px;height:34px;border-radius:999px;
    background:var(--brand);color:#fff;font-weight:700;
    box-shadow:0 0 0 2px var(--surface),0 0 0 3px var(--border);
  }
  .rfq-user-dropdown{
    border-radius:var(--radius);border:1px solid var(--border);
    box-shadow:var(--shadow-lg);padding:var(--sp-1);min-width:224px;background:var(--surface);
  }
  .rfq-user-dropdown a,.rfq-user-dropdown button{
    border-radius:var(--radius-xs);font-weight:600;color:var(--text-2);padding:9px 12px;
  }
  .rfq-user-dropdown a:hover,.rfq-user-dropdown button:hover{ background:var(--brand-soft);color:var(--brand); }
  .rfq-user-drop-head{ color:var(--muted);border-color:var(--border-soft); }
  .lang-switch{ height:34px;border-radius:var(--radius-sm);border-color:var(--border);background:var(--surface-2); }
  .lang-switch button{ font-weight:700;color:var(--muted); }
  .lang-switch button.active{ background:var(--brand);color:#fff; }

  /* ===================== 4) Sidebar rail ===================== */
  .rfq-rail{
    background:linear-gradient(180deg,var(--nav-2),var(--nav));
    border-color:var(--nav-border) !important;
    padding:var(--sp-3) 10px var(--sp-6) !important;
    gap:2px;
  }
  .rfq-rail-brand{ border-radius:11px;background:var(--brand);box-shadow:0 6px 16px -8px rgba(0,0,0,.7); }
  .rfq-rail-item{
    min-height:40px;border-radius:var(--radius-sm);color:var(--nav-fg);
    font-weight:600;font-size:13px;padding:8px 11px;
    transition:background .15s ease,color .15s ease;
  }
  .rfq-rail-item:hover{ background:rgba(255,255,255,.07);color:var(--nav-fg-strong); }
  .rfq-rail-item.active{
    background:rgba(255,255,255,.10);color:var(--nav-fg-strong);
    box-shadow:inset 3px 0 0 var(--brand);
  }
  [dir="ltr"] .rfq-rail-item.active{ box-shadow:inset -3px 0 0 var(--brand); }
  .rfq-rail-item .sym{ opacity:.9; }
  .rfq-rail-item.active .sym{ color:#7fd8cd;opacity:1; }
  .rfq-rail-sep{ background:var(--nav-border); }
  .rfq-rail-footer{ border-color:var(--nav-border); }

  /* ===================== 5) Page & record headers ===================== */
  .rfq-record-header,.rfq-object-bar{
    background:var(--surface);border-color:var(--border);padding:var(--sp-4) var(--sp-5);
  }
  .rfq-record-title{ font-size:var(--fs-22);font-weight:700; }
  .rfq-record-type{ color:var(--muted);font-weight:600;letter-spacing:.02em; }
  .rfq-record-icon{ border-radius:var(--radius);background:var(--brand-soft);color:var(--brand); }
  .rfq-tab{ border-radius:var(--radius-xs) var(--radius-xs) 0 0;font-weight:600;padding:10px 14px; }
  .rfq-tab:hover{ color:var(--text);background:var(--brand-soft-2); }
  .rfq-tab.active{ color:var(--brand);border-bottom-color:var(--brand);background:transparent; }

  .rfq-content{ padding:var(--sp-5) var(--sp-5) var(--sp-8); }
  .rfq-page-head{
    margin-bottom:var(--sp-5);padding-bottom:var(--sp-4);border-bottom:1px solid var(--border);
  }
  .rfq-page-title{ font-size:var(--fs-22);font-weight:700;gap:var(--sp-2); }
  .rfq-page-count{ background:var(--brand-soft);color:var(--brand);font-weight:700;padding:3px 9px; }
  .rfq-page-sub{ color:var(--muted);font-size:var(--fs-13); }
  .page-section-title{
    font-size:var(--fs-12);font-weight:700;color:var(--muted);letter-spacing:.04em;text-transform:uppercase;
  }

  /* ===================== 6) Buttons ===================== */
  .btn,.sf-btn,.ds-btn{
    height:36px;padding:0 var(--sp-4);border-radius:var(--radius-sm);
    font-size:var(--fs-13);font-weight:600;gap:var(--sp-2);
    transition:background .15s ease,border-color .15s ease,box-shadow .15s ease,transform .12s ease;
  }
  .btn:active{ transform:translateY(1px); }
  .btn-primary,.sf-btn-brand,.ds-btn-primary{
    background:var(--brand) !important;color:#fff !important;border-color:var(--brand) !important;
    box-shadow:0 1px 2px rgba(11,26,24,.10);
  }
  .btn-primary:hover{ background:var(--brand-hover) !important;box-shadow:0 6px 16px -8px rgba(14,107,98,.65); }
  .btn-ghost,.sf-btn-neutral,.ds-btn-ghost{
    background:var(--surface) !important;color:var(--text-2) !important;border-color:var(--border-strong) !important;
  }
  .btn-ghost:hover{ background:var(--brand-soft-2) !important;border-color:var(--brand) !important;color:var(--brand) !important; }
  .btn-soft{ background:var(--brand-soft) !important;color:var(--brand) !important;border-color:transparent !important; }
  .btn-soft:hover{ background:var(--brand-soft) !important;filter:brightness(.96); }
  .btn-danger{ background:var(--danger-soft) !important;color:var(--danger) !important;border-color:transparent !important; }
  .btn-danger:hover{ background:var(--danger) !important;color:#fff !important; }
  .btn-sm{ height:31px;padding:0 var(--sp-3);font-size:var(--fs-12); }
  .btn[disabled],.btn:disabled{ opacity:.55;cursor:not-allowed;transform:none; }
  main .bg-blue-600,main .bg-blue-700{ background:var(--brand) !important;border-radius:var(--radius-sm) !important; }
  main a.text-blue-600,main a.text-blue-700{ color:var(--brand) !important; }

  /* ===================== 7) Cards ===================== */
  .card,.sf-card,.ds-card{
    background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
    box-shadow:var(--shadow-sm);transition:box-shadow .18s ease,border-color .18s ease;
  }
  .card:hover{ box-shadow:var(--shadow);border-color:var(--border-strong); }
  .card-h,.sf-card-header,.ds-card-header{
    background:var(--surface-2) !important;border-bottom:1px solid var(--border-soft) !important;
    padding:11px var(--sp-4) !important;font-size:var(--fs-13);font-weight:700;color:var(--text) !important;
  }
  .card-b,.sf-card-body,.ds-card-body{ padding:var(--sp-4) !important; }
  .card-b.pad0{ padding:0 !important; }

  /* ===================== 8) Stats / KPI ===================== */
  .grid-stats{ gap:var(--sp-3);margin-bottom:var(--sp-5); }
  .stat,.sf-stat,.stat-card{
    background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
    padding:var(--sp-4) var(--sp-5);box-shadow:var(--shadow-sm);
    transition:box-shadow .18s ease,transform .18s ease;
  }
  .stat:hover{ box-shadow:var(--shadow);transform:translateY(-1px); }
  .stat::before{
    height:2px;background:var(--brand);opacity:.9;
  }
  .stat .lbl{ font-size:var(--fs-11);font-weight:700;color:var(--muted);letter-spacing:.04em; }
  .stat .num{ font-size:var(--fs-28);font-weight:700;letter-spacing:-.03em;margin-top:6px;color:var(--text); }
  .stat .sub{ font-size:var(--fs-11);color:var(--muted); }

  /* ===================== 9) Tables ===================== */
  .tbl,.sf-table{ font-size:var(--fs-13); }
  .tbl th{
    background:var(--surface-2) !important;color:var(--muted) !important;
    font-size:var(--fs-11);font-weight:700;letter-spacing:.04em;
    padding:10px var(--sp-3);border-bottom:1px solid var(--border) !important;
    position:sticky;top:0;z-index:2;white-space:nowrap;
  }
  .tbl td{ padding:11px var(--sp-3);border-bottom:1px solid var(--border-soft);color:var(--text-2);vertical-align:middle; }
  .tbl tbody tr{ transition:background .12s ease; }
  .tbl tbody tr:hover td{ background:var(--brand-soft-2) !important; }
  .tbl tbody tr:last-child td{ border-bottom:0; }
  .tbl a{ color:var(--brand);font-weight:600; }
  .tbl a:hover{ text-decoration:underline; }
  .data-table-wrap{ overflow:hidden; }
  .data-table-desktop{ overflow-x:auto; -webkit-overflow-scrolling:touch; }
  .mobile-list-card{ padding:var(--sp-3) var(--sp-4);border-color:var(--border-soft); }
  .mobile-list-card:hover{ background:var(--brand-soft-2); }

  /* ===================== 10) Badges / chips / status ===================== */
  .badge,.sf-badge,.ds-badge,.chip{
    border-radius:999px;font-size:var(--fs-11);font-weight:700;letter-spacing:.01em;
    padding:3px 9px;background:var(--brand-soft);color:var(--brand);
    border:1px solid transparent;
  }
  .chip{ height:26px;padding:0 10px;display:inline-flex;align-items:center;gap:5px; }
  .chip-ok{ background:var(--success-soft) !important;color:var(--success) !important; }
  .chip-warn{ background:var(--warning-soft) !important;color:var(--warning) !important; }
  .chip-danger{ background:var(--danger-soft) !important;color:var(--danger) !important; }
  .badge-info{ background:var(--info-soft) !important;color:var(--info) !important; }
  .badge-muted{ background:var(--border-soft) !important;color:var(--muted) !important; }
  .status-dot{ width:7px;height:7px;box-shadow:0 0 0 3px color-mix(in srgb,var(--brand) 18%,transparent); }
  .status-dot.ok{ background:var(--success);box-shadow:0 0 0 3px color-mix(in srgb,var(--success) 18%,transparent); }
  .status-dot.warn{ background:var(--warning);box-shadow:0 0 0 3px color-mix(in srgb,var(--warning) 18%,transparent); }
  .status-dot.danger{ background:var(--danger);box-shadow:0 0 0 3px color-mix(in srgb,var(--danger) 18%,transparent); }

  /* ===================== 11) Path / stage indicator ===================== */
  .path{ background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:var(--sp-2);gap:var(--sp-1); }
  .path-step,.sf-path-step{
    border-radius:var(--radius-xs);background:var(--surface-2);color:var(--muted);
    font-size:var(--fs-11);font-weight:600;padding:9px var(--sp-1);
  }
  .path-step.has,.sf-path-step.complete{ background:var(--brand-soft) !important;color:var(--brand) !important; }
  .path-step.on,.sf-path-step.current{ background:var(--brand) !important;color:#fff !important; }

  /* ===================== 12) Forms ===================== */
  label{ font-size:var(--fs-12);font-weight:600;color:var(--text-2); }
  input:not([type=checkbox]):not([type=radio]),select,textarea,.filter-input{
    border:1px solid var(--border-strong) !important;border-radius:var(--radius-sm) !important;
    background:var(--surface) !important;color:var(--text) !important;
    min-height:36px;font-size:var(--fs-13) !important;
    transition:border-color .15s ease,box-shadow .15s ease;
  }
  input::placeholder,textarea::placeholder{ color:var(--muted); }
  input:focus,select:focus,textarea:focus{
    border-color:var(--brand) !important;box-shadow:var(--focus) !important;outline:none !important;
  }
  input[aria-invalid="true"],.input-error,.border-red-500{
    border-color:var(--danger) !important;box-shadow:0 0 0 3px rgba(168,50,42,.14) !important;
  }
  .filter-bar{
    padding:var(--sp-3);gap:var(--sp-2);background:var(--surface);
    border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-sm);
    margin-bottom:var(--sp-3);
  }

  /* ===================== 13) Field rows / related lists ===================== */
  .field-row{ padding:9px 0;border-color:var(--border-soft);grid-template-columns:120px 1fr auto; }
  .field-row .lbl{ font-size:var(--fs-11);font-weight:700;color:var(--muted);letter-spacing:.02em; }
  .field-row .val{ font-size:var(--fs-13);color:var(--text); }
  .rel-item{ padding:11px var(--sp-4);border-color:var(--border-soft); }
  .rel-item:hover{ background:var(--brand-soft-2); }
  .rel-meta{ color:var(--muted);font-size:var(--fs-12); }

  /* ===================== 14) Timeline / activity ===================== */
  .act-composer{ gap:var(--sp-2);padding:var(--sp-3) var(--sp-4);border-color:var(--border-soft); }
  .act-chip{
    width:36px;height:36px;border-radius:var(--radius-sm);border-color:var(--border-strong);
    background:var(--surface);color:var(--brand);transition:background .15s ease,border-color .15s ease;
  }
  .act-chip:hover{ background:var(--brand-soft);border-color:var(--brand); }
  .act-item,.timeline-item,.activity-feed-item{ border-color:var(--border-soft); }
  .act-title{ color:var(--text);font-weight:700; }
  .act-when,.timeline-meta{ color:var(--muted);font-size:var(--fs-11); }
  .timeline{ position:relative; }
  .timeline-item{ padding:var(--sp-3) 0;gap:var(--sp-3); }
  .timeline-avatar{
    width:34px;height:34px;background:var(--brand-soft);color:var(--brand);
    font-weight:700;border:1px solid var(--border-soft);
  }
  .timeline-avatar.call{ background:var(--success-soft);color:var(--success); }
  .timeline-body{ color:var(--text-2); }
  .call-card{
    background:var(--success-soft);border:1px solid color-mix(in srgb,var(--success) 24%,transparent);
    border-radius:var(--radius-sm);
  }
  .call-card .call-title{ color:var(--success);font-weight:700; }
  .feed-dot{ background:var(--brand);box-shadow:0 0 0 3px var(--brand-soft); }
  .activity-feed-item:hover{ background:var(--brand-soft-2); }

  /* ===================== 15) Kanban ===================== */
  .kanban-card-meta span{
    background:var(--surface-2);border:1px solid var(--border-soft);
    padding:2px 7px;border-radius:6px;font-weight:600;color:var(--muted);
  }
  [class*="kanban"] .card,.kanban-card{
    transition:box-shadow .18s ease,transform .18s ease;
  }
  [class*="kanban"] .card:hover,.kanban-card:hover{ transform:translateY(-2px);box-shadow:var(--shadow); }

  /* ===================== 16) Charts ===================== */
  .chart-track{ display:block;background:var(--surface-2);height:9px;border:1px solid var(--border-soft); }
  .chart-fill{ display:block;height:100%;background:linear-gradient(90deg,var(--brand),color-mix(in srgb,var(--brand) 60%,var(--accent))); }
  .chart-label{ color:var(--muted);font-weight:600; }
  .chart-val{ font-weight:700;color:var(--text); }

  /* ===================== 17) Empty / loading / alerts / toasts ===================== */
  .empty-state{ padding:56px var(--sp-6);color:var(--muted); }
  .empty-state .empty-ico{
    width:56px;height:56px;border-radius:18px;background:var(--brand-soft);color:var(--brand);
    box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--brand) 14%,transparent);
  }
  .skeleton-line{
    background:linear-gradient(90deg,var(--border-soft) 25%,var(--surface-2) 50%,var(--border-soft) 75%);
    background-size:200% 100%;border-radius:6px;
  }
  .alert,[role=alert]{ border-radius:var(--radius-sm);font-weight:600;border:1px solid transparent; }
  .alert-success,.bg-green-100{ background:var(--success-soft) !important;color:var(--success) !important;border-color:color-mix(in srgb,var(--success) 26%,transparent) !important; }
  .alert-error,.bg-red-100{ background:var(--danger-soft) !important;color:var(--danger) !important;border-color:color-mix(in srgb,var(--danger) 26%,transparent) !important; }
  .rfq-toast{
    border-radius:var(--radius);box-shadow:var(--shadow-lg);font-weight:600;padding:12px var(--sp-4);
  }
  .rfq-toast-success{ background:var(--surface);color:var(--success);border:1px solid color-mix(in srgb,var(--success) 26%,transparent); }
  .rfq-toast-error{ background:var(--surface);color:var(--danger);border:1px solid color-mix(in srgb,var(--danger) 26%,transparent); }

  /* ===================== 18) Settings / email layouts ===================== */
  .settings-layout,.email-layout{ gap:var(--sp-4); }
  .settings-nav-item{ border-color:var(--border-soft);color:var(--text-2);padding:10px var(--sp-4); }
  .settings-nav-item:hover{ background:var(--brand-soft-2);color:var(--brand); }
  .settings-nav-item.active{ background:var(--brand-soft);color:var(--brand);box-shadow:inset 3px 0 0 var(--brand); }
  [dir="ltr"] .settings-nav-item.active{ box-shadow:inset -3px 0 0 var(--brand); }
  .settings-nav-group{ color:var(--muted);letter-spacing:.06em; }

  /* ===================== 19) Dark-mode overrides for legacy hardcodes ===================== */
  body.theme-dark .card,body.theme-dark .sf-card,body.theme-dark .stat,
  body.theme-dark .rfq-record-header,body.theme-dark .rfq-object-bar,
  body.theme-dark .path,body.theme-dark .filter-bar,body.theme-dark .act-chip{
    background:var(--surface) !important;border-color:var(--border) !important;color:var(--text) !important;
  }
  body.theme-dark .card-h,body.theme-dark .tbl th{ background:var(--surface-2) !important; }
  body.theme-dark .tbl td{ border-color:var(--border-soft) !important;color:var(--text-2) !important; }
  body.theme-dark .btn-ghost{ background:var(--surface-2) !important;color:var(--text) !important;border-color:var(--border-strong) !important; }
  body.theme-dark .rfq-user-dropdown{ background:var(--surface) !important;border-color:var(--border) !important; }
  body.theme-dark .bg-white,body.theme-dark .bg-gray-50,body.theme-dark .bg-gray-100{
    background:var(--surface) !important;color:var(--text) !important;
  }
  body.theme-dark .text-gray-500,body.theme-dark .text-gray-600,body.theme-dark .text-gray-700{ color:var(--muted) !important; }

  /* ===================== 20) Responsive ===================== */
  @media (max-width:1100px){
    .rfq-content{ padding:var(--sp-4); }
  }
  @media (max-width:767px){
    :root{ --header-h:56px; }
    body{ font-size:13px; }
    .rfq-content{ padding:var(--sp-3) var(--sp-3) 84px; }
    .rfq-page-head{ margin-bottom:var(--sp-3);padding-bottom:var(--sp-3); }
    .rfq-page-title{ font-size:var(--fs-18); }
    .rfq-page-actions{ width:100%; }
    .rfq-page-actions .btn{ flex:1 1 auto; }
    .stat{ padding:var(--sp-3) var(--sp-4); }
    .stat .num{ font-size:var(--fs-22); }
    .filter-bar{ padding:var(--sp-2); }
    .filter-bar .filter-input,.filter-bar select,.filter-bar input{ flex:1 1 46%;min-width:0; }
    .rfq-record-header{ padding:var(--sp-3); }
    .rfq-record-title{ font-size:var(--fs-18); }
    .rfq-tabs{ overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none; }
    .rfq-tabs::-webkit-scrollbar{ display:none; }
    .field-row{ grid-template-columns:1fr;gap:2px; }
    .rfq-rail{ box-shadow:var(--shadow-lg); }
  }

  /* ===================== 21) Micro-interactions ===================== */
  @media (prefers-reduced-motion:no-preference){
    .card,.stat,.btn,.rfq-rail-item,.rfq-icon-btn,.tbl tbody tr{ will-change:auto; }
    .rfq-user-dropdown.open{ animation:dsPop .16s ease-out; }
    @keyframes dsPop{ from{ opacity:0;transform:translateY(-4px) scale(.985);} to{ opacity:1;transform:none;} }
  }
  @media (prefers-reduced-motion:reduce){
    *,*::before,*::after{ animation-duration:.01ms !important;transition-duration:.01ms !important; }
  }

  /* ===================== 22) Print ===================== */
  @media print{
    .rfq-rail,.rfq-top,.rfq-page-actions,.filter-bar{ display:none !important; }
    .rfq-shell,.rfq-top{ margin:0 !important; }
    .card{ box-shadow:none;border-color:#ccc; }
    body{ background:#fff; }
  }
</style>
