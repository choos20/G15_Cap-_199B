<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name', 'ProjectLink'))</title>
    <link rel="icon" type="image/png" href="{{ asset('images/Logo_nobg.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/password-policy.css') }}?v={{ substr(md5_file(public_path('css/password-policy.css')), 0, 12) }}">

    <style>
        :root {
            --font-sans: 'Segoe UI', 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            --bg-start: #8de9bd;
            --bg-mid: #f5f5f5;
            --bg-end: #27a96b;
            --panel: rgba(8, 74, 46, 0.42);
            --panel-strong: rgba(255, 255, 255, 0.28);
            --border-panel: rgba(235, 255, 241, 0.34);
            --text-light: #edf7ef;
            --text-muted: rgba(238, 247, 239, 0.82);
            --text-dark: #10331e;
            --button-start: #2ca56f;
            --button-end: #17824f;
            --error: #dc2626;
            --error-bg: #fef2f2;
            --radius: 24px;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            min-height: 100%;
        }

        body {
            position: relative;
            isolation: isolate;
            overflow-x: hidden;
            min-height: 100vh;
            min-height: 100dvh;
            font-family: var(--font-sans);
            background: #073b25 url('{{ asset('images/projectlink-campus-bg.png') }}') center / cover no-repeat fixed;
            color: var(--text-light);
            font-size: 17px;
            line-height: 1.55;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0 24px;
        }

        body::before {
            position: fixed;
            inset: 0;
            z-index: -2;
            background:
                linear-gradient(120deg, rgba(2, 42, 25, 0.68) 0%, rgba(5, 93, 53, 0.52) 46%, rgba(19, 145, 83, 0.34) 100%),
                radial-gradient(circle at 15% 12%, rgba(166, 255, 190, 0.24), transparent 32%),
                radial-gradient(circle at 90% 92%, rgba(17, 255, 148, 0.22), transparent 28%);
            content: '';
            pointer-events: none;
        }

        body::after {
            position: fixed;
            inset: 0;
            z-index: -1;
            background: linear-gradient(180deg, rgba(3, 42, 26, 0.08), rgba(2, 32, 20, 0.22));
            content: '';
            pointer-events: none;
        }

        .page-wrapper {
            width: 100%;
            max-width: 520px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            padding: 32px 0;
        }

        .page-card {
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(145deg, rgba(48, 143, 94, 0.3), rgba(5, 59, 37, 0.22)) padding-box,
                linear-gradient(135deg, rgba(225, 255, 233, 0.62), rgba(87, 220, 143, 0.38) 48%, rgba(255, 255, 255, 0.16)) border-box;
            border: 1px solid transparent;
            -webkit-backdrop-filter: blur(20px) saturate(145%);
            backdrop-filter: blur(20px) saturate(145%);
            border-radius: 26px;
            box-shadow: 0 28px 80px rgba(2, 27, 16, 0.42), inset 0 1px 0 rgba(255, 255, 255, 0.16);
            width: 100%;
            padding: 34px 40px 28px;
            transition: transform 0.22s ease, box-shadow 0.22s ease;
        }

        .page-card::before {
            position: absolute;
            top: -90px;
            right: -80px;
            width: 220px;
            height: 220px;
            border: 1px solid rgba(196, 255, 211, 0.2);
            border-radius: 50%;
            box-shadow: 0 0 0 32px rgba(190, 255, 207, 0.035), 0 0 0 64px rgba(190, 255, 207, 0.025);
            content: '';
            pointer-events: none;
        }

        .page-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 34px 88px rgba(2, 27, 16, 0.48), inset 0 1px 0 rgba(255, 255, 255, 0.18);
        }

        .brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 20px;
        }

        .brand img {
            width: 88px;
            height: 88px;
            object-fit: contain;
            margin-bottom: 8px;
            filter: drop-shadow(0 8px 14px rgba(7, 31, 17, 0.35));
        }

        .brand-name {
            position: relative;
            z-index: 1;
            font-family: 'Space Grotesk', var(--font-sans);
            font-size: 1.9rem;
            font-weight: 700;
            letter-spacing: -0.04em;
            color: var(--text-light);
            text-shadow: 0 3px 14px rgba(2, 36, 19, 0.28);
        }

        .brand-sub {
            font-size: 0.9rem;
            color: rgba(241, 249, 241, 0.86);
            margin-top: 5px;
            line-height: 1.5;
        }

        .section-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--text-light);
            margin: 0 0 5px;
            letter-spacing: -0.025em;
        }

        .section-subtitle {
            font-size: 0.95rem;
            color: rgba(238, 247, 239, 0.82);
            margin: 0 0 20px;
        }

        label {
            display: block;
            text-align: left;
            font-size: 0.82rem;
            font-weight: 650;
            margin: 15px 0 6px;
            color: #f1fbf3;
        }

        input,
        button {
            font-family: var(--font-sans);
        }

        input {
            width: 100%;
            min-height: 52px;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.72);
            border-radius: 14px;
            color: var(--text-dark);
            font-size: 0.95rem;
            box-shadow: 0 7px 18px rgba(3, 42, 24, 0.1);
            transition: border-color 0.16s ease, box-shadow 0.16s ease, background 0.16s ease, transform 0.16s ease;
        }

        input:focus {
            outline: none;
            border-color: #b8ffd0;
            box-shadow: 0 0 0 4px rgba(111, 255, 157, 0.2), 0 10px 22px rgba(3, 42, 24, 0.16);
            background: #ffffff;
            transform: translateY(-1px);
        }

        input.is-invalid {
            border-color: var(--error);
        }

        .field-error {
            font-size: 0.72rem;
            color: var(--error);
            margin-top: 5px;
        }

        button {
            width: 100%;
            min-height: 54px;
            margin-top: 25px;
            padding: 14px 16px;
            background: linear-gradient(135deg, #35d27e 0%, #08a65f 52%, #087e4e 100%);
            border: none;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            color: #ffffff;
            cursor: pointer;
            box-shadow: 0 10px 22px rgba(0, 145, 77, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.24);
            transition: filter 0.16s ease, transform 0.16s ease, box-shadow 0.16s ease;
        }

        button:hover {
            filter: brightness(1.05);
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(0, 145, 77, 0.34), inset 0 1px 0 rgba(255, 255, 255, 0.28);
        }

        .alert-error {
            margin-bottom: 18px;
            background: var(--error-bg);
            border: 1px solid #fca5a5;
            padding: 11px 14px;
            border-radius: 8px;
            font-size: 0.78rem;
            color: var(--error);
        }

        .alert-success {
            margin-bottom: 18px;
            background: rgba(214, 255, 227, 0.85);
            border: 1px solid #8be4a2;
            padding: 11px 14px;
            border-radius: 8px;
            font-size: 0.78rem;
            color: #166a3f;
        }

        .otp-timer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 12px;
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--border-panel);
            border-radius: 10px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .otp-timer strong {
            font-size: 1rem;
            font-variant-numeric: tabular-nums;
            color: var(--text-light);
        }

        .otp-timer.is-expired {
            background: var(--error-bg);
            border-color: #fca5a5;
            color: var(--error);
        }

        .otp-timer.is-expired strong {
            color: var(--error);
        }

        button[disabled] {
            opacity: 0.55;
            cursor: not-allowed;
        }

        button[disabled]:hover {
            filter: none;
            transform: none;
        }

        .links {
            margin-top: 22px;
            text-align: center;
            font-size: 0.8rem;
            color: rgba(238, 247, 239, 0.84);
        }

        .links a {
            color: #d9fae4;
            text-decoration: none;
            font-weight: 500;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .links p {
            margin: 6px 0;
        }

        @media (max-width: 480px) {
            body {
                padding: 16px;
                background-attachment: scroll;
            }

            .page-card {
                border-radius: 22px;
                padding: 34px 23px 28px;
            }

            .brand img { width: 76px; height: 76px; }
            .brand-name { font-size: 1.78rem; }

            input {
                font-size: 1rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
            }
        }
    </style>

    @stack('styles')
</head>
<body>
    <div class="page-wrapper">
        @yield('content')
    </div>

    <script src="{{ asset('js/password-policy.js') }}?v={{ substr(md5_file(public_path('js/password-policy.js')), 0, 12) }}"></script>
    @stack('scripts')
    @auth @include('partials.session-timeout') @endauth
</body>
</html>
