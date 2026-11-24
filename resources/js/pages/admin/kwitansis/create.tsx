import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import KwitansiController from '@/actions/App/Http/Controllers/Admin/KwitansiController';
import { KwitansiForm } from '@/components/admin/kwitansi-form';
import { PageHeader } from '@/components/page-header';

export default function KwitansiCreate({ maxDetails }: { maxDetails: number }) {
    return (
        <>
            <Head title="Add kwitansi" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={Plus}
                    title="Add kwitansi"
                    description="Create a new kwitansi with its kwitansi dets in one save."
                />
                <KwitansiForm
                    action={KwitansiController.store.form()}
                    maxDetails={maxDetails}
                />
            </div>
        </>
    );
}

KwitansiCreate.layout = {
    breadcrumbs: [
        { title: 'Kwitansis', href: KwitansiController.index() },
        { title: 'Add', href: KwitansiController.create() },
    ],
};
