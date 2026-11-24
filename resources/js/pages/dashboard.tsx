import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    KeyRound,
    ShieldCheck,
    Smartphone,
    UserCheck,
    UserPlus,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { roleTone, StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { UserIdentity } from '@/components/user-avatar';
import { useCan } from '@/hooks/use-can';
import { formatDate, formatRelative } from '@/lib/format';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import { edit as securityEdit } from '@/routes/security';

type Stats = {
    total_users: number;
    active_users: number;
    new_this_week: number;
    active_tokens: number;
    roles: { name: string; users_count: number }[];
    recent_users: {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
        created_at: string;
    }[];
};

type Account = {
    roles: string[];
    active_devices: number;
    two_factor_enabled: boolean;
    member_since: string;
};

function greeting(): string {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Good morning';
    }

    if (hour < 18) {
        return 'Good afternoon';
    }

    return 'Good evening';
}

function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    accent,
}: {
    label: string;
    value: number | string;
    hint?: string;
    icon: LucideIcon;
    accent: string;
}) {
    return (
        <Card className="gap-0 py-5 transition-shadow hover:shadow-md">
            <CardContent className="flex items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-sm font-medium text-muted-foreground">
                        {label}
                    </p>
                    <p className="text-3xl font-bold tracking-tight tabular-nums">
                        {value}
                    </p>
                    {hint && (
                        <p className="text-xs text-muted-foreground">{hint}</p>
                    )}
                </div>
                <div
                    className={`flex size-11 items-center justify-center rounded-xl ${accent}`}
                >
                    <Icon className="size-5" />
                </div>
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    account,
    stats,
}: {
    account: Account;
    stats: Stats | null;
}) {
    const { auth } = usePage().props;
    const can = useCan();
    const firstName = auth.user.name.split(' ')[0];

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Welcome hero */}
                <div className="relative overflow-hidden rounded-2xl bg-brand-gradient p-6 text-white shadow-lg shadow-primary/20 md:p-8">
                    <div className="absolute inset-0 bg-dot-pattern text-white opacity-40" />
                    <div className="absolute -top-16 -right-16 size-64 rounded-full bg-white/10 blur-2xl" />
                    <div className="relative flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <div className="space-y-2">
                            <p className="text-sm font-medium text-white/80">
                                {greeting()},
                            </p>
                            <h1 className="text-3xl font-bold tracking-tight">
                                {firstName} 👋
                            </h1>
                            <p className="max-w-lg text-sm text-white/80">
                                Member since {formatDate(account.member_since)}{' '}
                                · Role: {account.roles.join(', ') || '-'}
                            </p>
                        </div>
                        {can('users.view') && (
                            <Button
                                asChild
                                variant="secondary"
                                className="bg-white text-primary hover:bg-white/90"
                            >
                                <Link href={admin.users.index()}>
                                    Manage users <ArrowRight />
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {stats ? (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <StatCard
                                label="Total users"
                                value={stats.total_users}
                                icon={Users}
                                accent="bg-primary/10 text-primary"
                            />
                            <StatCard
                                label="Active users"
                                value={stats.active_users}
                                hint={`${stats.total_users - stats.active_users} inactive`}
                                icon={UserCheck}
                                accent="bg-success/10 text-success"
                            />
                            <StatCard
                                label="New users"
                                value={stats.new_this_week}
                                hint="Last 7 days"
                                icon={UserPlus}
                                accent="bg-violet-500/10 text-violet-600 dark:text-violet-400"
                            />
                            <StatCard
                                label="Active API tokens"
                                value={stats.active_tokens}
                                hint="Signed-in mobile devices"
                                icon={Smartphone}
                                accent="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                            />
                        </div>

                        <div className="grid gap-4 lg:grid-cols-3">
                            <Card className="lg:col-span-2">
                                <CardHeader className="flex-row items-center justify-between">
                                    <div className="space-y-1.5">
                                        <CardTitle>Recent users</CardTitle>
                                        <CardDescription>
                                            The 5 most recently created accounts
                                        </CardDescription>
                                    </div>
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={admin.users.index()}>
                                            View all <ArrowRight />
                                        </Link>
                                    </Button>
                                </CardHeader>
                                <CardContent className="space-y-1">
                                    {stats.recent_users.map((u) => (
                                        <div
                                            key={u.id}
                                            className="flex items-center justify-between gap-4 rounded-lg px-2 py-2.5 hover:bg-muted/60"
                                        >
                                            <UserIdentity
                                                name={u.name}
                                                email={u.email}
                                            />
                                            <div className="flex shrink-0 items-center gap-3">
                                                <span className="hidden text-xs text-muted-foreground sm:inline">
                                                    {formatRelative(
                                                        u.created_at,
                                                    )}
                                                </span>
                                                <StatusBadge
                                                    tone={
                                                        u.is_active
                                                            ? 'success'
                                                            : 'danger'
                                                    }
                                                >
                                                    {u.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </StatusBadge>
                                            </div>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Role distribution</CardTitle>
                                    <CardDescription>
                                        Number of users per role
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {stats.roles.map((r) => {
                                        const pct =
                                            stats.total_users > 0
                                                ? Math.round(
                                                      (r.users_count /
                                                          stats.total_users) *
                                                          100,
                                                  )
                                                : 0;

                                        return (
                                            <div
                                                key={r.name}
                                                className="space-y-1.5"
                                            >
                                                <div className="flex items-center justify-between text-sm">
                                                    <StatusBadge
                                                        tone={roleTone(r.name)}
                                                        dot={false}
                                                    >
                                                        {r.name}
                                                    </StatusBadge>
                                                    <span className="text-muted-foreground tabular-nums">
                                                        {r.users_count} · {pct}%
                                                    </span>
                                                </div>
                                                <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className="h-full rounded-full bg-brand-gradient transition-all"
                                                        style={{
                                                            width: `${pct}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        );
                                    })}
                                </CardContent>
                            </Card>
                        </div>
                    </>
                ) : (
                    <div className="grid gap-4 md:grid-cols-3">
                        <StatCard
                            label="Signed-in devices"
                            value={account.active_devices}
                            hint="Connected mobile apps"
                            icon={Smartphone}
                            accent="bg-primary/10 text-primary"
                        />
                        <StatCard
                            label="Two-factor authentication"
                            value={account.two_factor_enabled ? 'On' : 'Off'}
                            hint="An extra layer of security"
                            icon={ShieldCheck}
                            accent={
                                account.two_factor_enabled
                                    ? 'bg-success/10 text-success'
                                    : 'bg-warning/15 text-amber-600'
                            }
                        />
                        <Card className="justify-between gap-3 py-5">
                            <CardContent className="space-y-1">
                                <p className="font-semibold">
                                    Secure your account
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Enable two-factor authentication and manage
                                    your password on the security page.
                                </p>
                            </CardContent>
                            <CardContent>
                                <Button asChild size="sm">
                                    <Link href={securityEdit()}>
                                        <KeyRound /> Security settings
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
