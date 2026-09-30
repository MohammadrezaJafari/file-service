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
            ->heading('Recent activity')
            ->query(Activity::query()->with(['user', 'library'])->latest('id')->limit(15))
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')->since(),
                TextColumn::make('user.name')->placeholder('Anonymous'),
                TextColumn::make('action')->badge(),
                TextColumn::make('library.name'),
                TextColumn::make('path')->limit(60),
            ]);
    }
}
