<?php

namespace App\Filament\Resources\ShareLinks\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ShareLinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('expires_at'),
                Toggle::make('allow_download'),
            ]);
    }
}
