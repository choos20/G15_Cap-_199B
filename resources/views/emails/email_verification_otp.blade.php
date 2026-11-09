@component('mail::message')
# Verify your email

Hello {{ $name }},

Thanks for registering with {{ config('app.name') }}. Enter this code to confirm your email address:

@component('mail::panel')
{{ $otp }}
@endcomponent

This code expires in **10 minutes**. You won't be able to log in until your email is verified.

@component('mail::subcopy')
**Didn't create this account?** You can safely ignore this email — no account will be activated without this code.
@endcomponent

Thanks,<br>
The {{ config('app.name') }} Team
@endcomponent
