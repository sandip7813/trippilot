@props(['rows' => []])
@foreach ($rows as $label => $value)
@if (filled($value))
{{ $label }}: {{ $value }}
@endif
@endforeach
