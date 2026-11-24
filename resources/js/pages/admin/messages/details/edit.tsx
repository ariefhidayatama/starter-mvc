import { Head } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import MessageController from '@/actions/App/Http/Controllers/Admin/MessageController';
import MessageDetailController from '@/actions/App/Http/Controllers/Admin/MessageDetailController';
import { MessageDetailForm } from '@/components/admin/message-detail-form';
import type { MessageDetail } from '@/components/admin/message-detail-form';
import { PageHeader } from '@/components/page-header';

type Parent = { id: number; [key: string]: unknown };

export default function MessageDetailEdit({
    parent,
    item,
}: {
    parent: Parent;
    item: MessageDetail;
}) {
    return (
        <>
            <Head title={`Edit detail #${item.id}`} />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={Pencil}
                    title={`Edit detail #${item.id}`}
                    description={`Of message #${parent.id}.`}
                />
                <MessageDetailForm
                    action={MessageDetailController.update.form({
                        message: parent.id,
                        detail: item.id,
                    })}
                    item={item}
                    cancelHref={MessageController.edit.url(parent.id)}
                />
            </div>
        </>
    );
}

MessageDetailEdit.layout = {
    breadcrumbs: [
        { title: 'Messages', href: MessageController.index() },
        { title: 'Edit detail', href: '#' },
    ],
};
