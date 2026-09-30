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
            Stat::make(__('Users'), User::count())
                ->description(User::where('is_active', true)->count().' '.__('active')),
            Stat::make(__('Libraries'), Library::count()),
            Stat::make(__('Files'), Node::files()->count())
                ->description(Format::bytes((int) Library::sum('size_bytes')).' '.__('stored')),
            Stat::make(__('Share links'), ShareLink::count())
                ->description(ShareLink::sum('download_count').' '.__('downloads')),
        ];
    }
}
