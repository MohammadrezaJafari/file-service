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
                TextColumn::make('created_at')->label(__('Created At'))->dateTime()->sortable(),
                TextColumn::make('user.name')->label(__('User'))->placeholder(__('Anonymous'))->searchable(),
                TextColumn::make('action')->label(__('Action'))->badge()->searchable(),
                TextColumn::make('library.name')->label(__('Library'))->searchable(),
                TextColumn::make('path')->label(__('Path'))->searchable()->limit(60),
                TextColumn::make('ip')->label(__('IP'))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->label(__('Action'))->options(fn () => Activity::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
                SelectFilter::make('user')->label(__('User'))->relationship('user', 'name')->searchable()->preload(),
            ]);
    }
}
