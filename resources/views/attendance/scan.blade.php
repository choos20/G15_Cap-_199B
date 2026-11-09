@extends('layouts.admin')

@section('title','Scan Attendance QR')
@section('subtitle','Scan a session QR code and submit your GPS location for attendance verification.')

@section('content')
    <style>
        .scan-page {
            max-width:800px;
            margin:auto;
        }
        .scan-card {
            background:#ffffff;
            border:1px solid #e5e7eb;
            border-radius:28px;
            box-shadow:0 24px 52px rgba(15,23,42,0.08);
            padding:32px;
        }
        .scan-card h3 {
            margin-top:0;
            color:#0f172a;
        }
        .scan-hint {
            margin:16px 0 24px;
            color:#475569;
            line-height:1.7;
        }
        .scan-status {
            padding:18px 20px;
            border-radius:18px;
            background:#f8fafc;
            border:1px solid #e2e8f0;
            font-size:0.96rem;
        }
        .scan-status.success {
            border-color:#d1fae5;
            background:#ecfdf4;
            color:#164e2f;
        }
        .scan-status.error {
            border-color:#fecaca;
            background:#fef2f2;
            color:#991b1b;
        }
        .scan-actions {
            margin-top:22px;
            display:flex;
            gap:12px;
            flex-wrap:wrap;
        }
        .scan-actions button {
            border:none;
            border-radius:14px;
            padding:14px 20px;
            font-weight:700;
            background:#0fbf91;
            color:#ffffff;
            cursor:pointer;
        }
        .scan-data {
            margin-top:22px;
            color:#475569;
            font-size:0.95rem;
            display:grid;
            gap:10px;
        }
    </style>

    <div class="scan-page">
        <div class="scan-card">
            <h3>Attendance scan</h3>
            <p class="scan-hint">Choose check-in or check-out. Both scans use your account, a short-lived one-use challenge, the session window, and your device’s reported GPS location. Location is a helpful signal, but browser GPS is not tamper-proof.</p>
            @if($attendanceRecord && in_array($attendanceRecord->attendance_source, \App\Models\AttendanceRecord::CSV_SOURCES, true))
                <div class="scan-status">The organizer has already recorded your attendance for this session. Use My Attendance to view the record.</div>
            @elseif($attendanceRecord && $canCheckOut)
                <div class="scan-status success">Your check-in is recorded. Scan again to check out.</div>
            @elseif($attendanceRecord && ! $canCheckIn)
                <div class="scan-status">Your attendance for this session is already complete.</div>
            @endif

            <div class="scan-status" id="scan-message">Ready to scan.</div>
            <div class="scan-data">
                <div><strong>Checking in as:</strong> {{ auth()->user()->name }} ({{ auth()->user()->email }})</div>
                <div><strong>Session:</strong> {{ $session->project->title }}</div>
                <div><strong>Status:</strong> {{ ucfirst($session->status) }}</div>
                <div><strong>Open until:</strong> {{ $session->closes_at->format('M d, Y H:i') }}</div>
                <div><strong>Allowed radius:</strong> {{ $session->radius_meters }} meters</div>
            </div>

            <div class="scan-actions">
                <button id="start-check-in" type="button" @disabled(!$canCheckIn)>Check in</button>
                <button id="start-check-out" type="button" @disabled(!$canCheckOut)>Check out</button>
                <button id="refresh-geolocation" type="button" style="background:#3b82f6">Refresh coordinates</button>
            </div>

            <div class="scan-data" id="scan-data" style="margin-top:12px"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const scanMessage = document.getElementById('scan-message');
            const scanData = document.getElementById('scan-data');
            const checkInButton = document.getElementById('start-check-in');
            const checkOutButton = document.getElementById('start-check-out');
            const refreshGeo = document.getElementById('refresh-geolocation');
            let currentCoords = null;
            let scanChallenge = @json($scanChallenge);
            let canCheckIn = @json($canCheckIn);
            let canCheckOut = @json($canCheckOut);

            const updateMessage = (text, status = 'info') => {
                scanMessage.textContent = text;
                scanMessage.className = 'scan-status ' + (status === 'success' ? 'success' : status === 'error' ? 'error' : '');
            };

            const updateData = (coords) => {
                if (!coords) {
                    scanData.innerHTML = '<div>Current coordinates not available.</div>';
                    return;
                }
                scanData.innerHTML = `<div><strong>Latitude:</strong> ${coords.latitude}</div><div><strong>Longitude:</strong> ${coords.longitude}</div><div><strong>Accuracy:</strong> ${coords.accuracy} meters</div>`;
            };

            const getLocation = () => {
                if (!navigator.geolocation) {
                    updateMessage('Geolocation is not supported by your browser.', 'error');
                    return;
                }

                updateMessage('Getting current location...');
                navigator.geolocation.getCurrentPosition((position) => {
                    currentCoords = position.coords;
                    updateData(currentCoords);
                    updateMessage('Location ready. Tap Scan to submit.', 'success');
                }, (error) => {
                    updateMessage('Unable to fetch your location. Make sure location access is allowed and refresh the page.', 'error');
                }, {
                    enableHighAccuracy: true,
                    maximumAge: 10000,
                    timeout: 20000,
                });
            };

            const sendScan = (action) => {
                if (!currentCoords) {
                    updateMessage('Please allow location access before scanning.', 'error');
                    return;
                }

                updateMessage('Submitting attendance...');
                checkInButton.disabled = true;
                checkOutButton.disabled = true;
                fetch(`{{ route('attendance.scan.submit', ['token' => $session->qr_token]) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        action,
                        scan_challenge: scanChallenge,
                        latitude: currentCoords.latitude,
                        longitude: currentCoords.longitude,
                    }),
                })
                .then(async (response) => {
                    const data = await response.json().catch(() => null);
                    if (!response.ok || !data) {
                        throw new Error(data?.message || 'Invalid server response');
                    }
                    return data;
                })
                .then((data) => {
                    scanChallenge = data.scan_challenge;
                    canCheckIn = data.can_check_in;
                    canCheckOut = data.can_check_out;
                    checkInButton.disabled = !canCheckIn;
                    checkOutButton.disabled = !canCheckOut;
                    if (data.status === 'valid' || data.status === 'completed') {
                        updateMessage(data.message, 'success');
                    } else {
                        updateMessage(data.message, 'error');
                    }
                    if (data.distance_meters !== undefined) {
                        scanData.innerHTML = `<div><strong>Distance:</strong> ${data.distance_meters} meters</div>`;
                    }
                })
                .catch((error) => {
                    checkInButton.disabled = !canCheckIn;
                    checkOutButton.disabled = !canCheckOut;
                    updateMessage(error.message || 'Could not submit the scan. Reload this page to get a fresh scan challenge.', 'error');
                });
            };

            checkInButton.addEventListener('click', () => sendScan('check_in'));
            checkOutButton.addEventListener('click', () => sendScan('check_out'));
            refreshGeo.addEventListener('click', getLocation);
            getLocation();
        });
    </script>
@endsection
