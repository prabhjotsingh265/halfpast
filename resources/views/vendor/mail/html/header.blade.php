@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('brand/mark-96.png') }}" width="24" height="24" alt="" style="vertical-align: middle; border: 0; margin-right: 8px;">{!! $slot !!}
</a>
</td>
</tr>
