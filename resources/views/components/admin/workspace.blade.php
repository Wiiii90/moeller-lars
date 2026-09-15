@props([
    'title',
])

<div {{ $attributes->class(['admin-workspace']) }}>
    <header class="admin-workspace__header">
        <div class="admin-workspace__heading">
            <h1 class="admin-workspace__title">{{ $title }}</h1>
        </div>

        @isset($summary)
            <div class="admin-workspace__summary">
                {{ $summary }}
            </div>
        @endisset
    </header>

    <div class="admin-workspace__body">
        {{ $slot }}
    </div>
</div>
