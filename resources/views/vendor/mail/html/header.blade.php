{{--
    Published from the framework to render the brand as a coloured band that
    lines up with the card below it.

    The framework's version puts the brand directly in a full-width cell. The
    band is therefore wrapped in its own 570px table here, matching
    .inner-body's width and alignment -- without it the gradient would run the
    full width of the viewport while the card stayed centred and narrow.

    The framework's "if the slot is exactly 'Laravel', show the Laravel logo"
    branch is dropped: this application always brands from the BusinessName
    setting, and a business legitimately called Laravel would otherwise get
    the framework's logo.
--}}
@props(['url'])
<tr>
<td>
<table class="header-band" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
