<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\SocialLinks;
use App\Filament\Support\AdminRowAction;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\CustomPageSetting;
use App\Models\PublicContentSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

trait HomePresentationChildren
{
    /** @param array<string,mixed> $component
     * @return list<array<string,mixed>>
     */
    private function homeChildProjection(array $component, int $componentIndex): array
    {
        $type = (string) ($component['type'] ?? '');
        if ($type !== 'contact') {
            return [];
        }
        $children = (new CustomPageSetting)->contactChildren($component);
        $children = is_array($children) ? array_values($children) : [];
        $result = [];
        foreach ($children as $position => $child) {
            if (! is_array($child)) {
                continue;
            }
            $kind = 'contact';
            $key = (string) ($child['type'] ?? '');
            $label = match ($key) {
                'public_email' => 'Public Email', 'social_links' => 'Social Media Links', 'contact_form' => 'Contact Form',
                default => 'Contact item',
            };
            $result[] = [
                'kind' => $kind,
                'key' => $key,
                'target' => $kind.':'.$componentIndex.':'.$key,
                'position' => $position + 1,
                'title' => $label,
                'detail' => $key === 'contact_form' ? (string) ($child['form_state'] ?? 'enabled') : '',
                'published' => CustomPageSetting::contactChildPublished($child),
                'can_move_up' => $position > 0,
                'can_move_down' => $position < count($children) - 1,
                'parent_published' => CustomPageSetting::componentPublished($component),
            ];
        }

        return $result;
    }

    /** @param array<string,mixed> $arguments
     * @return array{0:array<string,mixed>,1:int|string,2:array<string,mixed>}
     */
    private function homeActionChild(array $arguments): array
    {
        $component = $this->componentFromArguments($arguments);
        $kind = (string) ($arguments['type'] ?? '');
        if ($kind !== 'contact' || $component['type'] !== $kind) {
            throw ValidationException::withMessages(['component' => 'This Home component changed. Reload and try again.']);
        }
        $children = (new CustomPageSetting)->contactChildren($component);
        $key = (string) ($arguments['child'] ?? '');
        $index = array_search($key, array_column($children, 'type'), true);
        if ($index === false || ! isset($children[$index])) {
            throw ValidationException::withMessages(['component' => 'This Home child changed. Reload and try again.']);
        }

        return [$component, $key, $children[$index]];
    }

    /** @param array<string,mixed> $arguments */
    private function homeChildArgs(array $arguments): array
    {
        return [
            'index' => (int) ($arguments['index'] ?? -1),
            'type' => (string) ($arguments['type'] ?? ''),
            'child' => $arguments['child'] ?? null,
        ];
    }

    /** @param array<string,mixed> $args
     * @param array<string,mixed> $payload
     */
    private function persistHomeChild(array $args, string $operation, array $payload = []): void
    {
        $changed = app(HomePresentationEditorialService::class)->mutateChild(
            $this->settings(), $this->componentTemplate(),
            (int) $args['index'], (string) $args['type'], $operation, $args['child'] ?? null, $payload,
        );
        $this->reloadWorkspace();
        if ($changed) {
            $this->forceRender();
            app(AdminNotifier::class)->notification(
                title: match ($operation) {
                    'add' => 'Home item added',
                    'update' => 'Home item saved',
                    'publish' => ($payload['published'] ?? false) ? 'Home item published' : 'Home item unpublished',
                    'delete' => 'Home item deleted',
                    default => 'Home item order updated',
                },
                status: 'success',
            );
        }
    }

    public function addHomeChildAction(): Action
    {
        return AdminDialog::create(Action::make('addHomeChild')
            ->label('Add child')
            ->fillForm(function (array $arguments): array {
                $parent = $this->componentFromArguments($arguments);
                $existing = array_column((new CustomPageSetting)->contactChildren($parent), 'type');
                $available = array_values(array_diff(CustomPageSetting::CONTACT_CHILD_TYPES, $existing));

                return ['child_type' => $available[0] ?? null, 'publication_state' => 'published'];
            })
            ->schema(fn (array $arguments): array => $this->homeContactFields(true, $arguments))
            ->action(function (array $data, array $arguments): void {
                $args = $this->homeChildArgs($arguments);
                $this->componentFromArguments($args);
                $this->persistHomeChild($args, 'add', $this->homeContactPayload($data));
            }), 'Add item', AdminDialogSize::Large);
    }

