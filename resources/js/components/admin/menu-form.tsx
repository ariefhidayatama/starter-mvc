import { Form, Link } from '@inertiajs/react';
import { Eye, KeyRound, Link2, Lock, ShieldCheck, Users } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { CrudGeneratorSection } from '@/components/admin/crud-generator-section';
import {
    CrudGeneratingOverlay,
    forgetPendingCrud,
    newCrudToken,
    rememberPendingCrud,
} from '@/components/admin/crud-generating-overlay';
import InputError from '@/components/input-error';
import { roleTone, StatusBadge } from '@/components/status-badge';
import { ToggleSwitch } from '@/components/toggle-switch';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { menuIcon } from '@/lib/menu-icons';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { CrudGeneratorOptions, Menu, MenuLocation, Option } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/** Radix Select cannot use "" as a value, so "none" stands for empty. */
const NONE = 'none';

function Section({
    icon: Icon,
    title,
    description,
    children,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icon className="size-4.5" />
                </div>
                <div className="space-y-1">
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
            </CardHeader>
            <CardContent className="space-y-5">{children}</CardContent>
        </Card>
    );
}

function RoleBadges({ roles }: { roles: string[] }) {
    return (
        <div className="flex flex-wrap gap-1">
            {roles.map((r) => (
                <StatusBadge key={r} tone={roleTone(r)} dot={false}>
                    {r}
                </StatusBadge>
            ))}
        </div>
    );
}

/** A large radio card. */
function AudienceOption({
    selected,
    onSelect,
    icon: Icon,
    title,
    description,
}: {
    selected: boolean;
    onSelect: () => void;
    icon: LucideIcon;
    title: string;
    description: string;
}) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={selected}
            onClick={onSelect}
            className={cn(
                'flex items-start gap-3 rounded-xl border p-4 text-left transition-all hover:border-primary/50',
                selected && 'border-primary bg-primary/5 ring-1 ring-primary',
            )}
        >
            <span
                className={cn(
                    'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border-[1.5px] border-muted-foreground/45',
                    selected && 'border-primary',
                )}
            >
                {selected && (
                    <span className="size-2 rounded-full bg-primary" />
                )}
            </span>
            <span className="space-y-0.5">
                <span className="flex items-center gap-1.5 font-medium">
                    <Icon className="size-4 text-muted-foreground" />
                    {title}
                </span>
                <span className="block text-sm text-muted-foreground">
                    {description}
                </span>
            </span>
        </button>
    );
}

