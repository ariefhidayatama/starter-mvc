<?php

namespace App\Enums;

/**
 * Tempat sebuah menu ditampilkan.
 *
 * - Admin   → sidebar dashboard (/dashboard, /admin/...)
 * - Website → navbar halaman depan (publik)
 */
enum MenuLocation: string
{
    case Admin = 'admin';
    case Website = 'website';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Dashboard sidebar',
            self::Website => 'Website navbar',
        };
    }
}
