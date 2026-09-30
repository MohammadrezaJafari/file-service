<?php

namespace App\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Generates an inline SVG avatar with the user's initials, so the panel does
 * not depend on an external avatar service.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $user): string
    {
        $name = trim((string) ($user->name ?? '?'));
        $initials = mb_strtoupper(mb_substr($name, 0, 1));
        $hash = crc32($name);
        $hue = $hash % 360;
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><rect width="64" height="64" rx="32" fill="hsl({$hue},55%,45%)"/><text x="32" y="41" font-family="sans-serif" font-size="28" fill="#fff" text-anchor="middle">{$initials}</text></svg>
SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
