{{-- Print-only signature block. Shown on rosters and certification records. --}}
<div class="rp-sign">
    <table style="width:100%; border-collapse:collapse; margin-top:12px">
        <tr>
            @foreach($blocks as $block)
                <td style="width:{{ round(100 / count($blocks)) }}%; padding:0 18px; vertical-align:bottom">
                    <div style="height:44px"></div>
                    {{-- nbsp keeps unnamed blocks the same height as named ones,
                         so every signature rule lines up across the row. --}}
                    <div style="border-top:1px solid #000; padding-top:5px; font-size:0.78rem; color:#000">
                        <strong>{!! ($block['name'] ?? null) ? e($block['name']) : '&nbsp;' !!}</strong>
                    </div>
                    <div style="font-size:0.72rem; color:#333">{{ $block['role'] }}</div>
                </td>
            @endforeach
        </tr>
    </table>
</div>
