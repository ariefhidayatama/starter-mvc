import { Head } from '@inertiajs/react';
import { FolderOpen } from 'lucide-react';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import { CategoryForm } from '@/components/admin/category-form';
import { PageHeader } from '@/components/page-header';
import admin from '@/routes/admin';
import type { Category } from '@/types';

export default function CategoriesEdit({ category }: { category: Category }) {
    return (
        <>
            <Head title={`Edit ${category.name}`} />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={FolderOpen}
                    title={category.name}
                    description={`${category.products_count ?? 0} product(s) in this category.`}
                />
                <CategoryForm
                    action={CategoryController.update.form(category.ulid)}
                    category={category}
                />
            </div>
        </>
    );
}

CategoriesEdit.layout = {
    breadcrumbs: [
        { title: 'Categories', href: admin.categories.index() },
        { title: 'Edit', href: '#' },
    ],
};
