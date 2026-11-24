/**
 * Menu types. Shapes match App\Services\Menu\MenuTreeService (tree nodes)
 * and App\Http\Controllers\Admin\MenuController (management rows).
 */

export type MenuLocation = 'admin' | 'website';

/** A node of the menu tree shared on every page (`menus.admin` / `menus.website`). */
export type MenuNode = {
    id: number;
    title: string;
    /** null = top-level item that only opens its submenu. */
    url: string | null;
    icon: string | null;
    open_in_new_tab: boolean;
    children: MenuNode[];
};

export type SharedMenus = {
    admin: MenuNode[];
    website: MenuNode[];
};

/**
 * Who sees a menu:
 * - public     → website (everyone, including guests)
 * - everyone   → every signed-in user
 * - roles      → only the listed roles (admin always included)
 * - permission → built-in menu: roles that have `permission`
 */
export type MenuAudience = {
    type: 'public' | 'everyone' | 'roles' | 'permission';
    roles: string[];
    permission: string | null;
};

export type Menu = {
    id: number;
    /** Random id for admin URLs, e.g. /admin/menus/01jd…/edit. */
    ulid: string;
    location: MenuLocation;
    parent_id: number | null;
    title: string;
    url: string | null;
    icon: string | null;
    /** Built-in menus only: the feature permission the menu follows. */
    permission: string | null;
    open_in_new_tab: boolean;
    sort_order: number;
    is_active: boolean;
    is_system: boolean;
    /** Roles allowed to see the menu (empty = everyone). */
    roles: string[];
    audience?: MenuAudience;
};

/** A row in the Admin → Menus list (parent followed by its submenus). */
export type MenuRow = Menu & {
    depth: 0 | 1;
    children_count: number;
    audience: MenuAudience;
    /** Set when this menu's page came from the CRUD generator (local only). */
    generated: GeneratedCrud | null;
};

export type GeneratedCrud = {
    /** Module key (permissions, manifest), e.g. "suppliers". */
    table: string;
    /** Real database table, e.g. "supplier" for an existing table. */
    db_table: string;
    /** false = the table existed before, so it is never dropped. */
    can_drop: boolean;
};

export type CrudColumn = {
    name: string;
    type: string;
    required: boolean;
    options: string[];
    max: number | null;
    unsigned: boolean;
    references: string | null;
};

/** Audit columns found in an existing table (filled automatically). */
export type CrudAudit = {
    created_at: string | null;
    updated_at: string | null;
    created_by: string | null;
    updated_by: string | null;
    user_max: number;
};

/** A table with a foreign key to the chosen table (master–detail). */
export type CrudChildTable = {
    table: string;
    /** The foreign key column, e.g. msg_id. */
    column: string;
    fields: CrudColumn[];
    problem: string | null;
};

/** An existing database table that can get CRUD pages. */
export type CrudTable = {
    name: string;
    fields: CrudColumn[];
    skipped: { name: string; reason: string }[];
    timestamps: boolean;
    audit: CrudAudit;
    children: CrudChildTable[];
    /** Why it cannot be used (e.g. no auto-increment "id"), or null. */
    problem: string | null;
};

/** Options for the "Generate CRUD page" section (null outside development). */
export type CrudGeneratorOptions = {
    types: string[];
    maxFields: number;
    /** Max detail tables per resource (master–detail, one level). */
    maxDetails: number;
    /** Upper limit for a Short text length (longer → Long text). */
    maxStringLength: number;
    /** value '' = the app database; others e.g. "wmsreports" (SQL Server). */
    connections: { value: string; label: string }[];
};

/** Sidebar "view as role" preview, including hidden items and why. */
export type MenuPreviewNode = {
    id: number;
    title: string;
    url: string | null;
    icon: string | null;
    visible: boolean;
    reason: string | null;
    children: MenuPreviewNode[];
};

export type MenuPreview = {
    role: string;
    tree: MenuPreviewNode[];
};
