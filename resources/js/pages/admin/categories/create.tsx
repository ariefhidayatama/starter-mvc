import { Head } from '@inertiajs/react';
import { FolderPlus } from 'lucide-react';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import { CategoryForm } from '@/components/admin/category-form';
import { PageHeader } from '@/components/page-header';
import admin from '@/routes/admin';

export default function CategoriesCreate() {
    return (
        <>
            <Head title="Add category" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={FolderPlus}
                    title="Add category"
                    description="Create a new product category."
                />
                <CategoryForm action={CategoryController.store.form()} />
            </div>
        </>
    );
}

CategoriesCreate.layout = {
    breadcrumbs: [
        { title: 'Categories', href: admin.categories.index() },
        { title: 'Add', href: admin.categories.create() },
    ],
};
