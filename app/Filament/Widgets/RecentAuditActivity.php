<?php

namespace App\Filament\Widgets;

use App\Models\AuditEvent;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class RecentAuditActivity extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent editorial activity')
            ->description('Latest changes recorded by the append-only admin audit log.')
            ->query(AuditEvent::query()->with('adminUser'))
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('action')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace(['.', '_'], [' · ', ' '], $state)),
                TextColumn::make('entity_type')
                    ->label('Area')
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),
                TextColumn::make('entity_id')
                    ->label('Record'),
                TextColumn::make('adminUser.name')
                    ->label('By'),
                TextColumn::make('occurred_at')
                    ->label('When')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->paginated([5, 10, 25]);
    }
}
