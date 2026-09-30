<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Support\Format;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->components([
                        TextEntry::make('name'),
                        TextEntry::make('email')->label('Email address'),
                        IconEntry::make('is_admin')->boolean()->label('Administrator'),
                        IconEntry::make('is_active')->boolean()->label('Active'),
                        TextEntry::make('used_bytes')->label('Used')->formatStateUsing(fn ($state) => Format::bytes($state)),
                        TextEntry::make('quota_bytes')->label('Quota')->state(fn ($record) => Format::bytes($record->effectiveQuota())),
                        TextEntry::make('libraries_count')->label('Libraries')->state(fn ($record) => $record->libraries()->count()),
                        TextEntry::make('last_login_at')->dateTime(),
                        TextEntry::make('created_at')->dateTime(),
                    ]),
            ]);
    }
}
