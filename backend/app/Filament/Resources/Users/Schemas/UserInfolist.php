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
                        TextEntry::make('name')->label(__('Name')),
                        TextEntry::make('email')->label(__('Email address')),
                        IconEntry::make('is_admin')->boolean()->label(__('Administrator')),
                        IconEntry::make('is_active')->boolean()->label(__('Active')),
                        TextEntry::make('used_bytes')->label(__('Used'))->formatStateUsing(fn ($state) => Format::bytes($state)),
                        TextEntry::make('quota_bytes')->label(__('Quota'))->state(fn ($record) => Format::bytes($record->effectiveQuota())),
                        TextEntry::make('libraries_count')->label(__('Libraries'))->state(fn ($record) => $record->libraries()->count()),
                        TextEntry::make('last_login_at')->label(__('Last Login At'))->dateTime(),
                        TextEntry::make('created_at')->label(__('Created At'))->dateTime(),
                    ]),
            ]);
    }
}
