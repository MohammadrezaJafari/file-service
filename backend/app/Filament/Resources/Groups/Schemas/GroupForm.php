<?php

namespace App\Filament\Resources\Groups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                Select::make('owner_id')->label('Owner')->relationship('owner', 'name')->searchable(['name', 'email'])->preload()->required(),
                Textarea::make('description')->rows(3)->columnSpanFull(),
            ]);
    }
}
