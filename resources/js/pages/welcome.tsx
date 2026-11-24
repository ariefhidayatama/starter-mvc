import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Database,
    Gauge,
    KeyRound,
    Layers,
    ShieldCheck,
    Smartphone,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { SiteNav } from '@/components/site-nav';
import { ThemeToggle } from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

const FEATURES = [
    {
        icon: Layers,
        title: 'One backend, two interfaces',
        text: 'The Inertia + React web app and the /api/v1 REST API share the same business logic.',
    },
    {
        icon: ShieldCheck,
        title: 'Roles & permissions',
        text: 'Manage access from the UI, enforced by server-side policies, not just hidden buttons.',
    },
    {
        icon: Smartphone,
        title: 'Mobile ready',
        text: 'Per-device Sanctum tokens, remote logout, and two-factor authentication support.',
    },
    {
        icon: Database,
        title: 'Optimized database',
        text: 'Eager loading, indexes, pagination, raw SQL, and stored procedures.',
    },
    {
        icon: Gauge,
        title: 'Measured, not claimed',
        text: 'k6 load tests before & after optimization: RPS, P95, P99, error rate.',
    },
    {
        icon: KeyRound,
        title: 'Secure by default',
        text: 'Rate limiting, strict validation, mass-assignment protection, and errors that never leak internals.',
    },
];

const STACK = [
    'Laravel 13',
    'React 19',
    'TypeScript',
    'Inertia 3',
    'Tailwind 4',
    'MySQL 8.4',
    'Redis',
];

export default function Welcome() {
    const { auth, name, menus } = usePage().props;

    return (
        <>
            <Head title="Welcome" />

            <div className="relative min-h-screen overflow-hidden bg-background">
                {/* Dekorasi latar */}
                <div className="pointer-events-none absolute inset-x-0 top-0 h-[36rem] bg-dot-pattern [mask-image:linear-gradient(to_bottom,black,transparent)] text-primary opacity-[0.15]" />
                <div className="pointer-events-none absolute top-[-10rem] left-1/2 h-[30rem] w-[60rem] -translate-x-1/2 rounded-full bg-primary/20 blur-3xl" />

                {/* Navbar */}
                <header className="relative z-20 mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
                    <Link
                        href="/"
                        className="flex items-center gap-2.5 font-bold"
                    >
                        <span className="flex size-9 items-center justify-center rounded-lg bg-brand-gradient shadow-sm shadow-primary/30">
                            <AppLogoIcon className="size-4.5 fill-current text-white" />
                        </span>
                        {name}
                    </Link>

                    {/* Menu from Admin → Menus → Website */}
                    <div className="ml-auto flex items-center md:mr-auto md:ml-8">
                        <SiteNav items={menus.website} />
                    </div>

                    <nav className="flex items-center gap-2">
                        <ThemeToggle />
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>
                                    Dashboard <ArrowRight />
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button
                                    asChild
                                    className="shadow-sm shadow-primary/30"
                                >
                                    <Link href={register()}>Sign up</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="relative z-10">
                    {/* Hero */}
                    <section className="mx-auto max-w-4xl px-6 pt-16 pb-20 text-center md:pt-24">
                        <span className="inline-flex items-center gap-2 rounded-full border bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground backdrop-blur">
                            <span className="size-1.5 rounded-full bg-success" />
                            Starter kit Laravel + React
                        </span>
                        <h1 className="mt-6 text-4xl font-extrabold tracking-tight text-balance sm:text-6xl">
                            An application foundation that is{' '}
                            <span className="bg-brand-gradient bg-clip-text text-transparent">
                                fast, secure, and scalable
                            </span>
                        </h1>
                        <p className="mx-auto mt-6 max-w-2xl text-lg text-balance text-muted-foreground">
                            A modern web dashboard and a REST API for Android,
                            iOS, and third-party integrations, built on a single
                            clean codebase.
                        </p>
                        <div className="mt-10 flex flex-wrap items-center justify-center gap-3">
                            <Button
                                size="lg"
                                asChild
                                className="shadow-lg shadow-primary/30"
                            >
                                <Link
                                    href={auth.user ? dashboard() : register()}
                                >
                                    {auth.user
                                        ? 'Open dashboard'
                                        : 'Get started'}
                                    <ArrowRight />
                                </Link>
                            </Button>
                            {!auth.user && (
                                <Button size="lg" variant="outline" asChild>
                                    <Link href={login()}>
                                        I already have an account
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </section>

                    {/* Fitur */}
                    <section
                        id="features"
                        className="mx-auto max-w-6xl scroll-mt-8 px-6 pb-20"
                    >
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {FEATURES.map(({ icon: Icon, title, text }) => (
                                <div
                                    key={title}
                                    className="group rounded-2xl border bg-card/70 p-6 backdrop-blur transition-all hover:-translate-y-1 hover:shadow-lg hover:shadow-primary/5"
                                >
                                    <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15 transition-colors group-hover:bg-brand-gradient group-hover:text-white">
                                        <Icon className="size-5" />
                                    </div>
                                    <h3 className="mt-4 font-semibold">
                                        {title}
                                    </h3>
                                    <p className="mt-1.5 text-sm text-muted-foreground">
                                        {text}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    {/* Teknologi */}
                    <section className="border-t bg-muted/30">
                        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-8 gap-y-3 px-6 py-8 text-sm font-semibold text-muted-foreground">
                            {STACK.map((s) => (
                                <span key={s}>{s}</span>
                            ))}
                        </div>
                    </section>
                </main>

                <footer className="relative z-10 border-t">
                    <div className="mx-auto max-w-6xl px-6 py-6 text-center text-sm text-muted-foreground">
                        © {new Date().getFullYear()} {name}
                    </div>
                </footer>
            </div>
        </>
    );
}
