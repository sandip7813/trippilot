<x-mail::message>
# Expenses settled

Hi {{ $participantName }},

{{ $settledBy }} closed the expense sheet for "{{ $tripTitle }}". The total trip spend was **{{ $total }}**.

<x-mail::table>
| Person | Paid | Share | Balance |
|:-------|-----:|------:|--------:|
@foreach ($balances as $row)
| {{ $row['name'] }} | {{ $row['paid'] }} | {{ $row['share'] }} | {{ $row['balance'] }} |
@endforeach
</x-mail::table>

A positive balance means the person is owed money; a negative balance means they owe money.

@if (count($settlements) > 0)
**Suggested settlements**

@foreach ($settlements as $line)
- {{ $line }}
@endforeach
@endif

@if ($tripUrl)
<x-mail::button :url="$tripUrl">
View trip
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
