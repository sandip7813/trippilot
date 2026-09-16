<x-mail::message>
# You've been added to a trip

You now have **{{ $roleLabel }}** access to "{{ $tripTitle }}" on TripPilot.

<x-mail::button :url="$tripUrl">
View trip
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
