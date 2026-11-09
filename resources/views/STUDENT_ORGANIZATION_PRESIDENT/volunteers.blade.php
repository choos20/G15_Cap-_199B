@extends('layouts.admin')

@section('title', 'Volunteer Management')
@section('subtitle', 'Review applications and manage slots for your volunteer recruitment posts')

@section('content')
    @if(session('status'))
        <div class="card" style="border-left:4px solid #16a34a; background:#ecfdf5; color:#134e4a; padding:16px 20px; margin-bottom:20px">
            {{ session('status') }}
        </div>
    @endif

    @if($announcements->isEmpty())
        <div class="card" style="padding:40px; text-align:center; color:#64748b">
            <div style="font-size:2rem; margin-bottom:10px">📢</div>
            You haven't posted a volunteer recruitment announcement yet.
            <div style="margin-top:14px"><a href="{{ route('student_org.announcements') }}" style="color:#2563eb; text-decoration:none; font-weight:700;">Create an announcement</a></div>
        </div>
    @else
        <div style="display:grid; gap:14px">
            @foreach($announcements as $announcement)
                @php $remaining = $announcement->remainingSlots(); @endphp
                <div class="card" style="display:flex; justify-content:space-between; align-items:center; gap:16px; padding:20px 22px; flex-wrap:wrap">
                    <div>
                        <div style="font-weight:700; color:#0f172a; font-size:1.02rem">{{ $announcement->title }}</div>
                        <div style="font-size:0.88rem; color:#64748b; margin-top:6px">
                            {{ $announcement->project->title ?? '—' }}
                            · {{ $remaining }} of {{ $announcement->slots_needed }} slots remaining
                        </div>
                        <div style="margin-top:8px; display:flex; gap:10px; flex-wrap:wrap">
                            @if($announcement->pending_count > 0)
                                <span style="font-size:0.78rem; font-weight:700; padding:4px 10px; border-radius:999px; background:#fffbeb; color:#92400e">{{ $announcement->pending_count }} pending</span>
                            @endif
                            <span style="font-size:0.78rem; font-weight:700; padding:4px 10px; border-radius:999px; background:#ecfdf5; color:#065f46">{{ $announcement->approved_count }} approved</span>
                        </div>
                    </div>
                    <a href="{{ route('student_org.volunteers.show', $announcement) }}" style="flex-shrink:0; display:inline-flex; align-items:center; gap:6px; padding:12px 20px; border-radius:12px; background:#0b6a4a; color:#fff; text-decoration:none; font-weight:700">
                        Review applicants
                    </a>
                </div>
            @endforeach
        </div>
    @endif
@endsection
