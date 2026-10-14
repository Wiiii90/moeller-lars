<?php

namespace App\Filament\Support;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\SitePageHeading;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/** Shared form schema for the Pages Edit dialog and Home settings. */
final class PageHeadingFields
{
    /** Persist each heading field through the owner on a Livewire update. */
    public static function schema(?Closure $persist = null, string $successTitle = 'Page updated'): Grid
    {
        return Grid::make()
            ->columns(['md' => 2])
            ->schema([
                Toggle::make('content_heading_enabled')
                    ->label('Show page heading')
                    ->default(false)
                    ->live()
                    ->afterStateUpdated(fn (mixed $state) => self::persist($persist, 'content_heading_enabled', (bool) $state, $successTitle))
                    ->columnSpanFull(),
                TextInput::make('content_heading_override')
                    ->label('Heading text')
                    ->placeholder('Use page name')
                    ->maxLength(160)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (mixed $state) => self::persist($persist, 'content_heading_override', $state, $successTitle))
                    ->visible(fn (Get $get): bool => (bool) $get('content_heading_enabled'))
                    ->columnSpanFull(),
                Select::make('content_heading_alignment')
                    ->label('Alignment')
                    ->options(SitePageHeading::ALIGNMENTS)
                    ->default('left')
                    ->native()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (mixed $state) => self::persist($persist, 'content_heading_alignment', $state, $successTitle))
                    ->visible(fn (Get $get): bool => (bool) $get('content_heading_enabled')),
                Select::make('content_heading_divider')
                    ->label('Divider below heading')
                    ->options(SitePageHeading::DIVIDERS)
                    ->default('none')
                    ->native()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (mixed $state) => self::persist($persist, 'content_heading_divider', $state, $successTitle))
                    ->visible(fn (Get $get): bool => (bool) $get('content_heading_enabled')),
            ]);
    }

    /**
     * Heading fields have one autosave owner. A no-op never produces a second
     * Notification, even when the containing Edit modal also receives change.
     */
    private static function persist(?Closure $persist, string $field, mixed $value, string $title): void
    {
        // Add Page has no persisted SiteSection until submit. Its fields are
        // saved once by createPageFromDialog; Edit continues using autosave.
        if ($persist === null || $persist($field, $value) !== true) {
            return;
        }

        app(AdminNotifier::class)->notification(title: $title, status: 'success');
    }
}
