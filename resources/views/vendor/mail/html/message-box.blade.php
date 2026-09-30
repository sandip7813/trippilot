@props(['label', 'meta' => null, 'text' => '', 'tone' => 'quote'])
<table class="message-box message-box-{{ $tone }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="message-box-content">
<p class="message-box-label">{{ $label }}@if ($meta)<span class="message-box-meta"> &middot; {{ $meta }}</span>@endif</p>
<p class="message-box-body">{!! str_replace(["\r\n", "\r", "\n"], '<br>', e($text)) !!}</p>
</td>
</tr>
</table>
