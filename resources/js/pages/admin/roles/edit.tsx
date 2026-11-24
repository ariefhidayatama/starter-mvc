import { Head } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { RoleForm } from '@/components/admin/role-form';
import { PageHeader } from '@/components/page-header';
import admin from '@/routes/admin';
import type { PermissionGroup, RoleForm as RoleFormData } from '@/types';

export default function RolesEdit({
    role,
    permissionGroups,
}: {
    role: RoleFormData;
    permissionGroups: PermissionGroup[];
}) {
    return (
        <>
            <Head title={`Role ${role.name}`} />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={ShieldCheck}
                    title={`Role: ${role.name}`}
                    description="Permission changes apply immediately to every user with this role."
                />
                <RoleForm
                    action={RoleController.update.form(role.ulid)}
                    permissionGroups={permissionGroups}
                    role={role}
                />
            </div>
        </>
    );
}

RolesEdit.layout = {
    breadcrumbs: [
        { title: 'Roles', href: admin.roles.index() },
        { title: 'Edit', href: '#' },
    ],
};
