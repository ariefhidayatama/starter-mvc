import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import MessageController from '@/actions/App/Http/Controllers/Admin/MessageController';
import { MessageForm } from '@/components/admin/message-form';
import { PageHeader } from '@/components/page-header';

export default function MessageCreate({ maxDetails }: { maxDetails: number }) {
    return (
        <>
            <Head title="Add message" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={Plus}
                    title="Add message"
                    description="Create a new message and its detail rows in one save."
                />
                <MessageForm
                    action={MessageController.store.form()}
                    maxDetails={maxDetails}
                />
            </div>
        </>
    );
}

MessageCreate.layout = {
    breadcrumbs: [
        { title: 'Messages', href: MessageController.index() },
        { title: 'Add', href: MessageController.create() },
    ],
};
