@props(['label', 'code', 'hint' => null])
<table class="code-box" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="code-box-content" align="center">
<p class="code-box-label">{{ $label }}</p>
<p class="code-box-value">{{ $code }}</p>
@if ($hint)
<p class="code-box-hint">{{ $hint }}</p>
@endif
</td>
</tr>
</table>
