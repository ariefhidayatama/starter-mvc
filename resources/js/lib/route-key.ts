/** A row as sent by the server: always an id, a ulid when the table has one. */
export type Keyed = { id: number; ulid?: string | null };

/**
 * A row's URL key: its ulid (string) or id (number).
 *
 * Typed as `never` on purpose. Wayfinder types the URL parameter from the
 * table when it generates the routes: `string` with a "ulid" column,
 * `number` without. `never` is assignable to both, so the same page code
 * keeps type-checking before and after `php artisan make:ulid-migration`.
 * At runtime it is a plain string or number.
 */
export type RouteKey = never;

/**
 * Value for a row in admin URLs: its ULID when the table has a "ulid"
 * column, otherwise its id. Matches getRouteKeyName() of models using
 * App\Models\Concerns\HasOptionalPublicUlid (CRUD generator modules).
 *
 *   KwitansiController.edit(routeKey(item)) → /admin/kwitansis/01jd8q.../edit
 */
export function routeKey(item: Keyed): RouteKey {
    return (item.ulid ?? item.id) as RouteKey;
}
