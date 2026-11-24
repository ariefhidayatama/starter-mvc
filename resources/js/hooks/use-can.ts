import { usePage } from '@inertiajs/react';

/**
 * Cek izin user yang sedang login di sisi React.
 *
 *   const can = useCan();
 *   {can('users.create') && <Button>Tambah</Button>}
 *
 * PENTING: ini hanya untuk MENYEMBUNYIKAN tombol/menu. Keamanan yang
 * sebenarnya tetap dijaga Policy di Laravel; menyembunyikan tombol tidak
 * mencegah orang mengirim request langsung.
 */
export function useCan(): (permission: string) => boolean {
    const { auth } = usePage().props;

    return (permission: string) =>
        auth.roles.includes('admin') || auth.permissions.includes(permission);
}
