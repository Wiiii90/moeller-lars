<?php

declare(strict_types=1);

/**
 * Filament 5.7.6 uses a generic icon button for the native modal X, which
 * disables itself during ANY Livewire request. For autosaving Edit dialogs
 * only, scope that loading lock to the actual unmount operation. The edit
 * close guard in AdminDialog still defers closing until existing saves finish.
 * Other modal types keep their unmodified native loading behavior.
 */
$root = dirname(__DIR__);
$lock = json_decode((string) file_get_contents($root.'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
$packages = array_values(array_filter(
    $lock['packages'] ?? [],
    static fn (array $package): bool => ($package['name'] ?? null) === 'filament/support',
));

if (count($packages) !== 1 || $packages[0]['version'] !== 'v5.7.6') {
    fwrite(STDERR, "Filament edit close: unsupported support version. Review upstream before upgrading.\n");
    exit(1);
}

$file = $root.'/vendor/filament/support/resources/views/components/modal/index.blade.php';
$source = @file_get_contents($file);
if ($source === false) {
    fwrite(STDERR, "Filament edit close: vendor modal view missing.\n");
    exit(1);
}

$before = <<<'BLADE'
                            :x-on:click="$closeEventHandler"
                            class="fi-modal-close-btn"
BLADE;

$after = <<<'BLADE'
                            :x-on:click="$closeEventHandler"
                            @if (($extraModalWindowAttributeBag?->get('data-admin-edit-close-guard')) === 'true')
                                wire:target="unmountAction"
                            @endif
                            class="fi-modal-close-btn"
BLADE;

if (substr_count($source, $after) === 1 && ! str_contains($source, $before)) {
    echo "Filament Edit X already has scoped loading.\n";
    exit(0);
}

if (substr_count($source, $before) !== 1 || str_contains($source, $after)) {
    fwrite(STDERR, "Filament edit close: unreviewed vendor modal source; refusing to patch.\n");
    exit(1);
}

$updated = str_replace($before, $after, $source, $count);
if ($count !== 1 || file_put_contents($file, $updated) === false) {
    fwrite(STDERR, "Filament edit close: could not update vendor modal template.\n");
    exit(1);
}

echo "Filament Edit X enabled for pending saves; native closing remains guarded.\n";
