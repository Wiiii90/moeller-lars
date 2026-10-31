<?php

declare(strict_types=1);

/**
 * Filament's shared modal X uses the generic icon-button, which adds a
 * non-targeted wire:loading.attr="disabled". A text-input blur starts the
 * native Edit autosave before the pointer click reaches X, disabling it.
 *
 * Scope this one native button to its own unmount calls. Do not intercept
 * clicks, change autosave, or install a second modal lifecycle. Fail closed
 * whenever the pinned vendor source no longer matches.
 */
$root = dirname(__DIR__);
$lock = json_decode((string) file_get_contents($root.'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
$packages = array_values(array_filter(
    $lock['packages'] ?? [],
    static fn (array $package): bool => ($package['name'] ?? null) === 'filament/support',
));

if (count($packages) !== 1 || $packages[0]['version'] !== 'v5.7.6') {
    fwrite(STDERR, "Filament modal close patch: unsupported filament/support version. Review upstream modal and icon-button.\n");
    exit(1);
}

$view = $root.'/vendor/filament/support/resources/views/components/modal/index.blade.php';
$content = @file_get_contents($view);
if ($content === false) {
    fwrite(STDERR, "Filament modal close patch: missing vendor modal view: {$view}\n");
    exit(1);
}

$original = <<<'BLADE'
                            :x-on:click="$closeEventHandler"
                            class="fi-modal-close-btn"
BLADE;

$replacement = <<<'BLADE'
                            :x-on:click="$closeEventHandler"
                            wire:target="unmountAction,unmountFormComponentAction"
                            class="fi-modal-close-btn"
BLADE;

if (substr_count($content, $replacement) === 1 && ! str_contains($content, $original)) {
    echo "Filament modal close button already scoped to unmount actions.\n";
    exit(0);
}

if (substr_count($content, $original) !== 1 || str_contains($content, $replacement)) {
    fwrite(STDERR, "Filament modal close patch: unreviewed vendor modal source; refusing patch.\n");
    exit(1);
}

$updated = str_replace($original, $replacement, $content, $count);
if ($count !== 1 || file_put_contents($view, $updated) === false) {
    fwrite(STDERR, "Filament modal close patch: unable to update the canonical vendor view.\n");
    exit(1);
}

echo "Filament modal close button scoped to its own unmount actions.\n";
