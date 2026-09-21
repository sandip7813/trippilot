<x-mail::message>
# Settled expenses reopened

Hi {{ $participantName }},

{{ $reopenedBy }} reopened the settled expense sheet for "{{ $tripTitle }}", so figures may change.

**Reason:** {{ $reason }}

@if ($tripUrl)
<x-mail::button :url="$tripUrl">
View trip
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
