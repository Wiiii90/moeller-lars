<?php

it('maintains one version-locked Filament modal scroll lock, not an application-level duplicate', function (): void {
    $patch = file_get_contents(base_path('scripts/patch-filament-scroll-lock.php'));
    $composer = file_get_contents(base_path('composer.json'));
    $dialogGeometry = file_get_contents(resource_path('js/admin-dialog-geometry.js'));
    $shellStyles = file_get_contents(resource_path('css/admin/layouts.css'));
    $dockerfile = file_get_contents(base_path('Dockerfile'));

    expect($composer)
        ->toContain('@php scripts/patch-filament-scroll-lock.php')
        ->toContain('@php artisan filament:upgrade');

    expect(strpos($composer, '@php scripts/patch-filament-scroll-lock.php'))
        ->toBeLessThan(strpos($composer, '@php artisan filament:upgrade'));

    expect($patch)
        ->toContain("'v5.7.6'")
        ->toContain('substr_count($bundle, $original) !== 1')
        ->toContain('if(!hadVerticalScrollbar){')
        ->toContain('root.style.overflow="hidden"')
        ->toContain('root.style.overflowY="scroll"')
        ->toContain('body.style.position="fixed"')
        ->toContain('body.style.position="fixed"')
        ->toContain('window.addEventListener("scroll",holdPosition')
        ->toContain('window.removeEventListener("scroll",holdPosition')
        ->toContain('window.scrollTo({left:x,top:y,behavior:"instant"})')
        ->toContain('nr++')
        ->toContain('nr--');

    expect($dockerfile)
        ->toContain('COPY --from=application /var/www/html/public/js/filament ./public/js/filament');

    expect($dialogGeometry)
        ->not->toContain('window.addEventListener(\'wheel\'')
        ->not->toContain('window.addEventListener(\'touchmove\'')
        ->not->toContain('document.documentElement.style.overflow')
        ->not->toContain('window.scrollTo(');

    expect($shellStyles)
        ->not->toContain('scrollbar-gutter: stable')
        ->not->toContain('admin-modal-visible-scrollbar-lock');
});
