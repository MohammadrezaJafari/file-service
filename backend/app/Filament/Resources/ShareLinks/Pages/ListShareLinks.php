<?php

namespace App\Filament\Resources\ShareLinks\Pages;

use App\Filament\Resources\ShareLinks\ShareLinkResource;
use Filament\Resources\Pages\ListRecords;

class ListShareLinks extends ListRecords
{
    protected static string $resource = ShareLinkResource::class;
}
