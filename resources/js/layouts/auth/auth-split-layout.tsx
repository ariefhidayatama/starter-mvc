import { Link, usePage } from '@inertiajs/react';
import { Gauge, ShieldCheck, Smartphone } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const HIGHLIGHTS = [
    {
        icon: ShieldCheck,
        title: 'Secure from day one',
        text: 'Roles & permissions, two-factor authentication, rate limiting.',
    },
    {
        icon: Smartphone,
        title: 'Web & mobile',
        text: 'One backend for the web dashboard and the mobile REST API.',
    },
    {
        icon: Gauge,
        title: 'Built for performance',
        text: 'Optimized queries, Redis caching, and background jobs.',
    },
];

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="grid min-h-dvh lg:grid-cols-[1.05fr_1fr]">
            {/* Panel brand (desktop) */}
            <div className="relative hidden overflow-hidden bg-brand-gradient p-10 text-white lg:flex lg:flex-col lg:justify-between">
                <div className="absolute inset-0 bg-dot-pattern text-white opacity-30" />
                <div className="absolute -top-24 -left-24 size-96 rounded-full bg-white/10 blur-3xl" />
                <div className="absolute -right-32 -bottom-32 size-[28rem] rounded-full bg-fuchsia-400/20 blur-3xl" />

                <Link
                    href={home()}
                    className="relative z-10 flex items-center gap-3 text-lg font-bold"
                >
                    <span className="flex size-10 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25 backdrop-blur">
                        <AppLogoIcon className="size-5 fill-current text-white" />
                    </span>
                    {name}
                </Link>

                <div className="relative z-10 max-w-md space-y-8">
                    <h2 className="text-4xl leading-tight font-extrabold tracking-tight">
                        Run your business from one place.
                    </h2>
                    <ul className="space-y-5">
                        {HIGHLIGHTS.map(({ icon: Icon, title: t, text }) => (
                            <li key={t} className="flex gap-4">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20 backdrop-blur">
                                    <Icon className="size-5" />
                                </span>
                                <div>
                                    <p className="font-semibold">{t}</p>
                                    <p className="text-sm text-white/75">
                                        {text}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative z-10 text-sm text-white/60">
                    © {new Date().getFullYear()} {name}
                </p>
            </div>

            {/* Form */}
            <div className="flex items-center justify-center p-6 sm:p-10">
                <div className="w-full max-w-sm space-y-8">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 font-bold lg:hidden"
                    >
                        <span className="flex size-9 items-center justify-center rounded-lg bg-brand-gradient">
                            <AppLogoIcon className="size-4.5 fill-current text-white" />
                        </span>
                        {name}
                    </Link>

                    <div className="space-y-2">
                        <h1 className="text-2xl font-bold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-sm text-balance text-muted-foreground">
                            {description}
                        </p>
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}
