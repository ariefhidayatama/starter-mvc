import { Head } from '@inertiajs/react';
import { PackagePlus } from 'lucide-react';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import { ProductForm } from '@/components/admin/product-form';
import { PageHeader } from '@/components/page-header';
import admin from '@/routes/admin';
import type { Option } from '@/types';

export default function ProductsCreate({
    categoryOptions,
}: {
    categoryOptions: Option[];
}) {
    return (
        <>
            <Head title="Add product" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={PackagePlus}
                    title="Add product"
                    description="Create a new product in the catalog."
                />
                <ProductForm
                    action={ProductController.store.form()}
                    categoryOptions={categoryOptions}
                />
            </div>
        </>
    );
}

ProductsCreate.layout = {
    breadcrumbs: [
        { title: 'Products', href: admin.products.index() },
        { title: 'Add', href: admin.products.create() },
    ],
};
