<?php

namespace App\Filament\Support\Dialogs;

use Filament\Actions\Action;
use Filament\Support\Exceptions\Cancel;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;
use Throwable;

trait InteractsWithAdminEditDialogAutosave
{
    /** @var array<string, string> */
    public array $adminEditDialogPersistedFingerprints = [];

    public function persistMountedAdminEdit(): void
    {
        $action = $this->getMountedAction();

        if (! $action instanceof Action) {
            return;
        }

        $schema = $this->getMountedActionSchema(mountedAction: $action);

        if ($schema === null) {
            return;
        }

        $action->beginDatabaseTransaction();

        try {
            $action->callBeforeFormValidated();
            $state = $schema->getState();
            $action->callAfterFormValidated();
            $action->data($state);

            $key = $action->getName().':'.hash('sha256', serialize($action->getArguments()));
            $fingerprint = hash('sha256', serialize($state));

            if (($this->adminEditDialogPersistedFingerprints[$key] ?? null) === $fingerprint) {
                $action->commitDatabaseTransaction();

                return;
            }

            $action->callBefore();
            $action->call(['form' => $schema, 'schema' => $schema]);
            $action->callAfter();
            $this->afterActionCalled($action);
            $action->commitDatabaseTransaction();

            $this->adminEditDialogPersistedFingerprints[$key] = $fingerprint;
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction()
                ? $action->rollBackDatabaseTransaction()
                : $action->commitDatabaseTransaction();
        } catch (Cancel $exception) {
            $exception->shouldRollbackDatabaseTransaction()
                ? $action->rollBackDatabaseTransaction()
                : $action->commitDatabaseTransaction();
        } catch (ValidationException $exception) {
            $action->rollBackDatabaseTransaction();

            throw $exception;
        } catch (Throwable $exception) {
            $action->rollBackDatabaseTransaction();

            throw $exception;
        }
    }
}
