<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Models\Activity;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('User')->placeholder('Anonymous')->searchable(),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('library.name')->label('Library')->searchable(),
                TextColumn::make('path')->searchable()->limit(60),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->options(fn () => Activity::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
                SelectFilter::make('user')->relationship('user', 'name')->searchable()->preload(),
            ]);
    }
}
