import { Head } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { UserForm } from '@/components/admin/user-form';
import { PageHeader } from '@/components/page-header';
import admin from '@/routes/admin';
import type { Option } from '@/types';

export default function UsersCreate({
    roleOptions,
}: {
    roleOptions: Option[];
}) {
    return (
        <>
            <Head title="Add user" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={UserPlus}
                    title="Add user"
                    description="Users created by an admin are treated as email-verified."
                />
                <UserForm
                    action={UserController.store.form()}
                    roleOptions={roleOptions}
                />
            </div>
        </>
    );
}

UsersCreate.layout = {
    breadcrumbs: [
        { title: 'Users', href: admin.users.index() },
        { title: 'Add', href: admin.users.create() },
    ],
};
