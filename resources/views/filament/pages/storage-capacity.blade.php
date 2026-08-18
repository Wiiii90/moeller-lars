<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">How storage is counted</x-slot>
        <x-slot name="description">The site allowance protects the artist's own authoritative media without exposing server-wide storage details.</x-slot>

        <p>
            Original uploads are authoritative and count against the configured site allowance. Generated thumbnails and previews are measured separately because they can be rebuilt from the originals. The allowance is read-only here and can only be changed by the site operator.
        </p>
    </x-filament::section>
</x-filament-panels::page>
