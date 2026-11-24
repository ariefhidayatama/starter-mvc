import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-brand-gradient shadow-sm shadow-primary/30">
                <AppLogoIcon className="size-4.5 fill-current text-white" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-bold tracking-tight">
                    {name}
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    Admin Panel
                </span>
            </div>
        </>
    );
}
