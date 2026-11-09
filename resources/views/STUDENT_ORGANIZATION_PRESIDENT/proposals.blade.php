@extends('layouts.admin')

@section('title','My Proposals')
@section('subtitle','View and manage your submitted student organization activity proposals')

@section('content')
    <style>
        .proposals-header {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:16px;
            flex-wrap:wrap;
            margin-bottom:18px;
        }
        .proposals-header .heading-group {
            min-width:220px;
        }
        .proposals-actions {
            display:flex;
            flex-wrap:wrap;
            gap:12px;
            align-items:center;
        }
        .proposals-actions input,
        .proposals-actions select {
            min-width:220px;
            padding:12px 14px;
            border-radius:14px;
            border:1px solid #d1d5db;
            background:#f8fafc;
            color:#0f172a;
            font-size:0.95rem;
            outline:none;
        }
        .proposals-actions input:focus,
        .proposals-actions select:focus {
            border-color:#3b82f6;
            box-shadow:0 0 0 4px rgba(59,130,246,0.12);
        }
        .proposals-summary {
            padding:20px 22px;
            border-radius:20px;
            background:#f8fafc;
            color:#334155;
            margin-bottom:18px;
        }
        .proposal-table {
            width:100%;
            border-collapse:separate;
            border-spacing:0 10px;
        }
        .proposal-table th,
        .proposal-table td {
            padding:16px 16px;
            vertical-align:middle;
        }
        .proposal-table th {
            text-align:left;
            font-weight:700;
            color:#334155;
            font-size:0.95rem;
        }
        .proposal-table tbody tr {
            background:#ffffff;
            border:1px solid #e5e7eb;
            border-radius:18px;
            box-shadow:0 10px 30px rgba(15, 23, 42, 0.04);
        }
        .proposal-table tbody tr td {
            border:none;
        }
        .proposal-table tbody tr:hover {
            transform:translateY(-1px);
            box-shadow:0 14px 35px rgba(15, 23, 42, 0.08);
        }
        .proposal-title {
            font-weight:700;
            color:#0f172a;
            display:block;
            margin-bottom:4px;
        }
        .proposal-reference {
            color:#64748b;
            font-size:0.92rem;
        }
        .badge {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:8px 12px;
            border-radius:999px;
            font-size:0.87rem;
            font-weight:700;
            text-transform:capitalize;
            white-space:nowrap;
        }
        .badge-submitted { background:#eff6ff; color:#1d4ed8; }
        .badge-celp-review { background:#fef3c7; color:#92400e; }
        .badge-coordinator-review { background:#e0f2fe; color:#0369a1; }
        .badge-approved { background:#dcfce7; color:#166534; }
        .badge-rejected { background:#fee2e2; color:#991b1b; }
        .proposal-footer {
            display:flex;
            justify-content:space-between;
            flex-wrap:wrap;
            gap:12px;
            align-items:center;
            margin-top:18px;
            color:#64748b;
            font-size:0.95rem;
        }
        .proposal-delete {
            padding:8px 11px;
            border:1px solid #fecaca;
            border-radius:9px;
            background:#fff;
            color:#b91c1c;
            font:inherit;
            font-size:0.82rem;
            font-weight:700;
            cursor:pointer;
        }
        .proposal-delete:hover { background:#fef2f2; }
        .no-results {
            padding:24px;
            border-radius:20px;
            background:#f8fafc;
            color:#334155;
        }
        @media (max-width:760px) {
            .proposals-actions { flex-direction:column; }
            .proposal-table th, .proposal-table td { padding:14px 12px; }
        }
    </style>

    <div class="card" style="padding:24px 26px; margin-bottom:20px">
        @if(session('status'))
            <div role="status" style="margin-bottom:16px; padding:12px 15px; border-left:4px solid #16a34a; border-radius:8px; background:#ecfdf5; color:#166534">
                {{ session('status') }}
            </div>
        @endif
        @error('proposal')
            <div role="alert" style="margin-bottom:16px; padding:12px 15px; border-left:4px solid #dc2626; border-radius:8px; background:#fef2f2; color:#991b1b">
                {{ $message }}
            </div>
        @enderror
        <div class="proposals-header">
            <div class="heading-group">
                <h3 style="margin:0">Submitted Proposals</h3>
                <p style="margin:8px 0 0; color:#475569; max-width:720px">Review the status of your activity proposals and open the details for each submission.</p>
            </div>
            <div class="proposals-actions">
                <input id="proposal-search" type="search" placeholder="Search proposals..." aria-label="Search proposals">
                <select id="status-filter" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="submitted">Submitted</option>
                    <option value="celp review">CELP review</option>
                    <option value="coordinator review">Coordinator review</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>

        <div class="proposals-summary">
            Showing <strong>{{ $projects->count() }}</strong> proposal{{ $projects->count() === 1 ? '' : 's' }}. Use search and status filters to narrow results instantly.
        </div>

        @if($projects->isEmpty())
            <div class="no-results">
                No proposals have been submitted yet. Use the dashboard action to create a new proposal.
            </div>
        @else
            <div style="overflow-x:auto">
                <table class="proposal-table" id="proposal-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($projects as $project)
                            @php
                                $statusSlug = str_replace(' ', '-', strtolower(str_replace('_', ' ', $project->status)));
                                $statusLabel = str_replace('_', ' ', $project->status);
                            @endphp
                            <tr data-title="{{ strtolower($project->title) }}" data-status="{{ strtolower($statusLabel) }}">
                                <td>
                                    <span class="proposal-title">{{ $project->title }}</span>
                                    <span class="proposal-reference">Ref: PRP-{{ str_pad($project->id, 4, '0', STR_PAD_LEFT) }}</span>
                                </td>
                                <td>{{ $project->created_at?->format('M d, Y') ?? 'N/A' }}</td>
                                <td><span class="badge badge-{{ $statusSlug }}">{{ ucwords($statusLabel) }}</span></td>
                                <td>{{ $project->updated_at?->format('M d, Y') ?? 'N/A' }}</td>
                                <td>
                                    @if($project->status === 'submitted' || str_starts_with($project->status, 'returned_by_'))
                                        <form method="POST" action="{{ route('student_org.projects.destroy', $project) }}"
                                              data-confirm
                                              data-confirm-title="Delete this proposal?"
                                              data-confirm-body="{name} will be permanently removed along with its attachments. Proposals currently in review or already approved cannot be deleted."
                                              data-confirm-name="{{ $project->title }}"
                                              data-confirm-note="This cannot be undone."
                                              data-confirm-ok="Delete proposal"
                                              data-confirm-cancel="Keep proposal">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="proposal-delete">Delete</button>
                                        </form>
                                    @else
                                        <span style="color:#94a3b8">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="proposal-footer">
                <div id="proposal-count-text">Showing {{ $projects->count() }} of {{ $projects->count() }} proposals</div>
                <div style="color:#94a3b8;">Tip: type a title or status to filter instantly.</div>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('proposal-search');
            const statusFilter = document.getElementById('status-filter');
            const rows = Array.from(document.querySelectorAll('#proposal-table tbody tr'));
            const countText = document.getElementById('proposal-count-text');

            const updateFilter = () => {
                const query = searchInput.value.trim().toLowerCase();
                const status = statusFilter.value.toLowerCase();
                let visible = 0;

                rows.forEach((row) => {
                    const title = row.dataset.title;
                    const rowStatus = row.dataset.status;
                    const matchSearch = !query || title.includes(query) || rowStatus.includes(query);
                    const matchStatus = !status || rowStatus === status;
                    const show = matchSearch && matchStatus;
                    row.style.display = show ? '' : 'none';
                    if (show) visible += 1;
                });

                countText.textContent = `Showing ${visible} of ${rows.length} proposals`;
            };

            searchInput.addEventListener('input', updateFilter);
            statusFilter.addEventListener('change', updateFilter);
        });
    </script>
@endsection