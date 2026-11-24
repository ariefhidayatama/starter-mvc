<?php

namespace App\Enums;

/**
 * Role bawaan sistem.
 *
 * Role lain boleh dibuat bebas dari menu Roles. Ketiga role ini "sistem":
 * namanya dipakai oleh kode, jadi tidak boleh diganti nama atau dihapus.
 */
enum RoleName: string
{
    /** Super admin: lolos semua pemeriksaan izin (Gate::before). */
    case Admin = 'admin';

    case Staff = 'staff';

    /** Diberikan otomatis saat user mendaftar sendiri. */
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Staff => 'Staff',
            self::Customer => 'Customer',
        };
    }

    public static function isSystem(string $roleName): bool
    {
        return self::tryFrom($roleName) !== null;
    }
}