    public function editHomeChildAction(): Action
    {
        $action = Action::make('editHomeChild')->label('Edit')
            ->fillForm(function (array $arguments): array {
                [, , $child] = $this->homeActionChild($arguments);
                return [
                    ...$child,
                    'child_type' => $child['type'] ?? null,
                    'publication_state' => CustomPageSetting::contactChildPublished($child) ? 'published' : 'unpublished',
                ];
            })
            ->schema(fn (array $arguments): array => $this->homeContactFields(false, $arguments))
            ->extraModalFooterActions(fn (array $arguments): array => $this->homeChildHeaderActions($arguments))
            ->action(function (array $data, array $arguments): void {
                [, , $child] = $this->homeActionChild($arguments);
                $args = $this->homeChildArgs($arguments);
                $payload = $this->homeContactPayload([...$data, 'child_type' => $child['type']]);
                $payload['published'] = CustomPageSetting::contactChildPublished($child);
                $this->persistHomeChild($args, 'update', $payload);
            });

        return AdminDialog::edit($action, AdminDialogSize::Large);
    }

    /** @return list<Action>
     * @param array<string,mixed> $arguments
     */
    private function homeChildHeaderActions(array $arguments): array
    {
        [$parent, $key, $child] = $this->homeActionChild($arguments);
        $args = $this->homeChildArgs($arguments);
        $children = (new CustomPageSetting)->contactChildren($parent);
        $index = array_search($key, array_column($children, 'type'), true);
        $actions = [];
        foreach (['up' => AdminRowAction::MoveUp, 'down' => AdminRowAction::MoveDown] as $direction => $role) {
            $valid = $this->componentReorderEnabled() && ($direction === 'up' ? $index > 0 : $index < count($children) - 1);
            $actions[] = AdminDialog::headerAction('homeChildMove'.ucfirst($direction), $role, function () use ($args, $direction): void {
                $this->persistHomeChild($args, 'move_'.$direction);
                $this->replaceMountedAction('editHomeChild', $args);
            })->disabled(! $valid);
        }
        $published = CustomPageSetting::contactChildPublished($child);
        $actions[] = AdminDialog::headerAction('homeChildPublication',
            $published ? AdminRowAction::Unpublish : AdminRowAction::Publish,
            function () use ($args, $published): void {
                $this->persistHomeChild($args, 'publish', ['published' => ! $published]);
                $this->replaceMountedAction('editHomeChild', $args);
            });
        $actions[] = AdminDialog::headerAction('homeChildDelete', AdminRowAction::Delete, function () use ($args): void {
            $this->replaceMountedAction('deleteHomeChild', $args);
        });

        return $actions;
    }

    public function deleteHomeChildAction(): Action
    {
        return AdminDialog::confirm(Action::make('deleteHomeChild')->label('Delete')->color('danger')
            ->action(function (array $arguments): void {
                $this->homeActionChild($arguments);
                $this->persistHomeChild($this->homeChildArgs($arguments), 'delete');
            }), 'Delete item?', submitLabel: 'Delete', danger: true);
    }

