@php
    $name = trim((string) (auth()->user()?->name ?? ''));
@endphp

<span>{{ $name !== '' ? "Moin, {$name}!" : 'Admin Area' }}</span>
