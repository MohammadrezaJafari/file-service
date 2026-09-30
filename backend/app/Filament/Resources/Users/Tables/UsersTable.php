<?php

namespace App\Filament\Resources\Users\Tables;

use App\Support\Format;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('email')->label(__('Email address'))->searchable()->sortable(),
                IconColumn::make('is_admin')->label(__('Admin'))->boolean(),
                IconColumn::make('is_active')->label(__('Active'))->boolean(),
                TextColumn::make('used_bytes')->label(__('Used'))->formatStateUsing(fn ($state) => Format::bytes($state))->sortable(),
                TextColumn::make('quota_bytes')->label(__('Quota'))->state(fn ($record) => Format::bytes($record->effectiveQuota())),
                TextColumn::make('libraries_count')->label(__('Libraries'))->counts('libraries')->sortable(),
                TextColumn::make('last_login_at')->label(__('Last Login At'))->dateTime()->sortable()->toggleable(),
                TextColumn::make('created_at')->label(__('Created At'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_admin')->label(__('Administrators')),
                TernaryFilter::make('is_active')->label(__('Active')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('toggleActive')
                    ->label(fn ($record) => $record->is_active ? __('Deactivate') : 'Activate')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['is_active' => ! $record->is_active]);
                        if (! $record->is_active) {
                            $record->tokens()->delete();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
