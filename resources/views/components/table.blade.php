@props(['head'])

<div class="table-wrap">
    <table class="table">
        <thead><tr>{{ $head }}</tr></thead>
        <tbody>{{ $slot }}</tbody>
    </table>
</div>
