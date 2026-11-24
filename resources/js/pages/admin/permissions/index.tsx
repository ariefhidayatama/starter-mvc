import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    ChevronRight,
    ChevronsDownUp,
    ChevronsUpDown,
    KeyRound,
    ListTree,
    Loader2,
    Lock,
    Plug,
    Plus,
    Search,
    ShieldCheck,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { toast } from 'sonner';
import PermissionController from '@/actions/App/Http/Controllers/Admin/PermissionController';
import { MenuVisibilityMatrix } from '@/components/admin/menu-visibility-matrix';
import { PageHeader } from '@/components/page-header';
import { roleTone, StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { AccessRole, MenuVisibilityRow, PermissionGroup } from '@/types';

const ACTION_LABELS: Record<string, string> = {
    view: 'View',
    create: 'Create',
    update: 'Edit',
    delete: 'Delete',
};

type Grants = Record<string, string[]>;
type Tab = 'permissions' | 'menus';

const toGrants = (roles: AccessRole[]): Grants =>
    Object.fromEntries(roles.map((r) => [r.name, r.permissions]));

/** How many endpoints to show before "+N more". */
const MAX_ENDPOINTS = 3;

/**
 * REST API endpoints a permission unlocks, e.g. "GET /api/v1/messages".
 * The full list is in the tooltip when it is long.
 */
function ApiEndpoints({ endpoints }: { endpoints: string[] }) {
    if (endpoints.length === 0) {
        return null;
    }

    const shown = endpoints.slice(0, MAX_ENDPOINTS);
    const more = endpoints.length - shown.length;

    return (
        <p
            className="mt-1 flex flex-wrap items-center gap-1 text-xs text-muted-foreground"
            title={endpoints.join('\n')}
        >
            <Plug className="size-3.5 shrink-0 text-primary" />
            <span className="text-primary">API:</span>
            {shown.map((e) => (
                <code
                    key={e}
                    className="rounded bg-primary/5 px-1 py-0.5 font-mono text-[11px]"
                >
                    {e.replace('/api/v1/', '/')}
                </code>
            ))}
            {more > 0 && <span>+{more} more</span>}
        </p>
    );
}

/**
 * Access Control: one matrix of permissions (rows) × roles (columns).
 * Each click is saved immediately; the admin column is locked because
 * admin always has every permission.
 *
 * Modules are collapsed by default so the page stays short as menus grow:
 * the module row's checkbox still grants/revokes the whole module, and
 * expanding it shows the individual permissions.
 */
export default function PermissionsIndex({
    permissionGroups,
    roles,
    menuVisibility,
}: {
    permissionGroups: PermissionGroup[];
    roles: AccessRole[];
    menuVisibility: MenuVisibilityRow[];
}) {
    const can = useCan();
    const editable = can('roles.update');

    // Local copy for instant feedback; reset when the server sends new data.
    const [grants, setGrants] = useState(() => toGrants(roles));
    const [prevRoles, setPrevRoles] = useState(roles);

    if (roles !== prevRoles) {
        setPrevRoles(roles);
        setGrants(toGrants(roles));
    }

    const [pending, setPending] = useState(0);
    const [search, setSearch] = useState('');
    const [tab, setTab] = useState<Tab>('permissions');
    const [expanded, setExpanded] = useState<Set<string>>(() => new Set());

    const searching = search.trim() !== '';

    const groups = useMemo(() => {
        const q = search.trim().toLowerCase();

        if (!q) {
            return permissionGroups;
        }

        return permissionGroups
            .map((g) => ({
                ...g,
                permissions: g.permissions.filter((p) =>
                    [
                        p.name,
                        p.description,
                        g.label,
                        ...(p.menus ?? []),
                        ...(p.endpoints ?? []),
                    ]
                        .join(' ')
                        .toLowerCase()
                        .includes(q),
                ),
            }))
            .filter((g) => g.permissions.length > 0);
    }, [permissionGroups, search]);

    // While searching every matching module is open, so results are visible.
    const isOpen = (module: string) => searching || expanded.has(module);
    const allOpen = groups.length > 0 && groups.every((g) => isOpen(g.module));

    const toggleGroup = (module: string) =>
        setExpanded((prev) => {
            const next = new Set(prev);

            if (next.has(module)) {
                next.delete(module);
            } else {
                next.add(module);
            }

            return next;
        });

    const toggleAll = () =>
        setExpanded(
            allOpen
                ? new Set()
                : new Set(permissionGroups.map((g) => g.module)),
        );

    const has = (role: AccessRole, permission: string) =>
        role.is_admin || (grants[role.name] ?? []).includes(permission);

    const save = (
        role: AccessRole,
        permissions: string[],
        granted: boolean,
    ) => {
        const previous = grants;

        setGrants({
            ...grants,
            [role.name]: granted
                ? [...new Set([...(grants[role.name] ?? []), ...permissions])]
                : (grants[role.name] ?? []).filter(
                      (p) => !permissions.includes(p),
                  ),
        });
        setPending((n) => n + 1);

        router.patch(
            PermissionController.update.url(role.ulid),
            { permissions, granted },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['roles'],
                onError: () => {
                    setGrants(previous);
                    toast.error(`Could not update the "${role.name}" role.`);
                },
                onFinish: () => setPending((n) => n - 1),
            },
        );
    };

    const permissionCount = permissionGroups.reduce(
        (n, g) => n + g.permissions.length,
        0,
    );

    const tabs: {
        key: Tab;
        label: string;
        icon: typeof KeyRound;
        count: number;
    }[] = [
        {
            key: 'permissions',
            label: 'Permissions',
            icon: ShieldCheck,
            count: permissionCount,
        },
        {
            key: 'menus',
            label: 'Menu visibility',
            icon: ListTree,
            count: menuVisibility.length,
        },
    ];

    const saveStatus = (
        <div
            className="flex items-center gap-1.5 text-sm text-muted-foreground sm:ml-auto"
            aria-live="polite"
        >
            {pending > 0 ? (
                <>
                    <Loader2 className="size-4 animate-spin" />
                    Saving…
                </>
            ) : (
                editable && (
                    <>
                        <Check className="size-4 text-success" />
                        All changes saved
                    </>
                )
            )}
        </div>
    );

    return (
        <>
            <Head title="Permissions" />

            <div className="space-y-4 p-4 md:p-6">
                <PageHeader
                    icon={KeyRound}
                    title="Permissions"
                    description="Choose what each role can do. Every change is saved immediately."
                />

                {/* Tabs: keep the two matrices apart instead of stacking them */}
                <div className="flex flex-wrap items-center gap-3">
                    <div
                        role="tablist"
                        aria-label="Access control sections"
                        className="inline-flex rounded-lg border bg-muted/40 p-1"
                    >
                        {tabs.map((t) => (
                            <button
                                key={t.key}
                                type="button"
                                role="tab"
                                aria-selected={tab === t.key}
                                onClick={() => setTab(t.key)}
                                className={cn(
                                    'inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                    tab === t.key
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <t.icon className="size-4" />
                                {t.label}
                                <span className="rounded-full bg-muted px-1.5 text-xs text-muted-foreground tabular-nums">
                                    {t.count}
                                </span>
                            </button>
                        ))}
                    </div>
                    {tab === 'menus' && saveStatus}
                </div>

                {tab === 'permissions' && (
                    <Card className="gap-0 overflow-hidden py-0">
                        {/* Toolbar: [Add role] [search] [expand] ........ [save status] */}
                        <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center">
                            {can('roles.create') && (
                                <Button
                                    asChild
                                    className="shrink-0 shadow-sm shadow-primary/30"
                                >
                                    <Link href={admin.roles.create()}>
                                        <Plus /> Add role
                                    </Link>
                                </Button>
                            )}
                            <div className="relative w-full sm:max-w-xs">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search permissions or menus…"
                                    className="pl-9"
                                    aria-label="Search permissions"
                                />
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                className="shrink-0"
                                onClick={toggleAll}
                                disabled={searching || groups.length === 0}
                            >
                                {allOpen ? (
                                    <ChevronsDownUp />
                                ) : (
                                    <ChevronsUpDown />
                                )}
                                {allOpen ? 'Collapse all' : 'Expand all'}
                            </Button>
                            {saveStatus}
                        </div>

                        {/* Fixed-height scroll area: the role header stays visible */}
                        <div className="max-h-[calc(100svh-19rem)] min-h-64 overflow-auto">
                            <table className="w-full border-separate border-spacing-0 text-sm">
                                <thead className="sticky top-0 z-20">
                                    <tr>
                                        <th className="sticky left-0 z-30 min-w-64 border-b bg-muted px-4 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Permission
                                        </th>
                                        {roles.map((role) => (
                                            <th
                                                key={role.name}
                                                className="min-w-28 border-b bg-muted px-3 py-3 text-center font-normal"
                                            >
                                                <div className="flex flex-col items-center gap-1">
                                                    <StatusBadge
                                                        tone={roleTone(
                                                            role.name,
                                                        )}
                                                        dot={false}
                                                    >
                                                        {role.is_admin && (
                                                            <Lock className="size-3" />
                                                        )}
                                                        {role.name}
                                                    </StatusBadge>
                                                    <span className="text-xs text-muted-foreground">
                                                        {role.users_count} user
                                                        {role.users_count === 1
                                                            ? ''
                                                            : 's'}
                                                    </span>
                                                </div>
                                            </th>
                                        ))}
                                    </tr>
                                </thead>

                                <tbody>
                                    {groups.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={roles.length + 1}
                                                className="px-4 py-10 text-center text-muted-foreground"
                                            >
                                                No permissions match “{search}”.
                                            </td>
                                        </tr>
                                    )}

                                    {groups.map((group) => {
                                        const names = group.permissions.map(
                                            (p) => p.name,
                                        );
                                        const open = isOpen(group.module);
                                        const Chevron = open
                                            ? ChevronDown
                                            : ChevronRight;

                                        return (
                                            <Fragment key={group.module}>
                                                {/* Module row: click the name to expand; tick to grant the whole module */}
                                                <tr className="bg-muted/20">
                                                    <th
                                                        scope="rowgroup"
                                                        className="sticky left-0 z-10 border-b bg-muted p-0 text-left font-semibold"
                                                    >
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                toggleGroup(
                                                                    group.module,
                                                                )
                                                            }
                                                            disabled={searching}
                                                            aria-expanded={open}
                                                            className="flex w-full items-center gap-2 px-4 py-2.5 text-left hover:text-primary disabled:cursor-default disabled:hover:text-foreground"
                                                        >
                                                            <Chevron className="size-4 shrink-0 text-muted-foreground" />
                                                            <span>
                                                                {group.label}
                                                            </span>
                                                            <span className="ml-auto text-xs font-normal text-muted-foreground tabular-nums">
                                                                {names.length}
                                                            </span>
                                                        </button>
                                                    </th>
                                                    {roles.map((role) => {
                                                        const count =
                                                            names.filter((n) =>
                                                                has(role, n),
                                                            ).length;
                                                        const state =
                                                            count === 0
                                                                ? false
                                                                : count ===
                                                                    names.length
                                                                  ? true
                                                                  : 'indeterminate';

                                                        return (
                                                            <td
                                                                key={role.name}
                                                                className="border-b px-3 py-2.5 text-center"
                                                            >
                                                                <div className="flex items-center justify-center gap-1.5">
                                                                    <Checkbox
                                                                        checked={
                                                                            state
                                                                        }
                                                                        disabled={
                                                                            role.is_admin ||
                                                                            !editable
                                                                        }
                                                                        onCheckedChange={() =>
                                                                            save(
                                                                                role,
                                                                                names,
                                                                                state !==
                                                                                    true,
                                                                            )
                                                                        }
                                                                        aria-label={`All ${group.label} permissions for ${role.name}`}
                                                                    />
                                                                    <span className="w-7 text-left text-xs text-muted-foreground tabular-nums">
                                                                        {count}/
                                                                        {
                                                                            names.length
                                                                        }
                                                                    </span>
                                                                </div>
                                                            </td>
                                                        );
                                                    })}
                                                </tr>

                                                {open &&
                                                    group.permissions.map(
                                                        (p) => (
                                                            <tr
                                                                key={p.name}
                                                                className="transition-colors hover:bg-muted/30"
                                                            >
                                                                <th
                                                                    scope="row"
                                                                    className="sticky left-0 z-10 border-b bg-card py-2 pr-4 pl-10 text-left font-normal"
                                                                    title={
                                                                        p.description
                                                                    }
                                                                >
                                                                    <div className="flex flex-wrap items-center gap-2">
                                                                        <span className="font-medium">
                                                                            {ACTION_LABELS[
                                                                                p
                                                                                    .action
                                                                            ] ??
                                                                                p.action}
                                                                        </span>
                                                                        <code className="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                                                            {
                                                                                p.name
                                                                            }
                                                                        </code>
                                                                    </div>
                                                                    {(
                                                                        p.menus ??
                                                                        []
                                                                    ).length >
                                                                        0 && (
                                                                        <p className="mt-1 inline-flex items-center gap-1 text-xs text-primary">
                                                                            <ListTree className="size-3.5" />
                                                                            Shows
                                                                            menu:{' '}
                                                                            {p.menus?.join(
                                                                                ', ',
                                                                            )}
                                                                        </p>
                                                                    )}
                                                                    <ApiEndpoints
                                                                        endpoints={
                                                                            p.endpoints ??
                                                                            []
                                                                        }
                                                                    />
                                                                </th>
                                                                {roles.map(
                                                                    (role) => (
                                                                        <td
                                                                            key={
                                                                                role.name
                                                                            }
                                                                            className="border-b px-3 py-2 text-center"
                                                                        >
                                                                            <Checkbox
                                                                                checked={has(
                                                                                    role,
                                                                                    p.name,
                                                                                )}
                                                                                disabled={
                                                                                    role.is_admin ||
                                                                                    !editable
                                                                                }
                                                                                onCheckedChange={(
                                                                                    checked,
                                                                                ) =>
                                                                                    save(
                                                                                        role,
                                                                                        [
                                                                                            p.name,
                                                                                        ],
                                                                                        checked ===
                                                                                            true,
                                                                                    )
                                                                                }
                                                                                aria-label={`${p.name} for ${role.name}`}
                                                                            />
                                                                        </td>
                                                                    ),
                                                                )}
                                                            </tr>
                                                        ),
                                                    )}
                                            </Fragment>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex items-start gap-2 border-t bg-muted/20 px-4 py-3 text-sm text-muted-foreground">
                            <Lock className="mt-0.5 size-4 shrink-0" />
                            <span>
                                <strong className="text-foreground">
                                    admin
                                </strong>{' '}
                                always has every permission. To give a person
                                access, give their <strong>role</strong> the
                                permission here, then assign the role on the{' '}
                                <Link
                                    href={admin.users.index()}
                                    className="font-medium text-primary underline-offset-4 hover:underline"
                                >
                                    Users
                                </Link>{' '}
                                page.
                            </span>
                        </div>
                    </Card>
                )}

                {tab === 'menus' && (
                    <MenuVisibilityMatrix
                        rows={menuVisibility}
                        roles={roles}
                        editable={can('menus.update')}
                        onPendingChange={(delta) =>
                            setPending((n) => n + delta)
                        }
                    />
                )}
            </div>
        </>
    );
}

PermissionsIndex.layout = {
    breadcrumbs: [{ title: 'Permissions', href: admin.permissions.index() }],
};
