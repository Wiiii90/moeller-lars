<?php

namespace App\Filament\Support\Dialogs;

use BackedEnum;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Support\Exceptions\Cancel;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use JsonSerializable;
use Livewire\Attributes\Locked;
use Stringable;
use Throwable;

trait InteractsWithAdminEditDialogAutosave
{
    /** @var array<string, string> */
    #[Locked]
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

            $key = $this->adminEditDialogFingerprintKey($action);
            $fingerprint = $this->adminEditDialogFingerprint($state);
            if (($this->adminEditDialogPersistedFingerprints[$key] ?? null) === $fingerprint) {
                $action->commitDatabaseTransaction();

                return;
            }

            $action->callBefore();
            $action->call(['form' => $schema, 'schema' => $schema]);
            $action->callAfter();
            $this->afterActionCalled($action);
            $action->commitDatabaseTransaction();

            // Only a successfully completed domain action becomes the next
            // no-op baseline. Validation/errors never advance this receipt.
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

    private function adminEditDialogFingerprintKey(Action $action): string
    {
        $record = method_exists($action, 'getRecord') ? $action->getRecord() : null;
        $recordIdentity = $record instanceof Model
            ? $record::class.':'.(string) $record->getKey()
            : 'none';

        return $action->getName().':'.$recordIdentity.':'.$this->adminEditDialogFingerprint($action->getArguments());
    }

    private function adminEditDialogFingerprint(mixed $value): string
    {
        return hash('sha256', serialize($this->normalizeAdminEditDialogValue($value)));
    }

    private function normalizeAdminEditDialogValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof Model) {
            return [$value::class, $value->getKey()];
        }

        if ($value instanceof JsonSerializable) {
            return $this->normalizeAdminEditDialogValue($value->jsonSerialize());
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(fn (mixed $item): mixed => $this->normalizeAdminEditDialogValue($item), $value);
        }

        if (is_object($value)) {
            return [$value::class, spl_object_id($value)];
        }

        return $value;
    }
}