    public function sortHomeChild(string $target, int $position): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        $parts = explode(':', $target, 3);
        if (count($parts) !== 3 || $parts[0] !== 'contact' || ! ctype_digit($parts[1])) {
            throw ValidationException::withMessages(['component' => 'This Home child changed. Reload and try again.']);
        }
        $args = ['index' => (int) $parts[1], 'type' => 'contact', 'child' => $parts[2]];
        $this->homeActionChild($args);
        $this->persistHomeChild($args, 'reposition', ['position' => $position]);
    }

    public function moveHomeChild(int $index, string $type, int|string $child, string $direction): void
    {
        if (! $this->componentReorderEnabled() || ! in_array($direction, ['up', 'down'], true)) {
            return;
        }
        $this->homeActionChild(['index' => $index, 'type' => $type, 'child' => $child]);
        $this->persistHomeChild(['index' => $index, 'type' => $type, 'child' => $child], 'move_'.$direction);
    }

    public function publishHomeChild(int $index, string $type, int|string $child, bool $published): void
    {
        $this->homeActionChild(['index' => $index, 'type' => $type, 'child' => $child]);
        $this->persistHomeChild(['index' => $index, 'type' => $type, 'child' => $child], 'publish', ['published' => $published]);
    }

    /** @param array<string,mixed> $arguments
     * @return list<mixed>
     */
    private function homeContactFields(bool $creating, array $arguments): array
    {
        $all = [
            'public_email' => 'Public Email', 'social_links' => 'Social Media Links', 'contact_form' => 'Contact Form',
        ];
        if ($creating) {
            $parent = $this->componentFromArguments($arguments);
            $present = array_column((new CustomPageSetting)->contactChildren($parent), 'type');
            $options = array_diff_key($all, array_flip($present));
        } else {
            $options = $all;
        }

        $platforms = array_column(SocialLinks::visible(PublicContentSetting::general()->getAttribute('social_links')), 'platform');
        $platformOptions = array_intersect_key(SocialLinks::options(), array_flip($platforms));

        return [
            Grid::make()->columns(['md' => 2])->schema([
                $creating
                    ? Select::make('child_type')->label('Contact item')->options($options)->required()->live()
                    : Hidden::make('child_type'),
                $creating
                    ? Select::make('publication_state')->label('Status')->options(['published' => 'Published', 'unpublished' => 'Unpublished'])->default('published')->required()
                    : Hidden::make('publication_state'),
                Grid::make()->columns(['md' => 2])->schema(fn (Get $get): array => match ((string) $get('child_type')) {
                    'social_links' => [
                        $this->homeContactEditField(
                            Select::make('social_platforms')->label('Social links from General')
                                ->options($platformOptions)->multiple()->default($platforms)->columnSpanFull(),
                            $creating, $arguments, 'social_platforms',
                        ),
                    ],
                    'contact_form' => [
                        $this->homeContactEditField(
                            Select::make('form_state')->label('Form presentation')
                                ->options(['enabled' => 'Enabled', 'under_construction' => 'Under construction'])
                                ->required()->default('enabled')->live(),
                            $creating, $arguments, 'form_state',
                        ),
                        $this->homeContactEditField(
                            TextInput::make('status_text')->label('Status text')->maxLength(500)
                                ->required(fn (Get $get): bool => $get('form_state') === 'under_construction')
                                ->visible(fn (Get $get): bool => $get('form_state') === 'under_construction'),
                            $creating, $arguments, 'status_text', blur: true,
                        ),
                    ],
                    default => [],
                })->columnSpanFull(),
            ]),
        ];
    }

    /** As with Custom Pages, a native modal-window change event does not
     * consistently capture Filament's multiple-select and conditional fields. */
    private function homeContactEditField(mixed $field, bool $creating, array $arguments, string $key, bool $blur = false): mixed
    {
        if ($creating) return $field;

        return $field->live(onBlur: $blur)
            ->afterStateUpdated(fn (mixed $state) => $this->persistHomeChildEditField($arguments, $key, $state));
    }

    private function persistHomeChildEditField(array $arguments, string $key, mixed $value): void
    {
        [, , $child] = $this->homeActionChild($arguments);
        if (! in_array($key, ['social_platforms', 'form_state', 'status_text'], true)) {
            throw new \InvalidArgumentException('Unsupported Home Contact field.');
        }
        $data = [
            ...$child,
            'child_type' => (string) $child['type'],
            'publication_state' => CustomPageSetting::contactChildPublished($child) ? 'published' : 'unpublished',
            $key => $value,
        ];
        if ($key === 'form_state' || $key === 'status_text') {
            $form = $this->getMountedActionSchema()->getRawState();
            $data['form_state'] = $key === 'form_state'
                ? $value : ($form['form_state'] ?? $data['form_state']);
            $data['status_text'] = $key === 'status_text'
                ? $value : ($form['status_text'] ?? $data['status_text']);
            // Keep the unfinished presentation in the mounted form until
            // status text is entered; save both values in one mutation.
            if ($data['form_state'] === 'under_construction' && blank($data['status_text'])) return;
        }
        try {
            $this->persistHomeChild($this->homeChildArgs($arguments), 'update', $this->homeContactPayload($data));
        } catch (ValidationException $exception) {
            $path = $this->getMountedActionSchema()->getStatePath();
            $errors = [];
            foreach ($exception->errors() as $field => $messages) $errors[$path.'.'.$field] = $messages;
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function homeContactPayload(array $data): array
    {
        return match ($data['child_type'] ?? null) {
            'public_email' => ['type' => 'public_email', 'published' => ($data['publication_state'] ?? 'published') === 'published'],
            'social_links' => ['type' => 'social_links', 'published' => ($data['publication_state'] ?? 'published') === 'published',
                'social_platforms' => $this->validatedHomeSocialPlatforms($data['social_platforms'] ?? [])],
            'contact_form' => ['type' => 'contact_form', 'published' => ($data['publication_state'] ?? 'published') === 'published',
                'form_state' => $data['form_state'] ?? 'enabled', 'status_text' => $data['status_text'] ?? null],
            default => throw ValidationException::withMessages(['component' => 'Choose a supported Contact item.']),
        };
    }

    /** @return list<string> */
    private function validatedHomeSocialPlatforms(mixed $selected): array
    {
        if (! is_array($selected)) {
            return [];
        }

        $configured = array_column(SocialLinks::configured(
            PublicContentSetting::general()->getAttribute('social_links'),
        ), 'platform');

        foreach ($selected as $platform) {
            if (! is_string($platform) || ! in_array($platform, $configured, true)) {
                throw ValidationException::withMessages([
                    'social_platforms' => 'Choose only social links still configured in General.',
                ]);
            }
        }

        return array_values(array_unique($selected));
    }
}
