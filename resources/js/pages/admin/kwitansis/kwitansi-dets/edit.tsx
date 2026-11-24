import { Head } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import KwitansiController from '@/actions/App/Http/Controllers/Admin/KwitansiController';
import KwitansiKwitansiDetController from '@/actions/App/Http/Controllers/Admin/KwitansiKwitansiDetController';
import { KwitansiKwitansiDetForm } from '@/components/admin/kwitansi-kwitansi-det-form';
import type { KwitansiKwitansiDet } from '@/components/admin/kwitansi-kwitansi-det-form';
import { PageHeader } from '@/components/page-header';
import { routeKey } from '@/lib/route-key';

type Parent = { id: number; ulid?: string | null; [key: string]: unknown };

export default function KwitansiKwitansiDetEdit({
    parent,
    item,
}: {
    parent: Parent;
    item: KwitansiKwitansiDet;
}) {
    return (
        <>
            <Head title={`Edit kwitansi det #${item.id}`} />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={Pencil}
                    title={`Edit kwitansi det #${item.id}`}
                    description={`Of kwitansi #${parent.id}.`}
                />
                <KwitansiKwitansiDetForm
                    action={KwitansiKwitansiDetController.update.form({
                        kwitansi: routeKey(parent),
                        kwitansi_det: routeKey(item),
                    })}
                    item={item}
                    cancelHref={KwitansiController.edit.url(routeKey(parent))}
                />
            </div>
        </>
    );
}

KwitansiKwitansiDetEdit.layout = {
    breadcrumbs: [
        { title: 'Kwitansis', href: KwitansiController.index() },
        { title: 'Edit kwitansi det', href: '#' },
    ],
};
