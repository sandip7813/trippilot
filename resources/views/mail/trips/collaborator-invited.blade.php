<x-mail::message>
# You've been invited to a trip

Someone invited **{{ $invitedEmail }}** to plan "{{ $tripTitle }}" on TripPilot with **{{ $roleLabel }}** access.

Create a TripPilot account with this email address to get access automatically.

<x-mail::button :url="$registerUrl">
Create your account
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
