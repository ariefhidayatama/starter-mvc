import { Head, Link, router } from '@inertiajs/react';
import {
    Lock,
    Pencil,
    Plus,
    ShieldCheck,
    Trash2,
    Users as UsersIcon,
} from 'lucide-react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import type { RowAction } from '@/components/row-actions';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import admin from '@/routes/admin';
import type { RoleRow } from '@/types';

export default function RolesIndex({
    roles,
    totalPermissions,
}: {
    roles: RoleRow[];
    totalPermissions: number;
}) {
    const can = useCan();

    const actionsFor = (role: RoleRow): RowAction[] => {
        const actions: RowAction[] = [];

        if (can('roles.update')) {
            actions.push({
                type: 'link',
                label: role.is_locked ? 'View' : 'Edit',
                icon: Pencil,
                href: admin.roles.edit(role.ulid),
            });
        }

        // System roles can never be deleted, so the option is not offered.
        if (can('roles.delete') && !role.is_system) {
            actions.push({
                type: 'confirm',
                label: 'Delete',
                icon: Trash2,
                destructive: true,
                confirm: {
                    title: `Delete role "${role.name}"?`,
                    description:
                        'A role that is still assigned to users cannot be deleted.',
                    confirmLabel: 'Delete',
                },
                onConfirm: (close) =>
                    router.visit(RoleController.destroy(role.ulid), {
                        preserveScroll: true,
                        onFinish: close,
                    }),
            });
        }

        return actions;
    };

    return (
        <>
            <Head title="Roles" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={ShieldCheck}
                    title="Roles"
                    description="A role is a set of permissions. Users get access through their roles."
                />

                {can('roles.create') && (
                    <Button asChild className="shadow-sm shadow-primary/30">
                        <Link href={admin.roles.create()}>
                            <Plus /> Add role
                        </Link>
                    </Button>
                )}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {roles.map((role) => {
                        const pct =
                            totalPermissions > 0
                                ? Math.round(
                                      (role.permissions_count /
                                          totalPermissions) *
                                          100,
                                  )
                                : 0;

                        return (
                            <Card
                                key={role.id}
                                className="gap-4 transition-all hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <CardContent className="space-y-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex items-center gap-3">
                                            <div className="flex size-10 items-center justify-center rounded-xl bg-brand-gradient text-white shadow-sm shadow-primary/30">
                                                {role.is_locked ? (
                                                    <Lock className="size-4.5" />
                                                ) : (
                                                    <ShieldCheck className="size-4.5" />
                                                )}
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="text-lg font-semibold capitalize">
                                                        {role.name}
                                                    </p>
                                                    {role.is_system && (
                                                        <StatusBadge
                                                            tone={
                                                                role.is_locked
                                                                    ? 'primary'
                                                                    : 'neutral'
                                                            }
                                                            dot={false}
                                                        >
                                                            {role.is_locked
                                                                ? 'Locked'
                                                                : 'System'}
                                                        </StatusBadge>
                                                    )}
                                                </div>
                                                <p className="flex items-center gap-1 text-sm text-muted-foreground">
                                                    <UsersIcon className="size-3.5" />
                                                    {role.users_count}{' '}
                                                    {role.users_count === 1
                                                        ? 'user'
                                                        : 'users'}
                                                </p>
                                            </div>
                                        </div>
                                        <RowActions
                                            label={`Actions for role ${role.name}`}
                                            actions={actionsFor(role)}
                                            align="end"
                                        />
                                    </div>

                                    <div className="space-y-1.5">
                                        <div className="flex justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Permissions
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {role.permissions_count}/
                                                {totalPermissions}
                                            </span>
                                        </div>
                                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-brand-gradient"
                                                style={{ width: `${pct}%` }}
                                            />
                                        </div>
                                    </div>
                                </CardContent>

                                {can('roles.update') && (
                                    <CardFooter className="border-t pt-4">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="w-full"
                                            asChild
                                        >
                                            <Link
                                                href={admin.roles.edit(
                                                    role.ulid,
                                                )}
                                            >
                                                {role.is_locked
                                                    ? 'View permissions'
                                                    : 'Manage permissions'}
                                            </Link>
                                        </Button>
                                    </CardFooter>
                                )}
                            </Card>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles', href: admin.roles.index() }],
};
