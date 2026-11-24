import { Head } from '@inertiajs/react';
import { Package } from 'lucide-react';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import { ProductForm } from '@/components/admin/product-form';
import { PageHeader } from '@/components/page-header';
import admin from '@/routes/admin';
import type { Option, Product } from '@/types';

export default function ProductsEdit({
    product,
    categoryOptions,
}: {
    product: Product;
    categoryOptions: Option[];
}) {
    return (
        <>
            <Head title={`Edit ${product.name}`} />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    icon={Package}
                    title={product.name}
                    description={`SKU ${product.sku}`}
                />
                <ProductForm
                    action={ProductController.update.form(product.ulid)}
                    categoryOptions={categoryOptions}
                    product={product}
                />
            </div>
        </>
    );
}

ProductsEdit.layout = {
    breadcrumbs: [
        { title: 'Products', href: admin.products.index() },
        { title: 'Edit', href: '#' },
    ],
};
