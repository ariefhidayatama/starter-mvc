import { Head } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import MessageController from '@/actions/App/Http/Controllers/Admin/MessageController';
import { MessageDetailSection } from '@/components/admin/message-detail-section';
import type { MessageDetail } from '@/components/admin/message-detail-form';
import { MessageForm } from '@/components/admin/message-form';
import type { Message } from '@/components/admin/message-form';
import { PageHeader } from '@/components/page-header';
import type { Paginated } from '@/types';

export default function MessageEdit({
    item,
    details,
    abilities,
}: {
    item: Message;
    details: Paginated<MessageDetail>;
    abilities: { update: boolean };
}) {
    return (
        <>
            <Head title={`Edit ${item.whs_code}`} />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={Pencil}
                    title={`Edit ${item.whs_code}`}
                    description="Change the details and save."
                />
                <MessageForm
                    action={MessageController.update.form(item.id)}
                    item={item}
                />
                <MessageDetailSection
                    parentId={item.id}
                    details={details}
                    canManage={abilities.update}
                />
            </div>
        </>
    );
}

MessageEdit.layout = {
    breadcrumbs: [
        { title: 'Messages', href: MessageController.index() },
        { title: 'Edit', href: '#' },
    ],
};
