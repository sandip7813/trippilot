<x-mail::message>
# Expense report

{{ $senderName }} shared the expense report for "{{ $tripTitle }}" with you. The {{ $formatLabel }} report is attached.

@if ($note)
> {{ $note }}
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
