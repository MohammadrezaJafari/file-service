<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Account'))
                    ->columns(2)
                    ->components([
                        TextInput::make('name')->label(__('Name'))->required()->maxLength(255),
                        TextInput::make('email')->label(__('Email address'))->email()->required()->unique(ignoreRecord: true),
                        TextInput::make('password')->label(__('Password'))
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->minLength(8)
                            ->helperText(__('Leave blank to keep the current password.')),
                    ]),
                Section::make(__('Permissions & quota'))
                    ->columns(2)
                    ->components([
                        Toggle::make('is_admin')->label(__('Administrator'))->helperText(__('Admins can access this panel and every library.')),
                        Toggle::make('is_active')->label(__('Active'))->default(true),
                        TextInput::make('quota_mb')
                            ->label(__('Quota (MB)'))
                            ->numeric()
                            ->minValue(0)
                            ->helperText(__('Leave blank to use the default quota from Settings.'))
                            ->afterStateHydrated(function (TextInput $component, $record) {
                                $component->state($record?->quota_bytes !== null ? round($record->quota_bytes / 1048576) : null);
                            })
                            ->dehydrated(false),
                    ]),
            ]);
    }
}
