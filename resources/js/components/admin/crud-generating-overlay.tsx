import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import MenuController from '@/actions/App/Http/Controllers/Admin/MenuController';

const KEY = 'crud-generator-pending';
const POLL_MS = 1500;
const GIVE_UP_MS = 5 * 60 * 1000;

type Pending = { token: string; at: number };

type Status = {
    state: 'running' | 'done' | 'failed' | 'unknown' | 'disabled';
    name?: string;
    type?: 'success' | 'warning';
    message?: string;
    redirect?: string;
};

/** A random id for one "Generate CRUD page" submit. */
export function newCrudToken(): string {
    return typeof crypto.randomUUID === 'function'
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
}

/** Called when the form is submitted with "Generate CRUD page" on. */
export function rememberPendingCrud(token: string): void {
    try {
        sessionStorage.setItem(KEY, JSON.stringify({ token, at: Date.now() }));
    } catch {
        // Storage blocked: the normal redirect still works without a reload.
    }
}

export function forgetPendingCrud(): void {
    try {
        sessionStorage.removeItem(KEY);
    } catch {
        // ignore
    }
}

function readPending(): Pending | null {
    try {
        const pending = JSON.parse(
            sessionStorage.getItem(KEY) ?? 'null',
        ) as Pending | null;

        return pending && Date.now() - pending.at < GIVE_UP_MS ? pending : null;
    } catch {
        return null;
    }
}

/**
 * Full-screen "Generating…" state for the CRUD generator.
 *
 * In development, Vite reloads the browser as soon as the generator writes
 * new route / page files, so the page that submitted the form never gets
 * the response. After that reload this component finds the pending token
 * in sessionStorage and asks the server for the result until it is done.
 */
export function CrudGeneratingOverlay({
    submitting,
    label = 'the CRUD page',
    description = 'Writing files, migrating the database and refreshing routes.',
}: {
    submitting: boolean;
    /** What is being generated, until the server reports its name. */
    label?: string;
    description?: string;
}) {
    const [pending] = useState(readPending);
    const [name, setName] = useState<string | null>(null);

    useEffect(() => {
        if (!pending) {
            return;
        }

        let stopped = false;

        const poll = async () => {
            while (!stopped && Date.now() - pending.at < GIVE_UP_MS) {
                try {
                    const response = await fetch(
                        MenuController.generatorStatus.url(pending.token),
                        { headers: { Accept: 'application/json' } },
                    );
                    const status = (await response.json()) as Status;

                    if (status.name) {
                        setName(status.name);
                    }

                    if (status.state === 'done') {
                        forgetPendingCrud();
                        toast[status.type ?? 'success'](
                            status.message ?? 'Done.',
                        );
                        router.visit(status.redirect ?? '/admin/menus');

                        return;
                    }

                    if (
                        status.state === 'failed' ||
                        status.state === 'disabled'
                    ) {
                        forgetPendingCrud();
                        toast.error(
                            status.message ?? 'The CRUD generator failed.',
                        );
                        router.reload();

                        return;
                    }
                } catch {
                    // Server busy (still generating): try again.
                }

                await new Promise((r) => setTimeout(r, POLL_MS));
            }

            if (!stopped) {
                forgetPendingCrud();
                toast.error(
                    'The generator is taking too long. Check storage/logs/laravel.log.',
                );
                router.reload();
            }
        };

        void poll();

        return () => {
            stopped = true;
        };
    }, [pending]);

    if (!submitting && !pending) {
        return null;
    }

    return (
        <div
            role="status"
            aria-live="polite"
            className="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm"
        >
            <div className="flex max-w-sm flex-col items-center gap-3 rounded-2xl border bg-card p-6 text-center shadow-lg">
                <Loader2 className="size-8 animate-spin text-primary" />
                <p className="font-semibold">
                    Working on {name ? `“${name}”` : label}…
                </p>
                <p className="text-sm text-muted-foreground">
                    {description} The page may reload by itself during this.
                    That is normal.
                </p>
            </div>
        </div>
    );
}
