<?php

namespace App\Filament\Resources\Libraries\RelationManagers;

use App\Models\Node;
use App\Services\NodeService;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class NodesRelationManager extends RelationManager
{
    protected static string $relationship = 'nodes';

    protected static ?string $title = 'Files & folders';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([SoftDeletingScope::class]))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->icon(fn (Node $record) => $record->isFolder() ? 'heroicon-o-folder' : 'heroicon-o-document')
                    ->description(fn (Node $record) => $record->deleted_from_path ?? $record->path()),
                TextColumn::make('type')->badge(),
                TextColumn::make('size')->formatStateUsing(fn ($state) => Format::bytes($state))->sortable(),
                TextColumn::make('version_number')->label('Ver.'),
                TextColumn::make('updater.name')->label('Modified by'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
                TextColumn::make('deleted_at')->label('Trashed')->dateTime()->placeholder('—'),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('type')->options(['folder' => 'Folder', 'file' => 'File']),
            ])
            ->recordActions([
                Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Node $record) => $record->isFile() && $record->deleted_at === null)
                    ->url(fn (Node $record) => route('admin.nodes.download', $record), shouldOpenInNewTab: true),
                Action::make('restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (Node $record) => $record->deleted_at !== null && $record->deleted_by !== null)
                    ->action(fn (Node $record) => app(NodeService::class)->restore($record, auth()->user())),
                Action::make('purge')
                    ->label('Delete permanently')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Node $record) => $record->deleted_at !== null && $record->deleted_by !== null)
                    ->action(fn (Node $record) => app(NodeService::class)->purge($record, auth()->user())),
                Action::make('trash')
                    ->label('Move to trash')
                    ->icon('heroicon-o-trash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Node $record) => $record->deleted_at === null)
                    ->action(fn (Node $record) => app(NodeService::class)->trash($record, auth()->user())),
            ]);
    }
}
