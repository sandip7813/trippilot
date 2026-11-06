@props(['label', 'code', 'hint' => null])
{{ $label }}: {{ $code }}
@if ($hint)
{{ $hint }}
@endif
