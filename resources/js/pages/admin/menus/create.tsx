import { Head } from '@inertiajs/react';
import { ListTree } from 'lucide-react';
import MenuController from '@/actions/App/Http/Controllers/Admin/MenuController';
import { MenuForm } from '@/components/admin/menu-form';
import { PageHeader } from '@/components/page-header';
import admin from '@/routes/admin';
import type { CrudGeneratorOptions, MenuLocation, Option } from '@/types';

export default function MenusCreate({
    location,
    parentId,
    parentOptions,
    iconOptions,
    roleOptions,
    permissionOptions,
    crudGenerator,
}: {
    location: MenuLocation;
    parentId: number | null;
    parentOptions: Option[];
    iconOptions: string[];
    roleOptions: string[];
    permissionOptions: Option[];
    crudGenerator: CrudGeneratorOptions | null;
}) {
    const parent = parentOptions.find((p) => p.value === String(parentId));

    return (
        <>
            <Head title="Add menu" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={ListTree}
                    title={
                        parent ? `Add submenu to ${parent.label}` : 'Add menu'
                    }
                    description={
                        location === 'admin'
                            ? 'New item for the dashboard sidebar.'
                            : 'New item for the website navbar.'
                    }
                />
                <MenuForm
                    action={MenuController.store.form()}
                    location={location}
                    parentOptions={parentOptions}
                    iconOptions={iconOptions}
                    roleOptions={roleOptions}
                    permissionOptions={permissionOptions}
                    defaultParentId={parentId}
                    crudGenerator={crudGenerator}
                />
            </div>
        </>
    );
}

MenusCreate.layout = {
    breadcrumbs: [
        { title: 'Menus', href: admin.menus.index() },
        { title: 'Add', href: '#' },
    ],
};
