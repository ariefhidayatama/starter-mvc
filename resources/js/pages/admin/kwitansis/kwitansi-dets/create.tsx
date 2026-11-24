import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import KwitansiController from '@/actions/App/Http/Controllers/Admin/KwitansiController';
import KwitansiKwitansiDetController from '@/actions/App/Http/Controllers/Admin/KwitansiKwitansiDetController';
import { KwitansiKwitansiDetForm } from '@/components/admin/kwitansi-kwitansi-det-form';
import { PageHeader } from '@/components/page-header';
import { routeKey } from '@/lib/route-key';

type Parent = { id: number; ulid?: string | null; [key: string]: unknown };

export default function KwitansiKwitansiDetCreate({
    parent,
}: {
    parent: Parent;
}) {
    return (
        <>
            <Head title="Add kwitansi det" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={Plus}
                    title="Add kwitansi det"
                    description={`For kwitansi #${parent.id}.`}
                />
                <KwitansiKwitansiDetForm
                    action={KwitansiKwitansiDetController.store.form(
                        routeKey(parent),
                    )}
                    cancelHref={KwitansiController.edit.url(routeKey(parent))}
                />
            </div>
        </>
    );
}

KwitansiKwitansiDetCreate.layout = {
    breadcrumbs: [
        { title: 'Kwitansis', href: KwitansiController.index() },
        { title: 'Add kwitansi det', href: '#' },
    ],
};
