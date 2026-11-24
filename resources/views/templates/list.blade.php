@extends('layouts.admin')

@section('title', 'Forms & Requirements')
@section('subtitle','Download the official forms you need from CELP and CSA')

@section('content')
    {{--
        Read-only twin of templates/manage. Same rows, same uploader and date,
        but no upload card and no Remove or Replace controls — those endpoints
        are closed to this audience by middleware, so offering the buttons here
        would only produce a 403.
    --}}
    <style>
        .tpl-row { display:flex; justify-content:space-between; align-items:center; gap:16px; padding:18px 20px;
                   border-radius:16px; border:1px solid #e5e7eb; background:#fff; flex-wrap:wrap; }
        .tpl-row-icon { width:42px; height:42px; border-radius:12px; display:flex; align-items:center;
                        justify-content:center; font-size:1.15rem; color:#1e3a8a; flex-shrink:0; background:#eff6ff; }
        .tpl-source-filters { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 16px; }
        .tpl-source-filter { display:inline-flex; align-items:center; gap:7px; padding:8px 13px; border:1px solid #dfe7e5;
                             border-radius:999px; background:#fff; color:#475569; font-size:0.85rem; font-weight:700; text-decoration:none; }
        .tpl-source-filter:hover { border-color:#9bd8bd; background:#f5fbf7; }
        .tpl-source-filter.is-active { border-color:#0b6a4a; background:#0b6a4a; color:#fff; }
        .tpl-source-count { font-size:0.75rem; opacity:0.75; }
        .tpl-source-badge { display:inline-flex; align-items:center; padding:3px 8px; border-radius:999px; font-size:0.72rem; font-weight:800; letter-spacing:0.03em; }
        .tpl-source-celp { background:#eaf3ff; color:#2456a6; }
        .tpl-source-csa { background:#e7f8ef; color:#08734e; }
        .tpl-source-unknown { background:#f1f5f9; color:#52665d; }
        .tpl-download-btn { color:#0b6a4a; text-decoration:none; font-weight:700; padding:10px 16px;
                            border-radius:10px; background:#ecfdf5; border:1px solid #a7f3d0; }
        .tpl-download-btn:hover { background:#d1fae5; }
        .tpl-empty { padding:48px; text-align:center; color:#52665d; }
        .tpl-toolbar { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
        .tpl-search, .tpl-office { display:flex; align-items:center; gap:9px; background:#fff; border:1px solid #e2e8f0; border-radius:999px; padding:10px 14px; color:#52665d; }
        .tpl-search { flex:1 1 280px; min-width:0; }
        .tpl-search input, .tpl-office select { border:0; outline:0; background:transparent; color:#334155; font:inherit; min-width:0; }
        .tpl-search input { width:100%; }
        .tpl-office select { cursor:pointer; }
        .tpl-submit { border:0; border-radius:999px; padding:10px 18px; background:#0b6a4a; color:#fff; font:inherit; font-weight:700; cursor:pointer; }
        .tpl-search:focus-within, .tpl-office:focus-within, .tpl-submit:focus-visible { outline:2px solid #0b6a4a; outline-offset:2px; }
    </style>

    <div style="display:flex; align-items:center; gap:14px; margin-bottom:12px; flex-wrap:wrap">
        <span style="background:#f1f5f9; color:#334155; font-weight:700; padding:8px 16px; border-radius:999px; font-size:0.9rem">
            {{ $templates->count() }} of {{ $allCount }} {{ \Illuminate\Support\Str::plural('form', $allCount) }}
        </span>
        <span style="color:#52665d; font-size:0.9rem">
            Fill these out and attach them to your proposal.
        </span>
    </div>

    <form class="tpl-toolbar" method="GET" action="{{ route('templates.index') }}">
        <label class="tpl-search">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="m16 16 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <input type="search" name="q" value="{{ $term }}" placeholder="Search forms or uploader..." aria-label="Search forms or uploader">
        </label>
        <label class="tpl-office">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5h16l-6.2 7.1v5.2l-3.6 1.8v-7L4 5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <select name="source" aria-label="Filter forms by office" onchange="this.form.requestSubmit()">
                <option value="">All offices</option>
                @foreach(\App\Models\Template::SOURCES as $key => $label)
                    <option value="{{ $key }}" @selected($source === $key)>{{ $label }}</option>
                @endforeach
                @if(($sourceCounts[''] ?? 0) > 0)
                    <option value="unknown" @selected($source === 'unknown')>Source unavailable</option>
                @endif
            </select>
        </label>
        <button class="tpl-submit" type="submit">Search</button>
    </form>

    @if($templates->isEmpty())
        <div class="card tpl-empty">
            <div style="font-size:2rem; margin-bottom:10px">📄</div>
            @if($allCount > 0)
                No forms match your search or office filter. <a href="{{ route('templates.index') }}" style="color:#0b6a4a; font-weight:700">Show all forms</a>
            @else
                No forms have been published yet.
            @endif
        </div>
    @else
        <div style="display:grid; gap:12px">
            @foreach($templates as $t)
                @php
                    $ext = strtoupper(pathinfo($t->original_name, PATHINFO_EXTENSION));
                    $sourceClass = $t->source ? 'tpl-source-' . $t->source : 'tpl-source-unknown';
                @endphp
                <div class="tpl-row">
                    <div style="display:flex; align-items:center; gap:14px; flex:1; min-width:min(260px,100%)">
                        <div class="tpl-row-icon">{{ $ext === 'PDF' ? '📄' : '📝' }}</div>
                        <div style="min-width:0">
                            <div style="display:flex; align-items:center; gap:9px; flex-wrap:wrap">
                                <span style="font-weight:700; color:#0f172a">{{ $t->original_name }}</span>
                                <span class="tpl-source-badge {{ $sourceClass }}">{{ $t->sourceLabel() }}</span>
                            </div>
                            <div style="font-size:0.88rem; color:#52665d; margin-top:4px">
                                Uploaded by {{ $t->uploader->name ?? '—' }} &middot; {{ $t->created_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('templates.download', $t->id) }}" class="tpl-download-btn">Download</a>
                </div>
            @endforeach
        </div>
    @endif
@endsection
