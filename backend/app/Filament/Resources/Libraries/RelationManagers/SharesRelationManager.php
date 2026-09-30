<?php

namespace App\Filament\Resources\Libraries\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SharesRelationManager extends RelationManager
{
    protected static string $relationship = 'shares';

    protected static ?string $title = 'Shared with';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('User')->placeholder('—'),
                TextColumn::make('group.name')->label('Group')->placeholder('—'),
                TextColumn::make('node.name')->label('Folder')->placeholder('(whole library)'),
                TextColumn::make('permission')->badge()->formatStateUsing(fn ($state) => $state === 'rw' ? 'Read / Write' : 'Read only'),
                TextColumn::make('sharer.name')->label('Shared by'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }
}
