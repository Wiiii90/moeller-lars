<?php

use App\Filament\Support\Dialogs\AdminDialog;
use Filament\Actions\Action;

it('keeps committed dialog submit actions separate from their parent action', function (): void {
    $create = AdminDialog::create(Action::make('createThing'), 'Create thing');
    $createSubmit = $create->getModalSubmitAction();

    expect($createSubmit)->not->toBeNull()
        ->and($createSubmit->getName())->toBe('submit')
        ->and($createSubmit->isIconButton())->toBeTrue()
        ->and($create->getName())->toBe('createThing')
        ->and($create->isIconButton())->toBeFalse();

    $confirm = AdminDialog::confirm(Action::make('deleteThing'), 'Delete thing?');
    $confirmSubmit = $confirm->getModalSubmitAction();

    expect($confirmSubmit)->not->toBeNull()
        ->and($confirmSubmit->getName())->toBe('submit')
        ->and($confirmSubmit->isIconButton())->toBeTrue()
        ->and($confirm->getName())->toBe('deleteThing')
        ->and($confirm->isIconButton())->toBeFalse()
        ->and($confirm->isConfirmationRequired())->toBeFalse()
        ->and($confirm->hasModal())->toBeTrue()
        ->and($confirm->getModalIcon())->toBeNull();

    $direct = AdminDialog::confirm(
        Action::make('changeType'),
        'Change component type?',
        condition: false,
    );

    expect($direct->isConfirmationRequired())->toBeFalse()
        ->and($direct->hasModal())->toBeFalse();
});


it('uses the Pages toolbar icon for the Add page create submit but keeps the canonical default for other creates', function (): void {
    $pages = AdminDialog::create(
        Action::make('addPage'),
        'Create page',
        \App\Filament\Support\Dialogs\AdminDialogSize::Large,
        \App\Filament\Support\AdminIcon::Pages,
    );
    $default = AdminDialog::create(Action::make('addProfile'), 'Add profile');

    expect($pages->getModalSubmitAction()?->getIcon())->toBe(\App\Filament\Support\AdminIcon::Pages->value)
        ->and($default->getModalSubmitAction()?->getIcon())->toBe(\App\Filament\Support\AdminIcon::DialogSubmit->value);
});
