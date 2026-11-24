import { Head } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import KwitansiController from '@/actions/App/Http/Controllers/Admin/KwitansiController';
import { KwitansiKwitansiDetSection } from '@/components/admin/kwitansi-kwitansi-det-section';
import type { KwitansiKwitansiDet } from '@/components/admin/kwitansi-kwitansi-det-form';
import { KwitansiForm } from '@/components/admin/kwitansi-form';
import type { Kwitansi } from '@/components/admin/kwitansi-form';
import { PageHeader } from '@/components/page-header';
import { routeKey } from '@/lib/route-key';
import type { Paginated } from '@/types';

export default function KwitansiEdit({
    item,
    kwitansiDets,
    abilities,
}: {
    item: Kwitansi;
    kwitansiDets: Paginated<KwitansiKwitansiDet>;
    abilities: { update: boolean };
}) {
    return (
        <>
            <Head title={`Edit ${item.kwitansi_no}`} />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={Pencil}
                    title={`Edit ${item.kwitansi_no}`}
                    description="Change the details and save."
                />
                <KwitansiForm
                    action={KwitansiController.update.form(routeKey(item))}
                    item={item}
                />
                <KwitansiKwitansiDetSection
                    parentKey={routeKey(item)}
                    details={kwitansiDets}
                    canManage={abilities.update}
                />
            </div>
        </>
    );
}

KwitansiEdit.layout = {
    breadcrumbs: [
        { title: 'Kwitansis', href: KwitansiController.index() },
        { title: 'Edit', href: '#' },
    ],
};
