<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name', 'ProjectLink'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --bg-start: #91eec3;
            --bg-mid: #f5f5f5;
            --bg-end: #33b170;
            --panel: rgba(255, 255, 255, 0.18);
            --panel-strong: rgba(255, 255, 255, 0.28);
            --border-panel: rgba(255, 255, 255, 0.2);
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
            height: 100vh;
        }

        body {
            font-family: var(--font-sans);
            background:
                radial-gradient(circle at top left, rgba(141, 245, 184, 0.22), transparent 1%), /*(141, 245, 184, 0.22), orig*/
                linear-gradient(100deg, rgba(11, 58, 36, 0.82) 0%, rgba(20, 123, 70, 0.72)105%, rgba(51, 177, 112, 0.6) 100%), /*If ganahan ka adjust san  rgba(20, 123, 70, 0.72)1005%, and orig 65*/
                url('{{ asset('images/login-bg.jpg') }}') center / cover no-repeat fixed;
            color: var(--text-light);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0 24px;
        }

        .page-wrapper {
            width: 100%;
            max-width: 540px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            padding: 24px 0;
        }

        .page-card {
            position: relative;
            background: linear-gradient(180deg, hsla(96, 8%, 88%, 0.22), rgba(237, 229, 229, 0.1));
            border: 1px solid var(--border-panel);
            backdrop-filter: blur(8px) saturate(120%); /*color sa glass*/
            border-radius: 18px;
            box-shadow: 0 20px 50px rgba(8, 23, 13, 0.28);
            width: 100%;
            padding: 64px 40px 36px;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .page-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 28px 70px rgba(8, 23, 13, 0.32);
        }

        /* Small rounded back button in the top-left of the card */
        .back-link {
            position: absolute;
            top: 14px;
            left: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            background: rgba(255,255,255,0.06);
            color: var(--text-light);
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            border: 1px solid rgba(255,255,255,0.06);
            box-shadow: 0 6px 18px rgba(5, 20, 10, 0.12);
            transition: background 0.15s ease, transform 0.12s ease;
        }

        .back-link:hover {
            background: rgba(255,255,255,0.12);
            transform: translateY(-2px);
        }

        .brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 22px;
        }

        .brand img {
            width: 92px;
            height: 92px;
            object-fit: contain;
            margin-bottom: 14px;
        }

        .brand-name {
             font-family: 'Space Grotesk', var(--font-sans);
            font-size: 1.7rem;
            font-weight: 700;
            letter-spacing: -0.04em;
            color: var(--text-light);
        }

        .brand-sub {
            font-size: 0.82rem;
            color: rgba(241, 249, 241, 0.82);
            margin-top: 4px;
            line-height: 1.5;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-light);
            margin: 0 0 6px;
           /* text-align: center; */
        }

        .section-subtitle {
            font-size: 0.92rem;
            color: rgba(238, 247, 239, 0.78);
            margin: 0 0 26px;
        }

        label {
            display: block;
            text-align: left;
            font-size: 0.8rem;
            font-weight: 500;
            margin: 16px 0 6px;
            color: #edf6ef;
        }

        input,
        button {
            font-family: var(--font-sans);
        }

        input {
            width: 100%;
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.95);
            border: 1.5px solid rgba(255, 255, 255, 0.45);
            border-radius: 12px;
            color: var(--text-dark);
            font-size: 0.95rem;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        input:focus {
            outline: none;
            border-color: rgba(236, 237, 235, 0.95);
            box-shadow: 0 0 0 3px rgba(66, 198, 116, 0.2);
            background: #ffffff;
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
            margin-top: 26px;
            padding: 14px;
            background: linear-gradient(135deg, var(--button-start), var(--button-end));
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            color: #ffffff;
            cursor: pointer;    
            transition: filter 0.15s ease, transform 0.15s ease;
        }

        button:hover {
            filter: brightness(1.05);
            transform: translateY(-1px);
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
            margin-top: 24px;
            text-align: center;
            font-size: 0.78rem;
            color: rgba(238, 247, 239, 0.78);
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
            }

            .page-card {
                padding: 30px 24px;
            }
        }
    </style>

    @stack('styles')
</head>
<body>
    <div class="page-wrapper">
        @yield('content')
    </div>

    @stack('scripts')
</body>
</html>
