<x-mail::message>
# Verify your email

Use the code below to finish creating your TripPilot account:

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

This code expires in {{ $expiresInMinutes }} minutes. If you did not request it, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
