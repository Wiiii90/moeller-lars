<?php

it('keeps shared admin form sections uncontained', function (): void {
    $source = file_get_contents(app_path('Filament/Support/AdminForm.php'));

    expect($source)
        ->toContain('Section::make($label)')
        ->toContain('->contained(false)');
});
