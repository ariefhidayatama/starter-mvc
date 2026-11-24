import { Form, Link } from '@inertiajs/react';
import { Check, KeyRound, ShieldCheck, UserRound } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
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
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { AdminUserForm, Option } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

const ROLE_HINTS: Record<string, string> = {
    admin: 'Full access to the whole system',
    staff: 'Day-to-day operations',
    customer: 'Customers / regular users',
};

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

/**
 * User form shared by the Create & Edit pages.
 *
 * - Create: password is required.
 * - Edit  : password may be left empty (= unchanged).
 */
export function UserForm({
    action,
    roleOptions,
    user,
}: {
    action: RouteFormDefinition<'post' | 'put'>;
    roleOptions: Option[];
    user?: AdminUserForm;
}) {
    const isEdit = user !== undefined;
    const [roles, setRoles] = useState<string[]>(user?.roles ?? ['customer']);
    const [isActive, setIsActive] = useState<boolean>(user?.is_active ?? true);

    const toggleRole = (role: string) =>
        setRoles((prev) =>
            prev.includes(role)
                ? prev.filter((r) => r !== role)
                : [...prev, role],
        );

    return (
        <Form {...action} className="max-w-3xl space-y-6">
            {({ processing, errors }) => (
                <>
                    <Section
                        icon={UserRound}
                        title="Account information"
                        description="Name and email used to sign in."
                    >
                        <div className="grid items-start gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="name" required>
                                    Full name
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={user?.name}
                                    placeholder="John Doe"
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email" required>
                                    Email
                                </Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    defaultValue={user?.email}
                                    placeholder="john@example.com"
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.email} />
                            </div>
                        </div>
                    </Section>

                    <Section
                        icon={KeyRound}
                        title="Password"
                        description={
                            isEdit
                                ? 'Leave blank to keep the current password. Changing it signs the user out of every mobile app.'
                                : 'Initial password for this user.'
                        }
                    >
                        <div className="grid items-start gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="password"
                                    required={!isEdit}
                                    optional={isEdit}
                                >
                                    {isEdit ? 'New password' : 'Password'}
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required={!isEdit}
                                    autoComplete="new-password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label
                                    htmlFor="password_confirmation"
                                    required={!isEdit}
                                    optional={isEdit}
                                >
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required={!isEdit}
                                    autoComplete="new-password"
                                />
                            </div>
                        </div>
                    </Section>

                    <Section
                        icon={ShieldCheck}
                        title="Roles & status"
                        description="Roles decide which menus and actions the user can access."
                    >
                        {/* Selected values are submitted as roles[] */}
                        {roles.map((r) => (
                            <input
                                key={r}
                                type="hidden"
                                name="roles[]"
                                value={r}
                            />
                        ))}

                        <div className="grid gap-3 sm:grid-cols-3">
                            {roleOptions.map((role) => {
                                const selected = roles.includes(role.value);

                                return (
                                    <button
                                        key={role.value}
                                        type="button"
                                        onClick={() => toggleRole(role.value)}
                                        aria-pressed={selected}
                                        className={cn(
                                            'relative flex flex-col items-start gap-1 rounded-xl border p-4 text-left transition-all hover:border-primary/50',
                                            selected &&
                                                'border-primary bg-primary/5 ring-1 ring-primary',
                                        )}
                                    >
                                        <span className="font-semibold">
                                            {role.label}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {ROLE_HINTS[role.value] ??
                                                'Custom role'}
                                        </span>
                                        {selected && (
                                            <span className="absolute top-3 right-3 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                                <Check className="size-3" />
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                        <InputError message={errors.roles} />

                        <input
                            type="hidden"
                            name="is_active"
                            value={isActive ? '1' : '0'}
                        />
                        <div className="flex items-center justify-between gap-4 rounded-xl border p-4">
                            <div className="space-y-0.5">
                                <p className="font-medium">Active account</p>
                                <p className="text-sm text-muted-foreground">
                                    {user?.is_self
                                        ? 'You cannot deactivate your own account.'
                                        : 'Inactive accounts cannot sign in and are logged out from every device immediately.'}
                                </p>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                aria-checked={isActive}
                                aria-label="Active account"
                                disabled={user?.is_self}
                                onClick={() => setIsActive((v) => !v)}
                                className={cn(
                                    'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                                    isActive ? 'bg-primary' : 'bg-input',
                                )}
                            >
                                <span
                                    className={cn(
                                        'inline-block size-5 rounded-full bg-white shadow transition-transform',
                                        isActive
                                            ? 'translate-x-5.5'
                                            : 'translate-x-0.5',
                                    )}
                                />
                            </button>
                        </div>
                        <InputError message={errors.is_active} />
                    </Section>

                    <div className="flex items-center justify-end gap-2">
                        <Button variant="ghost" asChild>
                            <Link href={admin.users.index()}>Cancel</Link>
                        </Button>
                        <Button
                            disabled={processing}
                            className="shadow-sm shadow-primary/30"
                        >
                            {isEdit ? 'Save changes' : 'Create user'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
