/**
 * Tipe data halaman Admin (Users, Roles, Permissions).
 * Bentuknya harus sama dengan array yang dikirim controller Laravel.
 */

/** Hasil paginate() Laravel setelah dikirim lewat Inertia. */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

export type Option = { value: string; label: string };

export type AdminUserRow = {
    id: number;
    /** Random id for admin URLs (edit, update, delete). */
    ulid: string;
    name: string;
    email: string;
    email_verified: boolean;
    is_active: boolean;
    roles: string[];
    created_at: string;
};

export type AdminUserForm = {
    id: number;
    /** Random id for admin URLs (edit, update, delete). */
    ulid: string;
    name: string;
    email: string;
    is_active: boolean;
    roles: string[];
    is_self: boolean;
    active_devices: number;
};

export type UserFilters = {
    search: string;
    role: string;
    status: string;
    sort: string;
    per_page: number;
};

export type RoleRow = {
    id: number;
    /** Random id for admin URLs (edit, update, delete). */
    ulid: string;
    name: string;
    users_count: number;
    permissions_count: number;
    is_system: boolean;
    is_locked: boolean;
};

export type RoleForm = {
    id: number;
    /** Random id for admin URLs (edit, update, delete). */
    ulid: string;
    name: string;
    permissions: string[];
    is_system: boolean;
    is_locked: boolean;
};

export type PermissionItem = {
    name: string;
    action: string;
    description: string;
    roles?: string[];
    /** Built-in menus that follow this permission (Access Control page). */
    menus?: string[];
    /** REST API endpoints this permission unlocks, e.g. "GET /api/v1/messages". */
    endpoints?: string[];
};

/** A row of the "Menu visibility" matrix (custom sidebar menus). */
export type MenuVisibilityRow = {
    id: number;
    /** Random id for admin URLs (edit, update, delete). */
    ulid: string;
    title: string;
    parent_title: string | null;
    depth: 0 | 1;
    url: string | null;
    is_active: boolean;
    /** true = every signed-in user (no role restriction). */
    everyone: boolean;
    roles: string[];
};

/** A column of the Access Control matrix. */
export type AccessRole = {
    id: number;
    /** Random id for admin URLs (edit, update, delete). */
    ulid: string;
    name: string;
    is_admin: boolean;
    is_system: boolean;
    users_count: number;
    permissions: string[];
};

export type PermissionGroup = {
    module: string;
    label: string;
    permissions: PermissionItem[];
};
