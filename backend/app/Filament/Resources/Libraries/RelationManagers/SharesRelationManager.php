<?php

namespace App\Filament\Resources\Libraries\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SharesRelationManager extends RelationManager
{
    protected static string $relationship = 'shares';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Shared with');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label(__('User'))->placeholder(__('—')),
                TextColumn::make('group.name')->label(__('Group'))->placeholder(__('—')),
                TextColumn::make('node.name')->label(__('Folder'))->placeholder(__('(whole library)')),
                TextColumn::make('permission')->label(__('Permission'))->badge()->formatStateUsing(fn ($state) => $state === 'rw' ? __('Read / Write') : __('Read only')),
                TextColumn::make('sharer.name')->label(__('Shared by')),
                TextColumn::make('created_at')->label(__('Created At'))->dateTime(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }
}
