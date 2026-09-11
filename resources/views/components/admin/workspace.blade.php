@props([
    'title',
])

@php
    $automaticStatus = isset($status)
        ? null
        : app(\App\Filament\Support\AdminWorkspaceStatus::class)->resolve((string) $title);
@endphp

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

        @if (isset($status))
            <div class="admin-workspace__status">
                {{ $status }}
            </div>
        @elseif ($automaticStatus !== null)
            <div class="admin-workspace__status">
                <x-admin.status :tone="$automaticStatus['tone']">
                    {{ $automaticStatus['label'] }}
                </x-admin.status>
            </div>
        @endif
    </header>

    <div class="admin-workspace__body">
        {{ $slot }}
    </div>
</div>
