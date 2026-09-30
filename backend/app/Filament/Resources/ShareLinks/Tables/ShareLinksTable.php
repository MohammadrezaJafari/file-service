<?php

namespace App\Filament\Resources\ShareLinks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ShareLinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('token')->label(__('Token'))->copyable()->searchable(),
                TextColumn::make('kind')->label(__('Kind'))->badge(),
                TextColumn::make('library.name')->label(__('Library'))->searchable(),
                TextColumn::make('node.name')->label(__('Item'))->placeholder(__('(whole library)')),
                TextColumn::make('creator.name')->label(__('Created by')),
                IconColumn::make('has_password')->label(__('Password'))->boolean()->state(fn ($record) => $record->hasPassword()),
                TextColumn::make('expires_at')->label(__('Expires At'))->dateTime()->placeholder(__('Never')),
                TextColumn::make('view_count')->label(__('Views'))->numeric(),
                TextColumn::make('download_count')->label(__('Downloads'))->numeric(),
            ])
            ->filters([
                SelectFilter::make('kind')->label(__('Kind'))->options(['download' => __('Download'), 'upload' => __('Upload')]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
