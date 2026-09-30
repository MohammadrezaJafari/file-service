<?php

namespace App\Filament\Resources\Libraries\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LibraryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('Name'))->required()->maxLength(255),
                Select::make('owner_id')
                    ->label(__('Owner'))
                    ->relationship('owner', 'name')
                    ->searchable(['name', 'email'])
                    ->preload()
                    ->required(),
                Textarea::make('description')->label(__('Description'))->rows(3)->columnSpanFull(),
            ]);
    }
}
