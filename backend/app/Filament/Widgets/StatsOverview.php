<?php

namespace App\Filament\Widgets;

use App\Models\Library;
use App\Models\Node;
use App\Models\ShareLink;
use App\Models\User;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Users', User::count())
                ->description(User::where('is_active', true)->count().' active'),
            Stat::make('Libraries', Library::count()),
            Stat::make('Files', Node::files()->count())
                ->description(Format::bytes((int) Library::sum('size_bytes')).' stored'),
            Stat::make('Share links', ShareLink::count())
                ->description(ShareLink::sum('download_count').' downloads'),
        ];
    }
}
