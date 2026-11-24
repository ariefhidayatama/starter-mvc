import { Form, Link } from '@inertiajs/react';
import { KeyRound, Lock, Tag } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { PermissionMatrix } from '@/components/permission-matrix';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import admin from '@/routes/admin';
import type { PermissionGroup, RoleForm as RoleFormData } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

export function RoleForm({
    action,
    permissionGroups,
    role,
}: {
    action: RouteFormDefinition<'post'>;
    permissionGroups: PermissionGroup[];
    role?: RoleFormData;
}) {
    const [permissions, setPermissions] = useState<string[]>(
        role?.permissions ?? [],
    );
    const locked = role?.is_locked ?? false;
    const total = permissionGroups.reduce(
        (n, g) => n + g.permissions.length,
        0,
    );

    return (
        <Form {...action} className="max-w-4xl space-y-6">
            {({ processing, errors }) => (
                <>
                    {locked && (
                        <Alert className="border-primary/30 bg-primary/5">
                            <Lock />
                            <AlertDescription>
                                The admin role is locked and always has every
                                permission.
                            </AlertDescription>
                        </Alert>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Tag className="size-4.5 text-primary" />
                                Role details
                            </CardTitle>
                            <CardDescription>
                                Use lowercase letters and hyphens, e.g.{' '}
                                <code className="rounded bg-muted px-1">
                                    warehouse-staff
                                </code>
                                .
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid max-w-sm gap-2">
                                <Label htmlFor="name" required>
                                    Role name
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={role?.name}
                                    readOnly={role?.is_system}
                                    placeholder="e.g. warehouse-staff"
                                    className="read-only:bg-muted read-only:text-muted-foreground"
                                    required
                                />
                                {role?.is_system && (
                                    <p className="text-xs text-muted-foreground">
                                        System role names cannot be changed
                                        because the code relies on them.
                                    </p>
                                )}
                                <InputError message={errors.name} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-start justify-between gap-4">
                            <div className="space-y-1.5">
                                <CardTitle className="flex items-center gap-2">
                                    <KeyRound className="size-4.5 text-primary" />
                                    Permissions
                                </CardTitle>
                                <CardDescription>
                                    Tick the actions this role may perform. Tick
                                    a module name to select all of its actions.
                                </CardDescription>
                            </div>
                            <span className="shrink-0 rounded-full bg-primary/10 px-3 py-1 text-sm font-semibold text-primary tabular-nums">
                                {permissions.length}/{total}
                            </span>
                        </CardHeader>
                        <CardContent>
                            <PermissionMatrix
                                groups={permissionGroups}
                                value={permissions}
                                onChange={setPermissions}
                                disabled={locked}
                            />
                            <InputError
                                className="mt-2"
                                message={errors.permissions}
                            />
                        </CardContent>
                    </Card>

                    <div className="flex items-center justify-end gap-2">
                        <Button variant="ghost" asChild>
                            <Link href={admin.roles.index()}>Back</Link>
                        </Button>
                        {!locked && (
                            <Button
                                disabled={processing}
                                className="shadow-sm shadow-primary/30"
                            >
                                {role ? 'Save changes' : 'Create role'}
                            </Button>
                        )}
                    </div>
                </>
            )}
        </Form>
    );
}