/** Menu form shared by the Create & Edit pages. */
export function MenuForm({
    action,
    location,
    parentOptions,
    iconOptions,
    roleOptions,
    permissionOptions,
    menu,
    defaultParentId = null,
    hasChildren = false,
    crudGenerator = null,
}: {
    action: RouteFormDefinition<'post'>;
    location: MenuLocation;
    parentOptions: Option[];
    iconOptions: string[];
    roleOptions: string[];
    permissionOptions: Option[];
    menu?: Menu;
    defaultParentId?: number | null;
    hasChildren?: boolean;
    /** Only sent in development, for new sidebar menus. */
    crudGenerator?: CrudGeneratorOptions | null;
}) {
    const isAdmin = location === 'admin';
    const locked = menu?.is_system ?? false;
    const [crudEnabled, setCrudEnabled] = useState(false);
    const [crudToken] = useState(newCrudToken);
    // URL & visibility come from the generated module.
    const crudOn = crudGenerator !== null && crudEnabled;
    // New sidebar menu in development: two columns, generator on the right.
    const showCrud = crudGenerator !== null && !menu;

    const [parentId, setParentId] = useState(
        String(menu?.parent_id ?? defaultParentId ?? NONE),
    );
    const [icon, setIcon] = useState(menu?.icon ?? NONE);
    const [audience, setAudience] = useState<
        'everyone' | 'roles' | 'permission'
    >(
        menu?.permission
            ? 'permission'
            : (menu?.roles.length ?? 0) > 0
              ? 'roles'
              : 'everyone',
    );
    const [roles, setRoles] = useState<string[]>(menu?.roles ?? []);
    const [permission, setPermission] = useState(menu?.permission ?? NONE);
    const toggleRole = (role: string) =>
        setRoles((current) =>
            current.includes(role)
                ? current.filter((r) => r !== role)
                : [...current, role],
        );
    const [newTab, setNewTab] = useState(menu?.open_in_new_tab ?? false);
    const [isActive, setIsActive] = useState(menu?.is_active ?? true);

    const empty = (v: string) => (v === NONE ? '' : v);

    return (
        <Form
            {...action}
            className={showCrud ? 'space-y-6' : 'max-w-3xl space-y-6'}
            onStart={() => crudOn && rememberPendingCrud(crudToken)}
            onFinish={forgetPendingCrud}
        >
            {({ processing, errors }) => (
                <>
                    {crudGenerator && (
                        <CrudGeneratingOverlay
                            submitting={processing && crudOn}
                        />
                    )}

                    {locked && (
                        <Alert>
                            <Lock />
                            <AlertDescription>
                                This is a built-in menu linked to an app
                                feature. Its URL, permission, and status are
                                locked. You can still rename, move, or reorder
                                it.
                            </AlertDescription>
                        </Alert>
                    )}

                    {!menu && (
                        <input type="hidden" name="location" value={location} />
                    )}

                    {/* Two columns on wide screens; the buttons stay BELOW this grid. */}
                    <div
                        className={
                            showCrud
                                ? 'grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]'
                                : 'space-y-6'
                        }
                    >
                        {/* Left column: menu details & visibility. */}
                        <div className="min-w-0 space-y-6">
                            <Section
                                icon={Link2}
                                title="Menu details"
                                description={
                                    isAdmin
                                        ? 'Shown in the dashboard sidebar.'
                                        : 'Shown in the website navbar.'
                                }
                            >
                                <div className="grid items-start gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="title" required>
                                            Title
                                        </Label>
                                        <Input
                                            id="title"
                                            name="title"
                                            defaultValue={menu?.title}
                                            placeholder={
                                                isAdmin ? 'Reports' : 'About us'
                                            }
                                            required
                                        />
                                        <InputError message={errors.title} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="parent" optional>
                                            Parent menu
                                        </Label>
                                        <input
                                            type="hidden"
                                            name="parent_id"
                                            value={empty(parentId)}
                                        />
                                        <Select
                                            value={parentId}
                                            onValueChange={setParentId}
                                            disabled={hasChildren}
                                        >
                                            <SelectTrigger
                                                id="parent"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NONE}>
                                                    None (top-level menu)
                                                </SelectItem>
                                                {parentOptions.map((p) => (
                                                    <SelectItem
                                                        key={p.value}
                                                        value={p.value}
                                                    >
                                                        {p.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <p className="text-xs text-muted-foreground">
                                            {hasChildren
                                                ? 'This menu has submenus, so it must stay top-level.'
                                                : 'Choose a parent to make this a submenu.'}
                                        </p>
                                        <InputError
                                            message={errors.parent_id}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    {/* Submenu wajib punya URL; menu tingkat atas boleh kosong; mode CRUD diisi otomatis. */}
                                    <Label
                                        htmlFor="url"
                                        required={!crudOn && parentId !== NONE}
                                        optional={!crudOn && parentId === NONE}
                                    >
                                        URL
                                    </Label>
                                    <Input
                                        id="url"
                                        name={crudOn ? undefined : 'url'}
                                        defaultValue={menu?.url ?? ''}
                                        placeholder={
                                            crudOn
                                                ? 'Set automatically: /admin/<resource name>'
                                                : isAdmin
                                                  ? '/admin/reports'
                                                  : '/about, #features or https://...'
                                        }
                                        className="font-mono"
                                        disabled={locked || crudOn}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {crudOn
                                            ? 'The generated page decides the URL.'
                                            : isAdmin
                                              ? 'An internal path starting with "/". Leave empty for a menu that only opens its submenus.'
                                              : 'Internal path, page anchor, or full external link. Leave empty for a dropdown-only menu.'}
                                    </p>
                                    <InputError message={errors.url} />
                                </div>

                                <div className="grid items-start gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="icon" optional>
                                            Icon
                                        </Label>
                                        <input
                                            type="hidden"
                                            name="icon"
                                            value={empty(icon)}
                                        />
                                        <Select
                                            value={icon}
                                            onValueChange={setIcon}
                                        >
                                            <SelectTrigger
                                                id="icon"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent className="max-h-72">
                                                <SelectItem value={NONE}>
                                                    No icon
                                                </SelectItem>
                                                {iconOptions.map((name) => {
                                                    const Icon = menuIcon(name);

                                                    return (
                                                        <SelectItem
                                                            key={name}
                                                            value={name}
                                                        >
                                                            {Icon && <Icon />}
                                                            {name}
                                                        </SelectItem>
                                                    );
                                                })}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.icon} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="sort_order" optional>
                                            Order
                                        </Label>
                                        <Input
                                            id="sort_order"
                                            name="sort_order"
                                            type="number"
                                            min="0"
                                            max="65535"
                                            step="1"
                                            defaultValue={menu?.sort_order}
                                            placeholder="Auto (placed last)"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Lower numbers appear first.
                                        </p>
                                        <InputError
                                            message={errors.sort_order}
                                        />
                                    </div>
                                </div>
                            </Section>

                            <Section
                                icon={Eye}
                                title="Visibility"
                                description={
                                    isAdmin
                                        ? 'Who sees this menu in the sidebar. Page access is still enforced by permissions on the server.'
                                        : 'Hidden menus are removed from the website navbar.'
                                }
                            >
                                {isAdmin && locked && menu?.audience && (
                                    <div className="flex items-start gap-3 rounded-xl border bg-muted/30 p-4">
                                        <KeyRound className="mt-0.5 size-4 shrink-0 text-primary" />
                                        <div className="space-y-2 text-sm">
                                            <p>
                                                This built-in menu follows the{' '}
                                                <code className="rounded bg-muted px-1 font-semibold">
                                                    {menu.audience.permission}
                                                </code>{' '}
                                                permission. It is currently
                                                visible to:
                                            </p>
                                            <RoleBadges
                                                roles={menu.audience.roles}
                                            />
                                            <p className="text-muted-foreground">
                                                To change who sees it, grant or
                                                revoke that permission on the{' '}
                                                <Link
                                                    href={admin.permissions.index()}
                                                    className="font-medium text-primary underline-offset-4 hover:underline"
                                                >
                                                    Permissions
                                                </Link>{' '}
                                                page.
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {crudOn && (
                                    <div className="flex items-start gap-3 rounded-xl border bg-muted/30 p-4 text-sm">
                                        <KeyRound className="mt-0.5 size-4 shrink-0 text-primary" />
                                        <p>
                                            This menu will follow the generated{' '}
                                            <code className="rounded bg-muted px-1 font-semibold">
                                                &lt;name&gt;.view
                                            </code>{' '}
                                            permission, so it shows exactly for
                                            the users who can open the page.
                                        </p>
                                    </div>
                                )}

                                {isAdmin && !locked && !crudOn && (
                                    <fieldset className="grid gap-3">
                                        <legend className="mb-3 text-sm font-medium">
                                            Who can see this menu?
                                        </legend>
                                        <input
                                            type="hidden"
                                            name="audience"
                                            value={audience}
                                        />
                                        <div
                                            role="radiogroup"
                                            className="grid gap-3 lg:grid-cols-3"
                                        >
                                            <AudienceOption
                                                selected={
                                                    audience === 'everyone'
                                                }
                                                onSelect={() =>
                                                    setAudience('everyone')
                                                }
                                                icon={Users}
                                                title="Everyone"
                                                description="All signed-in users."
                                            />
                                            <AudienceOption
                                                selected={audience === 'roles'}
                                                onSelect={() =>
                                                    setAudience('roles')
                                                }
                                                icon={ShieldCheck}
                                                title="Only selected roles"
                                                description="e.g. administrators only."
                                            />
                                            <AudienceOption
                                                selected={
                                                    audience === 'permission'
                                                }
                                                onSelect={() =>
                                                    setAudience('permission')
                                                }
                                                icon={KeyRound}
                                                title="Users with a permission"
                                                description="For pages locked by a permission in code."
                                            />
                                        </div>

                                        {audience === 'permission' && (
                                            <div className="space-y-3 rounded-xl border p-4">
                                                <div className="grid gap-2">
                                                    <Label
                                                        htmlFor="permission"
                                                        required
                                                    >
                                                        Permission
                                                    </Label>
                                                    <input
                                                        type="hidden"
                                                        name="permission"
                                                        value={empty(
                                                            permission,
                                                        )}
                                                    />
                                                    <Select
                                                        value={permission}
                                                        onValueChange={
                                                            setPermission
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id="permission"
                                                            className="w-full"
                                                        >
                                                            <SelectValue placeholder="Choose a permission" />
                                                        </SelectTrigger>
                                                        <SelectContent className="max-h-72">
                                                            {permissionOptions.map(
                                                                (p) => (
                                                                    <SelectItem
                                                                        key={
                                                                            p.value
                                                                        }
                                                                        value={
                                                                            p.value
                                                                        }
                                                                    >
                                                                        {
                                                                            p.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    <InputError
                                                        message={
                                                            errors.permission
                                                        }
                                                    />
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    The menu then shows exactly
                                                    for the users who can open
                                                    the page. Grant the
                                                    permission to roles on the{' '}
                                                    <Link
                                                        href={admin.permissions.index()}
                                                        className="font-medium text-primary underline-offset-4 hover:underline"
                                                    >
                                                        Permissions
                                                    </Link>{' '}
                                                    page.
                                                </p>
                                            </div>
                                        )}

                                        {audience === 'roles' && (
                                            <div className="space-y-3 rounded-xl border p-4">
                                                <div className="flex flex-wrap gap-2">
                                                    {roleOptions.map((role) => {
                                                        const isAdminRole =
                                                            role === 'admin';
                                                        const checked =
                                                            isAdminRole ||
                                                            roles.includes(
                                                                role,
                                                            );

                                                        return (
                                                            <label
                                                                key={role}
                                                                className={cn(
                                                                    'flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors',
                                                                    checked &&
                                                                        'border-primary bg-primary/5',
                                                                    isAdminRole &&
                                                                        'cursor-default',
                                                                )}
                                                            >
                                                                <Checkbox
                                                                    checked={
                                                                        checked
                                                                    }
                                                                    disabled={
                                                                        isAdminRole
                                                                    }
                                                                    onCheckedChange={() =>
                                                                        toggleRole(
                                                                            role,
                                                                        )
                                                                    }
                                                                />
                                                                <span className="font-medium">
                                                                    {role}
                                                                </span>
                                                                {checked && (
                                                                    <input
                                                                        type="hidden"
                                                                        name="roles[]"
                                                                        value={
                                                                            role
                                                                        }
                                                                    />
                                                                )}
                                                            </label>
                                                        );
                                                    })}
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    Admin always sees every
                                                    menu. Leave only{' '}
                                                    <strong>admin</strong>{' '}
                                                    ticked for an admin-only
                                                    menu.
                                                </p>
                                            </div>
                                        )}
                                        <InputError
                                            message={
                                                errors.roles ?? errors.audience
                                            }
                                        />
                                    </fieldset>
                                )}

                                {!isAdmin && (
                                    <div className="flex items-center justify-between gap-4 rounded-xl border p-4">
                                        <div className="space-y-0.5">
                                            <p className="font-medium">
                                                Open in a new tab
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                Recommended for links to other
                                                websites.
                                            </p>
                                        </div>
                                        <ToggleSwitch
                                            name="open_in_new_tab"
                                            checked={newTab}
                                            onChange={setNewTab}
                                            label="Open in a new tab"
                                        />
                                    </div>
                                )}

                                <div className="flex items-center justify-between gap-4 rounded-xl border p-4">
                                    <div className="space-y-0.5">
                                        <p className="font-medium">Active</p>
                                        <p className="text-sm text-muted-foreground">
                                            Inactive menus (and their submenus)
                                            are hidden from navigation.
                                        </p>
                                    </div>
                                    <ToggleSwitch
                                        name={locked ? undefined : 'is_active'}
                                        checked={isActive}
                                        onChange={setIsActive}
                                        disabled={locked}
                                        label="Active"
                                    />
                                </div>
                                <InputError message={errors.is_active} />
                            </Section>
                        </div>

                        {/* Right column on wide screens. */}
                        {showCrud && crudGenerator && (
                            <div className="min-w-0">
                                <CrudGeneratorSection
                                    options={crudGenerator}
                                    enabled={crudEnabled}
                                    onEnabledChange={setCrudEnabled}
                                    errors={errors}
                                    token={crudToken}
                                />
                            </div>
                        )}
                    </div>

                    <div className="flex items-center justify-end gap-2">
                        <Button variant="ghost" asChild>
                            <Link
                                href={admin.menus.index({
                                    query: { location },
                                })}
                            >
                                Cancel
                            </Link>
                        </Button>
                        <Button
                            disabled={processing}
                            className="shadow-sm shadow-primary/30"
                        >
                            {menu ? 'Save changes' : 'Create menu'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
