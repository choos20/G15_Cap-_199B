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
        .tpl-source-unknown { background:#f1f5f9; color:#64748b; }
        .tpl-download-btn { color:#0b6a4a; text-decoration:none; font-weight:700; padding:10px 16px;
                            border-radius:10px; background:#ecfdf5; border:1px solid #a7f3d0; }
        .tpl-download-btn:hover { background:#d1fae5; }
        .tpl-empty { padding:48px; text-align:center; color:#64748b; }
    </style>

    <div style="display:flex; align-items:center; gap:14px; margin-bottom:12px; flex-wrap:wrap">
        <span style="background:#f1f5f9; color:#334155; font-weight:700; padding:8px 16px; border-radius:999px; font-size:0.9rem">
            {{ $templates->count() }} of {{ $allCount }} {{ \Illuminate\Support\Str::plural('form', $allCount) }}
        </span>
        <span style="color:#64748b; font-size:0.9rem">
            Fill these out and attach them to your proposal.
        </span>
    </div>

    <nav class="tpl-source-filters" aria-label="Filter forms by office">
        <a class="tpl-source-filter {{ !$source ? 'is-active' : '' }}" href="{{ route('templates.index') }}">
            All offices <span class="tpl-source-count">{{ $allCount }}</span>
        </a>
        @foreach(\App\Models\Template::SOURCES as $key => $label)
            <a class="tpl-source-filter {{ $source === $key ? 'is-active' : '' }}"
               href="{{ route('templates.index', ['source' => $key]) }}">
                {{ $label }} <span class="tpl-source-count">{{ $sourceCounts[$key] ?? 0 }}</span>
            </a>
        @endforeach
        @if(($sourceCounts[''] ?? 0) > 0)
            <a class="tpl-source-filter {{ $source === 'unknown' ? 'is-active' : '' }}"
               href="{{ route('templates.index', ['source' => 'unknown']) }}">
                Source unavailable <span class="tpl-source-count">{{ $sourceCounts[''] }}</span>
            </a>
        @endif
    </nav>

    @if($templates->isEmpty())
        <div class="card tpl-empty">
            <div style="font-size:2rem; margin-bottom:10px">📄</div>
            @if($allCount > 0)
                No forms match this office filter. <a href="{{ route('templates.index') }}" style="color:#0b6a4a; font-weight:700">Show all forms</a>
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
                    <div style="display:flex; align-items:center; gap:14px; flex:1; min-width:260px">
                        <div class="tpl-row-icon">{{ $ext === 'PDF' ? '📄' : '📝' }}</div>
                        <div style="min-width:0">
                            <div style="display:flex; align-items:center; gap:9px; flex-wrap:wrap">
                                <span style="font-weight:700; color:#0f172a">{{ $t->original_name }}</span>
                                <span class="tpl-source-badge {{ $sourceClass }}">{{ $t->sourceLabel() }}</span>
                            </div>
                            <div style="font-size:0.88rem; color:#64748b; margin-top:4px">
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
