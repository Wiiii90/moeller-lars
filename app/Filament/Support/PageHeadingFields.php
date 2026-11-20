<?php

namespace App\Filament\Support;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\SitePageHeading;
use App\Filament\Support\Controls\AdminControl;
use App\Filament\Support\Controls\AdminPanelControl;
use Closure;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/** Shared heading panel for Pages creation and editing. */
final class PageHeadingFields
{
    /**
     * The shared disclosure panel owns Page headings as well as Gallery options.
     * Editing autosaves each field; Create saves once with the new page.
     */
    public static function schema(?Closure $persist = null, string $successTitle = 'Page updated'): Group
    {
        [$heading, $alignment, $divider] = self::detailFields($persist, $successTitle);

        return AdminPanelControl::make('Show page heading', 'content_heading_enabled', [
            $heading,
            Grid::make(['default' => 2])->schema([$alignment, $divider]),
        ], $persist === null ? null : fn (Component $livewire, mixed $state) =>
            self::persist($livewire, $persist, 'content_heading_enabled', (bool) $state, $successTitle),
            collapsible: true,
            bodyColumns: ['default' => 1, 'md' => 2],
        );
    }

    /** @return list<mixed> */
    public static function detailFields(?Closure $persist = null, string $successTitle = 'Page updated'): array
    {
        return [
            AdminControl::text('content_heading_override')
                ->label('Heading text')
                ->placeholder('Use page name')
                ->maxLength(160)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($livewire, mixed $state) => self::persist($livewire, $persist, 'content_heading_override', $state, $successTitle)),
            AdminControl::select('content_heading_alignment')
                ->label('Alignment')
                ->options(SitePageHeading::ALIGNMENTS)
                ->default('left')
                ->native()
                ->live()
                ->afterStateUpdated(fn ($livewire, mixed $state) => self::persist($livewire, $persist, 'content_heading_alignment', $state, $successTitle)),
            AdminControl::select('content_heading_divider')
                ->label('Divider')
                ->options(SitePageHeading::DIVIDERS)
                ->default('none')
                ->native()
                ->live()
                ->afterStateUpdated(fn ($livewire, mixed $state) => self::persist($livewire, $persist, 'content_heading_divider', $state, $successTitle)),
        ];
    }

    /**
     * Heading fields have one autosave owner. A no-op never produces a second
     * Notification, even when the containing Edit modal also receives change.
     */
    private static function persist(Component $livewire, ?Closure $persist, string $field, mixed $value, string $title): void
    {
        if ($persist === null) {
            return; // Add Page persists heading fields only after creation.
        }
        if (! $livewire instanceof HasActions || ($schemaName = $livewire->getMountedActionSchemaName()) === null) {
            throw new \LogicException('Heading autosave requires a mounted action schema.');
        }

        $path = $livewire->{$schemaName}->getStatePath();
        $livewire->resetErrorBag($path.'.'.$field);
        try {
            if ($persist($field, $value) !== true) {
                return;
            }
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $errors[$path.'.'.$key] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }

        app(AdminNotifier::class)->notification(title: $title, status: 'success');
    }
}
