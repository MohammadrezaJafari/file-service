<?php

namespace App\Filament\Widgets;

use App\Models\Activity;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentActivity extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Recent activity'))
            ->query(Activity::query()->with(['user', 'library'])->latest('id')->limit(15))
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')->label(__('Created At'))->since(),
                TextColumn::make('user.name')->label(__('User'))->placeholder(__('Anonymous')),
                TextColumn::make('action')->label(__('Action'))->badge(),
                TextColumn::make('library.name')->label(__('Library')),
                TextColumn::make('path')->label(__('Path'))->limit(60),
            ]);
    }
}
