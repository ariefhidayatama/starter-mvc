import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import admin from '@/routes/admin';
import type { Category } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/** Category form shared by the Create & Edit pages. */
export function CategoryForm({
    action,
    category,
}: {
    action: RouteFormDefinition<'post'>;
    category?: Category;
}) {
    return (
        <Form {...action} className="max-w-2xl space-y-6">
            {({ processing, errors }) => (
                <>
                    <Card>
                        <CardContent className="space-y-5">
                            <div className="grid gap-2">
                                <Label htmlFor="name" required>
                                    Name
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={category?.name}
                                    placeholder="Coffee"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slug" optional>
                                    Slug
                                </Label>
                                <Input
                                    id="slug"
                                    name="slug"
                                    defaultValue={category?.slug}
                                    placeholder="Generated from the name, e.g. coffee"
                                    className="font-mono"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Used in URLs. Leave blank to generate it
                                    automatically.
                                </p>
                                <InputError message={errors.slug} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description" optional>
                                    Description
                                </Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    defaultValue={category?.description ?? ''}
                                    rows={3}
                                    maxLength={500}
                                />
                                <InputError message={errors.description} />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center justify-end gap-2">
                        <Button variant="ghost" asChild>
                            <Link href={admin.categories.index()}>Cancel</Link>
                        </Button>
                        <Button
                            disabled={processing}
                            className="shadow-sm shadow-primary/30"
                        >
                            {category ? 'Save changes' : 'Create category'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
