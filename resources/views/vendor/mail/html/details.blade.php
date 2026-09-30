@props(['rows' => []])
<table class="details" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($rows as $label => $value)
@if (filled($value))
<tr>
<td class="details-label" width="34%">{{ $label }}</td>
<td class="details-value">{{ $value }}</td>
</tr>
@endif
@endforeach
</table>
