import { Head, router } from '@inertiajs/react';
import { LogOut, Smartphone } from 'lucide-react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { UserForm } from '@/components/admin/user-form';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { UserAvatar } from '@/components/user-avatar';
import admin from '@/routes/admin';
import type { AdminUserForm, Option } from '@/types';

export default function UsersEdit({
    user,
    roleOptions,
}: {
    user: AdminUserForm;
    roleOptions: Option[];
}) {
    return (
        <>
            <Head title={`Edit ${user.name}`} />

            <div className="space-y-6 p-4 md:p-6">
                {/* Short profile of the user being edited */}
                <div className="flex flex-wrap items-center gap-4">
                    <UserAvatar name={user.name} className="size-14 text-lg" />
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">
                                {user.name}
                            </h1>
                            <StatusBadge
                                tone={user.is_active ? 'success' : 'danger'}
                            >
                                {user.is_active ? 'Active' : 'Inactive'}
                            </StatusBadge>
                            {user.is_self && (
                                <StatusBadge tone="primary" dot={false}>
                                    You
                                </StatusBadge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {user.email}
                        </p>
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-[1fr_320px]">
                    <UserForm
                        action={UserController.update.form(user.ulid)}
                        roleOptions={roleOptions}
                        user={user}
                    />

                    <aside>
                        <Card className="xl:sticky xl:top-20">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Smartphone className="size-4.5 text-primary" />
                                    Sessions
                                </CardTitle>
                                <CardDescription>
                                    Mobile app tokens that are still active.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="rounded-xl bg-muted/60 p-4">
                                    <p className="text-3xl font-bold tabular-nums">
                                        {user.active_devices}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        connected devices
                                    </p>
                                </div>
                                <ConfirmDialog
                                    trigger={
                                        <Button
                                            variant="outline"
                                            className="w-full"
                                        >
                                            <LogOut /> Log out all devices
                                        </Button>
                                    }
                                    title={`Log out ${user.name} everywhere?`}
                                    description="All mobile tokens and web sessions of this user will be revoked. They will have to log in again."
                                    confirmLabel="Log out"
                                    onConfirm={(close) =>
                                        router.visit(
                                            UserController.logoutDevices(
                                                user.ulid,
                                            ),
                                            {
                                                preserveScroll: true,
                                                onFinish: close,
                                            },
                                        )
                                    }
                                />
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>
        </>
    );
}

UsersEdit.layout = {
    breadcrumbs: [
        { title: 'Users', href: admin.users.index() },
        { title: 'Edit', href: '#' },
    ],
};
