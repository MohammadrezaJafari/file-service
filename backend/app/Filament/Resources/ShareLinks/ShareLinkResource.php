<?php

namespace App\Filament\Resources\ShareLinks;

use App\Filament\Resources\ShareLinks\Pages\EditShareLink;
use App\Filament\Resources\ShareLinks\Pages\ListShareLinks;
use App\Filament\Resources\ShareLinks\Schemas\ShareLinkForm;
use App\Filament\Resources\ShareLinks\Tables\ShareLinksTable;
use App\Models\ShareLink;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ShareLinkResource extends Resource
{
    protected static ?string $model = ShareLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('Share link');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Share links');
    }

    public static function form(Schema $schema): Schema
    {
        return ShareLinkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShareLinksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShareLinks::route('/'),
            'edit' => EditShareLink::route('/{record}/edit'),
        ];
    }
}
