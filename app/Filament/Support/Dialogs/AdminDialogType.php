<?php

namespace App\Filament\Support\Dialogs;

enum AdminDialogType: string
{
    case Create = 'create';
    case Edit = 'edit';
    case Command = 'command';
    case Confirm = 'confirm';
    case Viewer = 'viewer';
}
