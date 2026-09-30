<x-mail::message>
# Welcome to TripPilot, {{ $firstName }}!

Your account has been created. Use the one-time password below to log in:

<x-mail::panel>
**Email:** {{ $email }}<br>
**One-time password:** {{ $password }}
</x-mail::panel>

You will be asked to choose a new password the first time you log in.

<x-mail::button :url="$loginUrl">
Log in
</x-mail::button>

If you did not create this account, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
