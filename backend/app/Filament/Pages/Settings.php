<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Settings');
    }

    public function getTitle(): string
    {
        return __('Settings');
    }

    public function mount(): void
    {
        $defaults = Setting::defaults();
        $this->form->fill([
            'site_name' => Setting::get('site_name', $defaults['site_name']),
            'registration_enabled' => (bool) Setting::get('registration_enabled', $defaults['registration_enabled']),
            'share_links_enabled' => (bool) Setting::get('share_links_enabled', $defaults['share_links_enabled']),
            'default_quota_mb' => (int) Setting::get('default_quota_bytes', 0) > 0 ? round((int) Setting::get('default_quota_bytes') / 1048576) : null,
            'max_upload_mb' => round((int) Setting::get('max_upload_bytes', $defaults['max_upload_bytes']) / 1048576),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('General'))->columns(2)->components([
                    TextInput::make('site_name')->label(__('Site Name'))->required()->maxLength(100),
                    Toggle::make('registration_enabled')->label(__('Allow self-registration'))->inline(false),
                    Toggle::make('share_links_enabled')->label(__('Allow public share links'))->inline(false),
                ]),
                Section::make(__('Storage'))->columns(2)->components([
                    TextInput::make('default_quota_mb')->label(__('Default user quota (MB)'))->numeric()->minValue(0)->helperText(__('Blank = unlimited. Per-user quotas override this.')),
                    TextInput::make('max_upload_mb')->label(__('Max upload size (MB)'))->numeric()->minValue(1)->required(),
                ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('site_name', $data['site_name']);
        Setting::set('registration_enabled', $data['registration_enabled'] ? '1' : '0');
        Setting::set('share_links_enabled', $data['share_links_enabled'] ? '1' : '0');
        Setting::set('default_quota_bytes', (string) ((int) ($data['default_quota_mb'] ?? 0) * 1048576));
        Setting::set('max_upload_bytes', (string) ((int) $data['max_upload_mb'] * 1048576));

        Notification::make()->title(__('Settings saved'))->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label(__('Save'))->action('save'),
        ];
    }
}
