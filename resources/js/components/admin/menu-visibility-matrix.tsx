import { Link, router } from '@inertiajs/react';
import { CornerDownRight, ListTree, Pencil } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import MenuController from '@/actions/App/Http/Controllers/Admin/MenuController';
import { roleTone, StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import admin from '@/routes/admin';
import type { AccessRole, MenuVisibilityRow } from '@/types';

type Visibility = { everyone: boolean; roles: string[] };

const toState = (rows: MenuVisibilityRow[]): Record<number, Visibility> =>
    Object.fromEntries(
        rows.map((r) => [r.id, { everyone: r.everyone, roles: r.roles }]),
    );

/**
 * "Menu visibility": which roles see each CUSTOM sidebar menu.
 *
 * - "Everyone" ticked → every signed-in user sees it.
 * - Untick "Everyone" → it becomes "only these roles" (all ticked at first),
 *   then untick the roles that should not see it.
 *
 * Built-in menus and menus that follow a permission are not listed here:
 * they are controlled by the permission matrix above.
 */
export function MenuVisibilityMatrix({
    rows,
    roles,
    editable,
    onPendingChange,
}: {
    rows: MenuVisibilityRow[];
    roles: AccessRole[];
    editable: boolean;
    onPendingChange: (delta: number) => void;
}) {
    const [state, setState] = useState(() => toState(rows));
    const [prevRows, setPrevRows] = useState(rows);

    if (rows !== prevRows) {
        setPrevRows(rows);
        setState(toState(rows));
    }

    const otherRoles = roles.filter((r) => !r.is_admin).map((r) => r.name);

    const save = (menu: MenuVisibilityRow, next: Visibility) => {
        const previous = state;
        setState({ ...state, [menu.id]: next });
        onPendingChange(1);

        router.patch(
            MenuController.visibility.url(menu.ulid),
            next.everyone
                ? { audience: 'everyone' }
                : { audience: 'roles', roles: ['admin', ...next.roles] },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['menuVisibility'],
                onError: (errors) => {
                    setState(previous);
                    toast.error(
                        Object.values(errors)[0] ??
                            `Could not update "${menu.title}".`,
                    );
                },
                onFinish: () => onPendingChange(-1),
            },
        );
    };

    const toggleEveryone = (menu: MenuVisibilityRow) => {
        const current = state[menu.id];

        // Leaving "Everyone" keeps the same people for now (all roles ticked),
        // so nothing disappears until a role is unticked.
        save(
            menu,
            current.everyone
                ? { everyone: false, roles: otherRoles }
                : { everyone: true, roles: [] },
        );
    };

    const toggleRole = (menu: MenuVisibilityRow, role: string) => {
        const current = state[menu.id];
        const selected = current.everyone
            ? otherRoles
            : current.roles.filter((r) => r !== 'admin');

        save(menu, {
            everyone: false,
            roles: selected.includes(role)
                ? selected.filter((r) => r !== role)
                : [...selected, role],
        });
    };

    return (
        <Card className="gap-0 overflow-hidden py-0">
            <CardHeader className="flex-row items-start gap-3 border-b py-4">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <ListTree className="size-4.5" />
                </div>
                <div className="space-y-1">
                    <CardTitle>Menu visibility</CardTitle>
                    <CardDescription>
                        Who sees your own sidebar menus. Built-in menus follow
                        the Permissions tab.
                    </CardDescription>
                </div>
            </CardHeader>

            {rows.length === 0 ? (
                <div className="px-4 py-10 text-center text-sm text-muted-foreground">
                    No custom sidebar menus yet.{' '}
                    <Link
                        href={admin.menus.create({
                            query: { location: 'admin' },
                        })}
                        className="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        Add one
                    </Link>
                    .
                </div>
            ) : (
                <div className="max-h-[calc(100svh-19rem)] min-h-64 overflow-auto">
                    <table className="w-full border-collapse text-sm">
                        <thead className="sticky top-0 z-20 shadow-[inset_0_-1px_0_var(--border)]">
                            <tr className="border-b">
                                <th className="sticky left-0 z-30 min-w-64 bg-muted px-4 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Menu
                                </th>
                                <th className="min-w-24 bg-muted px-3 py-3 text-center text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Everyone
                                </th>
                                {roles.map((role) => (
                                    <th
                                        key={role.name}
                                        className="min-w-28 bg-muted px-3 py-3 text-center font-normal"
                                    >
                                        <StatusBadge
                                            tone={roleTone(role.name)}
                                            dot={false}
                                        >
                                            {role.name}
                                        </StatusBadge>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((menu) => {
                                const v = state[menu.id] ?? {
                                    everyone: menu.everyone,
                                    roles: menu.roles,
                                };

                                return (
                                    <tr
                                        key={menu.id}
                                        className="border-b transition-colors last:border-0 hover:bg-muted/30"
                                    >
                                        <th
                                            scope="row"
                                            className="sticky left-0 z-10 bg-card px-4 py-3 text-left font-normal"
                                        >
                                            <div className="flex items-center gap-2">
                                                {menu.depth === 1 && (
                                                    <CornerDownRight className="size-3.5 shrink-0 text-muted-foreground/60" />
                                                )}
                                                <div className="min-w-0">
                                                    <p className="font-medium">
                                                        {menu.title}
                                                        {!menu.is_active && (
                                                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                                (hidden)
                                                            </span>
                                                        )}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {menu.parent_title
                                                            ? `Under ${menu.parent_title} · `
                                                            : ''}
                                                        <span className="font-mono">
                                                            {menu.url ??
                                                                'No URL'}
                                                        </span>
                                                    </p>
                                                </div>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                    className="ml-auto size-7 shrink-0"
                                                >
                                                    <Link
                                                        href={admin.menus.edit(
                                                            menu.ulid,
                                                        )}
                                                        aria-label={`Edit ${menu.title}`}
                                                    >
                                                        <Pencil className="size-3.5" />
                                                    </Link>
                                                </Button>
                                            </div>
                                        </th>
                                        <td className="px-3 py-3 text-center">
                                            <Checkbox
                                                checked={v.everyone}
                                                disabled={!editable}
                                                onCheckedChange={() =>
                                                    toggleEveryone(menu)
                                                }
                                                aria-label={`${menu.title} visible to everyone`}
                                            />
                                        </td>
                                        {roles.map((role) => (
                                            <td
                                                key={role.name}
                                                className="px-3 py-3 text-center"
                                            >
                                                <Checkbox
                                                    checked={
                                                        role.is_admin ||
                                                        v.everyone ||
                                                        v.roles.includes(
                                                            role.name,
                                                        )
                                                    }
                                                    disabled={
                                                        role.is_admin ||
                                                        !editable
                                                    }
                                                    onCheckedChange={() =>
                                                        toggleRole(
                                                            menu,
                                                            role.name,
                                                        )
                                                    }
                                                    aria-label={`${menu.title} visible to ${role.name}`}
                                                />
                                            </td>
                                        ))}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </Card>
    );
}
