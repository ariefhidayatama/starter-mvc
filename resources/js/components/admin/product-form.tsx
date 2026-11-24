import { Form, Link } from '@inertiajs/react';
import { Box, DollarSign, Tag } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { ToggleSwitch } from '@/components/toggle-switch';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import admin from '@/routes/admin';
import type { Option, Product } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

function Section({
    icon: Icon,
    title,
    description,
    children,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icon className="size-4.5" />
                </div>
                <div className="space-y-1">
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
            </CardHeader>
            <CardContent className="space-y-5">{children}</CardContent>
        </Card>
    );
}

/** Product form shared by the Create & Edit pages. */
export function ProductForm({
    action,
    categoryOptions,
    product,
}: {
    action: RouteFormDefinition<'post'>;
    categoryOptions: Option[];
    product?: Product;
}) {
    const [categoryId, setCategoryId] = useState(
        product?.category ? String(product.category.id) : '',
    );
    const [isActive, setIsActive] = useState(product?.is_active ?? true);

    return (
        <Form {...action} className="max-w-3xl space-y-6">
            {({ processing, errors }) => (
                <>
                    <Section
                        icon={Tag}
                        title="Product details"
                        description="How the product appears in the catalog."
                    >
                        <div className="grid items-start gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="name" required>
                                    Name
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={product?.name}
                                    placeholder="Arabica Gayo 250g"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="sku" required>
                                    SKU
                                </Label>
                                <Input
                                    id="sku"
                                    name="sku"
                                    defaultValue={product?.sku}
                                    placeholder="KOP-001"
                                    className="font-mono uppercase"
                                    required
                                />
                                <InputError message={errors.sku} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="category" required>
                                Category
                            </Label>
                            <input
                                type="hidden"
                                name="category_id"
                                value={categoryId}
                            />
                            <Select
                                value={categoryId}
                                onValueChange={setCategoryId}
                            >
                                <SelectTrigger
                                    id="category"
                                    className="w-full sm:w-72"
                                >
                                    <SelectValue placeholder="Choose a category" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categoryOptions.map((c) => (
                                        <SelectItem
                                            key={c.value}
                                            value={c.value}
                                        >
                                            {c.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.category_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description" optional>
                                Description
                            </Label>
                            <Textarea
                                id="description"
                                name="description"
                                defaultValue={product?.description ?? ''}
                                rows={4}
                            />
                            <InputError message={errors.description} />
                        </div>
                    </Section>

                    <Section
                        icon={DollarSign}
                        title="Pricing & inventory"
                        description="Price in IDR. Stock is the number of units available."
                    >
                        <div className="grid items-start gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="price" required>
                                    Price (IDR)
                                </Label>
                                <Input
                                    id="price"
                                    name="price"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="0.01"
                                    defaultValue={product?.price}
                                    placeholder="85000"
                                    required
                                />
                                <InputError message={errors.price} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="stock" required>
                                    Stock
                                </Label>
                                <Input
                                    id="stock"
                                    name="stock"
                                    type="number"
                                    inputMode="numeric"
                                    min="0"
                                    step="1"
                                    defaultValue={product?.stock ?? 0}
                                    required
                                />
                                <InputError message={errors.stock} />
                            </div>
                        </div>
                    </Section>

                    <Section
                        icon={Box}
                        title="Visibility"
                        description="Inactive products are hidden from customers and the public API."
                    >
                        <div className="flex items-center justify-between gap-4 rounded-xl border p-4">
                            <div className="space-y-0.5">
                                <p className="font-medium">Active</p>
                                <p className="text-sm text-muted-foreground">
                                    Show this product in the catalog.
                                </p>
                            </div>
                            <ToggleSwitch
                                name="is_active"
                                checked={isActive}
                                onChange={setIsActive}
                                label="Active"
                            />
                        </div>
                        <InputError message={errors.is_active} />
                    </Section>

                    <div className="flex items-center justify-end gap-2">
                        <Button variant="ghost" asChild>
                            <Link href={admin.products.index()}>Cancel</Link>
                        </Button>
                        <Button
                            disabled={processing}
                            className="shadow-sm shadow-primary/30"
                        >
                            {product ? 'Save changes' : 'Create product'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
