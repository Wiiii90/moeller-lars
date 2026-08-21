<?php

it('disables Apache keepalive on the internal reverse-proxy hop', function (): void {
    $config = file_get_contents(base_path('docker/apache-mpm.conf'));

    expect($config)->not->toBeFalse();
    expect((bool) preg_match('/^\s*KeepAlive\s+Off\s*$/mi', (string) $config))->toBeTrue();
    expect((bool) preg_match('/^\s*KeepAlive\s+On\s*$/mi', (string) $config))->toBeFalse();
});

it('ships the Apache performance config in the runtime image', function (): void {
    $dockerfile = file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)->not->toBeFalse();
    expect((string) $dockerfile)->toContain(
        'COPY docker/apache-mpm.conf /etc/apache2/mods-available/mpm_prefork.conf',
    );
});
