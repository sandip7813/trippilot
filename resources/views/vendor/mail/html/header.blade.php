@props(['url'])
<tr>
<td align="center">
<table class="header-inner" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo-v2.1.png" class="logo" alt="Laravel Logo">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>

<!-- Accent bar -->
<tr>
<td class="accent-bar" width="100%"></td>
</tr>
</table>
</td>
</tr>
