<?php

declare(strict_types=1);

// Correct Livewire's existing serializer, only in the separate Public CSP
// bundles. No second history cache, navigation listener or Admin bundle patch.
$root = dirname(__DIR__);
$lock = json_decode((string) file_get_contents($root.'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
$packages = array_values(array_filter($lock['packages'], static fn (array $package): bool => $package['name'] === 'livewire/livewire'));
if (count($packages) !== 1 || $packages[0]['version'] !== 'v4.4.0') {
    throw new RuntimeException('Review the CSP history patch before upgrading Livewire.');
}

$serializer = <<<'JS'

  function serializeCspHistoryDocument() {
    const snapshot = document.documentElement.cloneNode(true);
    const sources = document.querySelectorAll('script,style,link');
    // Chromium deliberately hides connected nonce attributes from outerHTML.
    // Serialize the current document's actual nonce property on the detached
    // copy; Livewire's existing nonce normalization still owns page restores.
    // Dynamically created styles (NProgress) may have a nonce property with
    // no reflected attribute at all; selecting [nonce] would miss them.
    snapshot.querySelectorAll('script,style,link').forEach((element, index) => {
      if (sources[index].nonce) element.setAttribute('nonce', sources[index].nonce);
    });
    // NProgress is transient navigation UI, not destination page state.
    snapshot.querySelector('#nprogress')?.remove();
    return snapshot.outerHTML;
  }

JS;

$bundles = [
    'livewire.csp.js' => ['prefix' => "(() => {\n", 'hash' => 'e2c33b4c41071574399dafddf32bfb7d3d83ba63d34b946d557fbd487b5e1969'],
    'livewire.csp.min.js' => ['prefix' => '(()=>{', 'hash' => '94a5f8498eed8be8e1e1013fb040859fa41dc1659ac58d9525c7140b7b520f14'],
];
$updated = [];
foreach ($bundles as $name => $contract) {
    $path = $root.'/vendor/livewire/livewire/dist/'.$name;
    $current = file_get_contents($path);
    if ($current === false) {
        throw new RuntimeException('Missing Public Livewire bundle: '.$name);
    }
    $original = $current;
    if (substr_count($current, $serializer) === 1) {
        $original = str_replace($serializer, '', $original);
        $original = str_replace('serializeCspHistoryDocument()', 'document.documentElement.outerHTML', $original, $count);
        if ($count !== 2) {
            throw new RuntimeException('Unexpected patched history callers: '.$name);
        }
    }
    if (hash('sha256', $original) !== $contract['hash'] || ! str_starts_with($original, $contract['prefix']) || substr_count($original, 'document.documentElement.outerHTML') !== 2) {
        throw new RuntimeException('Public Livewire bundle differs from reviewed 4.4.0: '.$name);
    }
    $patched = str_replace('document.documentElement.outerHTML', 'serializeCspHistoryDocument()', $original);
    $patched = substr_replace($patched, $serializer, strlen($contract['prefix']), 0);
    $updated[$path] = $patched;
}
// Both public debug/production assets share Livewire's canonical cache version.
// Admin code remains byte-identical; its URL also receives a harmless cache bust.
$manifestPath = $root.'/vendor/livewire/livewire/dist/manifest.json';
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$manifest['/livewire.js'] = substr(hash('sha256', implode('', $updated)), 0, 8);
foreach ($updated as $path => $content) {
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException('Could not write Public CSP history patch.');
    }
}
file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
echo "Public Livewire CSP history serializer verified and patched.\n";
