<?php

namespace App\Filament\Resources\Libraries\Tables;

use App\Models\Library;
use App\Services\NodeService;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class LibrariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('owner.name')->label('Owner')->searchable()->sortable(),
                TextColumn::make('size_bytes')->label('Size')->formatStateUsing(fn ($state) => Format::bytes($state))->sortable(),
                TextColumn::make('file_count')->label('Files')->numeric()->sortable(),
                TextColumn::make('shares_count')->label('Shares')->counts('shares'),
                TextColumn::make('share_links_count')->label('Links')->counts('shareLinks'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
                TextColumn::make('deleted_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('owner')->relationship('owner', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('emptyTrash')
                    ->label('Empty trash')
                    ->icon('heroicon-o-trash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Library $record) => $record->deleted_at === null)
                    ->action(fn (Library $record) => app(NodeService::class)->emptyTrash($record, auth()->user())),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
