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
                TextColumn::make('token')->copyable()->searchable(),
                TextColumn::make('kind')->badge(),
                TextColumn::make('library.name')->label('Library')->searchable(),
                TextColumn::make('node.name')->label('Item')->placeholder('(whole library)'),
                TextColumn::make('creator.name')->label('Created by'),
                IconColumn::make('has_password')->label('Password')->boolean()->state(fn ($record) => $record->hasPassword()),
                TextColumn::make('expires_at')->dateTime()->placeholder('Never'),
                TextColumn::make('view_count')->label('Views')->numeric(),
                TextColumn::make('download_count')->label('Downloads')->numeric(),
            ])
            ->filters([
                SelectFilter::make('kind')->options(['download' => 'Download', 'upload' => 'Upload']),
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
