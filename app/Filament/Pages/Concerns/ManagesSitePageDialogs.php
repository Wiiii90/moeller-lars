<?php

namespace App\Filament\Pages\Concerns;

use Illuminate\Validation\ValidationException;

trait ManagesSitePageDialogs
{
    use ManagesHomePagePresentation;
    use ManagesSitePageCreateDialog;
    use ManagesSitePageEditDialog;
    use ManagesSitePagesSettings;

    private function throwPageDialogValidation(ValidationException $exception): never
    {
        $path = $this->getMountedActionSchema()->getStatePath();
        $errors = [];
        foreach ($exception->errors() as $field => $messages) {
            $errors[$path.'.'.$field] = $messages;
        }
        throw ValidationException::withMessages($errors);
    }
}
