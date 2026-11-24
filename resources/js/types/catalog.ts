/**
 * Catalog types. Shapes match the Laravel API Resources
 * (CategoryResource / ProductResource), which are reused by the web pages.
 */

export type Category = {
    id: number;
    /** Random id for admin URLs, e.g. /admin/categories/01jd…/edit. */
    ulid: string;
    name: string;
    slug: string;
    description?: string | null;
    products_count?: number;
    created_at?: string;
    updated_at?: string;
};

export type Product = {
    id: number;
    /** Random id for admin URLs, e.g. /admin/products/01jd…/edit. */
    ulid: string;
    sku: string;
    name: string;
    description?: string | null;
    /** Decimal as string, e.g. "85000.00" (no float rounding errors). */
    price: string;
    stock: number;
    in_stock: boolean;
    is_active: boolean;
    category?: Pick<Category, 'id' | 'name' | 'slug'>;
    created_at: string;
    updated_at: string;
};

export type CategoryFilters = {
    search: string;
    sort: string;
    per_page: number;
};

export type ProductFilters = {
    search: string;
    sort: string;
    per_page: number;
    filter: {
        category_id: string;
        status: string;
        price_min: string;
        price_max: string;
        stock_min: string;
        stock_max: string;
    };
};
