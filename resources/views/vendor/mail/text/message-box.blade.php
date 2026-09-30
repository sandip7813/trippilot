@props(['label', 'meta' => null, 'text' => '', 'tone' => 'quote'])
--- {{ $label }}{{ $meta ? ' · '.$meta : '' }} ---
{{ $text }}
