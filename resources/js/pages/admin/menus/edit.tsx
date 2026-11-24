import { Head } from '@inertiajs/react';
import { ListTree } from 'lucide-react';
import MenuController from '@/actions/App/Http/Controllers/Admin/MenuController';
import { MenuForm } from '@/components/admin/menu-form';
import { PageHeader } from '@/components/page-header';
import admin from '@/routes/admin';
import type { Menu, Option } from '@/types';

export default function MenusEdit({
    menu,
    hasChildren,
    parentOptions,
    iconOptions,
    roleOptions,
    permissionOptions,
}: {
    menu: Menu;
    hasChildren: boolean;
    parentOptions: Option[];
    iconOptions: string[];
    roleOptions: string[];
    permissionOptions: Option[];
}) {
    return (
        <>
            <Head title={`Edit ${menu.title}`} />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={ListTree}
                    title={menu.title}
                    description={
                        menu.location === 'admin'
                            ? 'Dashboard sidebar menu.'
                            : 'Website navbar menu.'
                    }
                />
                <MenuForm
                    action={MenuController.update.form(menu.ulid)}
                    location={menu.location}
                    parentOptions={parentOptions}
                    iconOptions={iconOptions}
                    roleOptions={roleOptions}
                    permissionOptions={permissionOptions}
                    menu={menu}
                    hasChildren={hasChildren}
                />
            </div>
        </>
    );
}

MenusEdit.layout = {
    breadcrumbs: [
        { title: 'Menus', href: admin.menus.index() },
        { title: 'Edit', href: '#' },
    ],
};
