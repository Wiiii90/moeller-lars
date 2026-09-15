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
        ->and($confirm->isIconButton())->toBeFalse();
});
