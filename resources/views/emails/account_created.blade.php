<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0; padding:0; background-color:#f4f6f8; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:560px; margin:0 auto;">
                <tr>
                    <td style="padding:0 0 18px; color:#0b6a4a; font-size:20px; line-height:1.3; font-weight:700; letter-spacing:-0.3px;">
                        {{ config('app.name') }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:0; background-color:#ffffff; border:1px solid #e2e8f0; border-radius:12px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="padding:30px 32px 28px; border-top:3px solid #0b6a4a; border-radius:12px 12px 0 0;">
                                    <p style="margin:0 0 8px; color:#64748b; font-size:12px; line-height:1.5; font-weight:700; letter-spacing:1px; text-transform:uppercase;">Account details</p>
                                    <h1 style="margin:0 0 14px; color:#111827; font-size:24px; line-height:1.3; font-weight:700;">Welcome to {{ config('app.name') }}</h1>
                                    <p style="margin:0 0 22px; color:#475569; font-size:15px; line-height:1.65;">Hi {{ $userData['name'] }}, your account is ready. Use the details below to sign in.</p>

                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 22px; border:1px solid #e5e7eb; border-radius:8px;">
                                        <tr>
                                            <td style="padding:13px 15px 4px; color:#64748b; font-size:12px; line-height:1.4;">Email address</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:0 15px 13px; color:#1f2937; font-size:14px; line-height:1.55; font-weight:600; word-break:break-word; overflow-wrap:anywhere;">{{ $userData['email'] }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:12px 15px 4px; border-top:1px solid #e5e7eb; color:#64748b; font-size:12px; line-height:1.4;">Account role</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:0 15px 13px; color:#1f2937; font-size:14px; line-height:1.55; font-weight:600;">{{ role_label($userData['role']) }}</td>
                                        </tr>
                                    </table>

                                    @if(!empty($plainPassword))
                                        <p style="margin:0 0 8px; color:#1f2937; font-size:14px; line-height:1.5; font-weight:700;">Temporary password</p>
                                        <div style="margin:0 0 12px; padding:16px; background-color:#f8fafc; border:1px solid #dbe3ea; border-radius:8px; text-align:center;">
                                            <span style="color:#0b6a4a; font-family:Consolas,'Courier New',monospace; font-size:22px; line-height:1.5; font-weight:700; letter-spacing:2px; word-break:break-all; overflow-wrap:anywhere;">{{ $plainPassword }}</span>
                                        </div>
                                        <p style="margin:0 0 22px; color:#64748b; font-size:13px; line-height:1.6;">For your security, sign in and change this password as soon as possible. Do not share it with anyone.</p>
                                    @endif

                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0;">
                                        <tr>
                                            <td align="center" bgcolor="#0b6a4a" style="border-radius:7px;">
                                                <a href="{{ route('login') }}" style="display:inline-block; padding:13px 22px; border:1px solid #0b6a4a; border-radius:7px; color:#ffffff; font-size:14px; line-height:1.4; font-weight:700; text-decoration:none;">Log in to {{ config('app.name') }}</a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 8px 0; color:#94a3b8; font-size:12px; line-height:1.6; text-align:center;">
                        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
