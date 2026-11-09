@component('mail::message')
# Password reset code

Hello {{ $name }},

We received a request to reset the password on your {{ config('app.name') }} account. Enter this code to continue:

@component('mail::panel')
{{ $otp }}
@endcomponent

This code expires in **10 minutes** and can only be used once.

@component('mail::subcopy')
**Didn't request this?** Your password is still safe — no changes have been made. You can ignore this email, or reach out if you're concerned about your account's security.
@endcomponent

Thanks,<br>
The {{ config('app.name') }} Team
@endcomponent
