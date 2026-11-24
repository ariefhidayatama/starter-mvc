import { Head } from '@inertiajs/react';
import { ShieldPlus } from 'lucide-react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { RoleForm } from '@/components/admin/role-form';
import { PageHeader } from '@/components/page-header';
import admin from '@/routes/admin';
import type { PermissionGroup } from '@/types';

export default function RolesCreate({
    permissionGroups,
}: {
    permissionGroups: PermissionGroup[];
}) {
    return (
        <>
            <Head title="Add role" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={ShieldPlus}
                    title="Add role"
                    description="Create a new role, then choose its permissions."
                />
                <RoleForm
                    action={RoleController.store.form()}
                    permissionGroups={permissionGroups}
                />
            </div>
        </>
    );
}

RolesCreate.layout = {
    breadcrumbs: [
        { title: 'Roles', href: admin.roles.index() },
        { title: 'Add', href: admin.roles.create() },
    ],
};
