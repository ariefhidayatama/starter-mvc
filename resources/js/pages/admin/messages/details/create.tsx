import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import MessageController from '@/actions/App/Http/Controllers/Admin/MessageController';
import MessageDetailController from '@/actions/App/Http/Controllers/Admin/MessageDetailController';
import { MessageDetailForm } from '@/components/admin/message-detail-form';
import { PageHeader } from '@/components/page-header';

type Parent = { id: number; [key: string]: unknown };

export default function MessageDetailCreate({ parent }: { parent: Parent }) {
    return (
        <>
            <Head title="Add detail" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={Plus}
                    title="Add detail"
                    description={`For message #${parent.id}.`}
                />
                <MessageDetailForm
                    action={MessageDetailController.store.form(parent.id)}
                    cancelHref={MessageController.edit.url(parent.id)}
                />
            </div>
        </>
    );
}

MessageDetailCreate.layout = {
    breadcrumbs: [
        { title: 'Messages', href: MessageController.index() },
        { title: 'Add detail', href: '#' },
    ],
};
