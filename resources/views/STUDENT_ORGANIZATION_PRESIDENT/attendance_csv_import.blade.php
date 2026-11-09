@extends('layouts.admin')

@section('title', 'Upload Attendance CSV')
@section('subtitle', $attendanceSession->project->title.' · Session #'.$attendanceSession->id)

@section('content')
    <style>
        .attendance-import-shell { max-width:1060px; margin:0 auto; }
        .attendance-import-back { display:inline-flex; align-items:center; gap:8px; margin-bottom:18px; color:#087c5b; font-weight:750; text-decoration:none; }
        .attendance-import-back:hover { color:#065f46; }
        .attendance-import-card { overflow:hidden; background:#fff; border:1px solid #e4ebe7; border-radius:24px; box-shadow:0 22px 55px rgba(15,53,39,.09); }
        .attendance-import-hero { position:relative; overflow:hidden; padding:32px 36px 30px; background:radial-gradient(circle at 90% 15%,rgba(255,255,255,.2),transparent 25%),linear-gradient(125deg,#075e49 0%,#087c5b 52%,#0a9b70 100%); color:#fff; }
        .attendance-import-hero::after { position:absolute; right:-64px; bottom:-150px; width:340px; height:340px; border:1px solid rgba(255,255,255,.12); border-radius:50%; content:""; box-shadow:0 0 0 34px rgba(255,255,255,.035),0 0 0 70px rgba(255,255,255,.025); }
        .attendance-import-eyebrow { display:inline-flex; align-items:center; gap:7px; padding:6px 10px; border:1px solid rgba(255,255,255,.25); border-radius:999px; background:rgba(255,255,255,.12); color:#e4fff5; font-size:.76rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
        .attendance-import-hero h1 { position:relative; z-index:1; margin:15px 0 8px; font-size:clamp(1.55rem,3vw,2.1rem); line-height:1.15; letter-spacing:-.03em; }
        .attendance-import-hero p { position:relative; z-index:1; max-width:690px; margin:0; color:rgba(255,255,255,.82); font-size:.98rem; line-height:1.6; }
        .attendance-import-session { position:relative; z-index:1; display:flex; flex-wrap:wrap; gap:8px; margin-top:18px; }
        .attendance-import-session span { padding:7px 10px; border:1px solid rgba(255,255,255,.2); border-radius:9px; background:rgba(0,0,0,.12); color:#fff; font-size:.8rem; font-weight:650; }
        .attendance-import-body { padding:30px 36px 34px; }
        .attendance-import-section-title { margin:0; color:#12251e; font-size:1.08rem; letter-spacing:-.01em; }
        .attendance-import-muted { color:#687b73; line-height:1.55; }
        .attendance-import-notice { display:flex; gap:12px; align-items:flex-start; margin:20px 0 24px; padding:14px 16px; border:1px solid #c8e8d8; border-radius:14px; background:linear-gradient(110deg,#f0fbf5,#f7fcf8); color:#315f49; font-size:.9rem; line-height:1.5; }
        .attendance-import-notice-icon { display:grid; place-items:center; width:30px; height:30px; flex:0 0 30px; border-radius:10px; background:#d9f2e3; color:#087c5b; }
        .attendance-import-notice strong { color:#075e49; }
        .attendance-import-steps { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin:0 0 27px; padding:0; list-style:none; counter-reset:attendance-step; }
        .attendance-import-step { min-width:0; padding:15px 14px; border:1px solid #e6ede9; border-radius:14px; background:#fbfdfc; counter-increment:attendance-step; }
        .attendance-import-step::before { display:grid; place-items:center; width:27px; height:27px; margin-bottom:10px; border-radius:9px; background:#e4f4eb; color:#087c5b; content:counter(attendance-step); font-size:.8rem; font-weight:850; }
        .attendance-import-step strong { display:block; margin-bottom:4px; color:#18372a; font-size:.86rem; }
        .attendance-import-step span { display:block; color:#718079; font-size:.78rem; line-height:1.45; }
        .attendance-import-downloads { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-bottom:25px; }
        .attendance-import-download { display:flex; align-items:center; gap:13px; min-width:0; padding:15px 16px; border:1px solid #e4ebe7; border-radius:14px; background:#fff; color:#17382b; text-decoration:none; transition:border-color .16s ease,transform .16s ease,box-shadow .16s ease; }
        .attendance-import-download:hover { transform:translateY(-2px); border-color:#9bd1b4; box-shadow:0 10px 24px rgba(15,53,39,.08); }
        .attendance-import-download-icon { display:grid; place-items:center; width:42px; height:42px; flex:0 0 42px; border-radius:12px; background:#e8f7ed; color:#087c5b; }
        .attendance-import-download:nth-child(2) .attendance-import-download-icon { background:#fff3e8; color:#bd6b16; }
        .attendance-import-download-copy { min-width:0; }
        .attendance-import-download-copy strong { display:block; margin-bottom:3px; font-size:.9rem; }
        .attendance-import-download-copy span { display:block; color:#718079; font-size:.78rem; line-height:1.4; }
        .attendance-import-chevron { margin-left:auto; color:#8ca096; font-size:1.15rem; }
        .attendance-import-upload { padding:21px; border:1px solid #e5ece8; border-radius:17px; background:linear-gradient(145deg,#fbfdfc,#f7faf8); }
        .attendance-import-upload-head { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; margin-bottom:15px; }
        .attendance-import-upload-head p { margin:5px 0 0; color:#718079; font-size:.85rem; line-height:1.5; }
        .attendance-import-limit { flex:0 0 auto; padding:6px 9px; border-radius:8px; background:#edf3ef; color:#607168; font-size:.73rem; font-weight:750; }
        .attendance-import-drop { display:grid; justify-items:center; padding:25px 18px; border:1.5px dashed #9ab6a7; border-radius:14px; background:#fff; text-align:center; cursor:pointer; transition:border-color .16s ease,background .16s ease,box-shadow .16s ease; }
        .attendance-import-drop:hover,.attendance-import-drop.is-dragging { border-color:#0b8f70; background:#f4fcf7; box-shadow:inset 0 0 0 3px rgba(11,143,112,.06); }
        .attendance-import-drop-icon { display:grid; place-items:center; width:48px; height:48px; margin-bottom:11px; border-radius:15px; background:#e6f6ec; color:#087c5b; }
        .attendance-import-drop strong { color:#19372a; font-size:.93rem; }
        .attendance-import-drop span { margin-top:5px; color:#7a8982; font-size:.81rem; }
        .attendance-import-file { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; clip-path:inset(50%); }
        .attendance-import-file:focus-visible + .attendance-import-drop { outline:3px solid rgba(11,143,112,.35); outline-offset:3px; }
        .attendance-import-selected { display:none; align-items:center; gap:10px; margin-top:12px; padding:11px 12px; border:1px solid #cde7d7; border-radius:11px; background:#f0fbf4; color:#285c40; font-size:.84rem; }
        .attendance-import-selected.is-visible { display:flex; }
        .attendance-import-selected-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:750; }
        .attendance-import-selected-size { flex:0 0 auto; margin-left:auto; color:#668174; font-size:.78rem; }
        .attendance-import-errors { margin:0 0 16px; padding:13px 15px; border:1px solid #f3c6c2; border-radius:12px; background:#fff6f5; color:#a83228; font-size:.88rem; }
        .attendance-import-errors ul { margin:6px 0 0; padding-left:20px; }
        .attendance-import-footer { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-top:16px; }
        .attendance-import-footer-note { color:#76857e; font-size:.78rem; line-height:1.45; }
        .attendance-import-actions { display:flex; flex-wrap:wrap; gap:9px; }
        .attendance-import-button { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:43px; padding:10px 15px; border:1px solid #d5e0da; border-radius:11px; background:#fff; color:#365448; font-size:.88rem; font-weight:750; text-decoration:none; transition:transform .16s ease,box-shadow .16s ease,background .16s ease; }
        .attendance-import-button:hover { transform:translateY(-1px); background:#f7fbf8; box-shadow:0 5px 12px rgba(15,53,39,.08); }
        .attendance-import-submit { border-color:#087c5b; background:linear-gradient(110deg,#087c5b,#0b9870); color:#fff; cursor:pointer; box-shadow:0 7px 15px rgba(8,124,91,.18); }
        .attendance-import-submit:hover { background:linear-gradient(110deg,#066b4e,#087c5b); }
        @media(max-width:760px) { .attendance-import-hero { padding:27px 24px; } .attendance-import-body { padding:24px; } .attendance-import-steps { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media(max-width:560px) { .attendance-import-hero { padding:24px 19px; } .attendance-import-body { padding:19px; } .attendance-import-downloads { grid-template-columns:1fr; } .attendance-import-upload { padding:15px; } .attendance-import-upload-head,.attendance-import-footer { flex-direction:column; align-items:stretch; } .attendance-import-limit { align-self:flex-start; } .attendance-import-actions { display:grid; grid-template-columns:1fr; } .attendance-import-button { width:100%; } }
    </style>

    <div class="attendance-import-shell">
        <a class="attendance-import-back" href="{{ route('student_org.attendance') }}">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 12H5m0 0 7-7m-7 7 7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Attendance sessions
        </a>

        <section class="attendance-import-card">
            <header class="attendance-import-hero">
                <span class="attendance-import-eyebrow">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.5 19 6v5.5c0 4.4-2.8 7.4-7 9-4.2-1.6-7-4.6-7-9V6l7-2.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Attendance backup
                </span>
                <h1>Record attendance, your way.</h1>
                <p>Use the CSV roster when QR check-in is unavailable. Mark your volunteers in Excel, upload the file online, and review every match before saving.</p>
                <div class="attendance-import-session">
                    <span>{{ $attendanceSession->project->title }}</span>
                    <span>Session #{{ $attendanceSession->id }}</span>
                    <span>Online verification</span>
                </div>
            </header>

            <div class="attendance-import-body">
                <div class="attendance-import-notice">
                    <span class="attendance-import-notice-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 8v4m0 4h.01M10.3 3.8 2.9 17a2 2 0 0 0 1.7 3h14.8a2 2 0 0 0 1.7-3l-7.4-13.2a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span><strong>Internet connection required for upload.</strong> ProjectLink checks your CSV against this activity's approved volunteer roster. Nothing is saved until you review the results and confirm.</span>
                </div>

                <h2 class="attendance-import-section-title">How it works</h2>
                <ol class="attendance-import-steps" aria-label="Attendance CSV steps" style="margin-top:13px;">
                    <li class="attendance-import-step"><strong>Get your roster</strong><span>Download the CSV and open it in Excel.</span></li>
                    <li class="attendance-import-step"><strong>Mark attendance</strong><span>Enter Present or Absent for check-in and check-out.</span></li>
                    <li class="attendance-import-step"><strong>Upload online</strong><span>Save as .csv and upload it here.</span></li>
                    <li class="attendance-import-step"><strong>Review &amp; save</strong><span>Check matches, then confirm to record.</span></li>
                </ol>

                <h2 class="attendance-import-section-title" style="margin-bottom:12px;">Prepare your attendance</h2>
                <div class="attendance-import-downloads">
                    <a class="attendance-import-download" href="{{ route('student_org.attendance.csv-template', $attendanceSession) }}">
                        <span class="attendance-import-download-icon" aria-hidden="true">
                            <svg width="21" height="21" viewBox="0 0 24 24" fill="none"><path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 3.75v4h4M8 12h8m-8 3.5h8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                        </span>
                        <span class="attendance-import-download-copy"><strong>Attendance CSV</strong><span>Excel-ready roster with check-in and check-out columns</span></span>
                        <span class="attendance-import-chevron" aria-hidden="true">›</span>
                    </a>
                    <a class="attendance-import-download" href="{{ route('student_org.attendance.signature-sheet', $attendanceSession) }}">
                        <span class="attendance-import-download-icon" aria-hidden="true">
                            <svg width="21" height="21" viewBox="0 0 24 24" fill="none"><path d="M5 19.25c2.8-2.1 4.7-2.1 6.6 0 1.8-2.1 3.8-2.1 7.4 0M5 5.75h14M5 10h14M5 14.25h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M5 3.75h14a1 1 0 0 1 1 1v14.5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4.75a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.5"/></svg>
                        </span>
                        <span class="attendance-import-download-copy"><strong>Signature sheet <span style="font-weight:550;color:#718079;">· DOCX</span></strong><span>Printable volunteer name and specimen-signature list</span></span>
                        <span class="attendance-import-chevron" aria-hidden="true">›</span>
                    </a>
                </div>

                @if($errors->any())
                    <div class="attendance-import-errors" role="alert">
                        <strong>The CSV could not be uploaded:</strong>
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="post" action="{{ route('student_org.attendance.preview', $attendanceSession) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="attendance-import-upload">
                        <div class="attendance-import-upload-head">
                            <div>
                                <h2 class="attendance-import-section-title">Upload completed CSV</h2>
                                <p>Choose the CSV you filled in Excel. The server will check its headers, activity, and participant IDs.</p>
                            </div>
                            <span class="attendance-import-limit">CSV · MAX 5 MB</span>
                        </div>
                        <input class="attendance-import-file" id="attendance_file" name="attendance_file" type="file" accept=".csv,text/csv" required aria-describedby="attendance-file-help">
                        <label class="attendance-import-drop" id="attendance-drop" for="attendance_file" tabindex="0">
                            <span class="attendance-import-drop-icon" aria-hidden="true">
                                <svg width="23" height="23" viewBox="0 0 24 24" fill="none"><path d="M12 15V4m0 0L8 8m4-4 4 4M5 13.5v4.75A1.75 1.75 0 0 0 6.75 20h10.5A1.75 1.75 0 0 0 19 18.25V13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <strong id="attendance-drop-title">Drop your CSV here or <span style="color:#087c5b;text-decoration:underline;text-underline-offset:3px;">browse files</span></strong>
                            <span id="attendance-drop-subtitle">Only .csv files are accepted</span>
                        </label>
                        <div class="attendance-import-selected" id="attendance-selected" aria-live="polite">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m9 14 2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span class="attendance-import-selected-name" id="attendance-file-name"></span>
                            <span class="attendance-import-selected-size" id="attendance-file-size"></span>
                        </div>
                        <div class="attendance-import-footer">
                            <span class="attendance-import-footer-note" id="attendance-file-help">Keep the activity details and participant IDs unchanged. Review the server results before confirming attendance.</span>
                            <div class="attendance-import-actions" style="margin:0;">
                                <a class="attendance-import-button" href="{{ route('student_org.attendance') }}">Cancel</a>
                                <button class="attendance-import-button attendance-import-submit" id="attendance-submit" type="submit" disabled>
                                    Upload &amp; review
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <script>
        (() => {
            const input = document.getElementById('attendance_file');
            const drop = document.getElementById('attendance-drop');
            const submit = document.getElementById('attendance-submit');
            const selected = document.getElementById('attendance-selected');
            const fileName = document.getElementById('attendance-file-name');
            const fileSize = document.getElementById('attendance-file-size');
            const title = document.getElementById('attendance-drop-title');
            const subtitle = document.getElementById('attendance-drop-subtitle');

            const showFile = (file) => {
                if (!file) {
                    selected.classList.remove('is-visible');
                    submit.disabled = true;
                    title.textContent = 'Drop your CSV here or browse files';
                    subtitle.textContent = 'Only .csv files are accepted';
                    return;
                }

                fileName.textContent = file.name;
                fileSize.textContent = `${(file.size / 1024).toFixed(1)} KB`;
                selected.classList.add('is-visible');
                submit.disabled = false;
                title.textContent = 'CSV ready to upload';
                subtitle.textContent = 'Click to choose a different file';
            };

            input.addEventListener('change', () => showFile(input.files[0] ?? null));
            ['dragenter', 'dragover'].forEach((eventName) => drop.addEventListener(eventName, (event) => {
                event.preventDefault();
                drop.classList.add('is-dragging');
            }));
            ['dragleave', 'drop'].forEach((eventName) => drop.addEventListener(eventName, (event) => {
                event.preventDefault();
                drop.classList.remove('is-dragging');
            }));
            drop.addEventListener('drop', (event) => {
                const file = event.dataTransfer?.files?.[0];
                if (!file) return;
                const transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
                showFile(file);
            });
            drop.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    input.click();
                }
            });
        })();
    </script>
@endsection
