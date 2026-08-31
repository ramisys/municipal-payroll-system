@props(['message', 'colspan' => 1])

<tr>
    <td colspan="{{ $colspan }}" class="empty-row">
        <span class="block">{{ $message }}</span>
        @isset($action)
            <span class="block mt-2">{{ $action }}</span>
        @endisset
    </td>
</tr>
