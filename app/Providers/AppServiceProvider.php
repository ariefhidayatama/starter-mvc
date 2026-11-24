<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Services\Crud\CrudPostProcessor;
use App\Services\Crud\ProcessCrudPostProcessor;
use App\Services\Menu\MenuTreeService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // CRUD generator: langkah lanjutan (migrate, seeder, Wayfinder) dijalankan
        // sebagai proses terpisah. Test menggantinya dengan versi palsu.
        $this->app->bind(CrudPostProcessor::class, ProcessCrudPostProcessor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Strict mode Eloquent di luar production. Bug berikut akan langsung
        // melempar exception (bukan diam-diam lambat/salah):
        // - Lazy loading (penyebab N+1 query)  → wajib pakai with()/load()
        // - Mengisi atribut yang tidak ada di $fillable
        // - Membaca atribut yang tidak ikut di-SELECT
        Model::shouldBeStrict(! app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Otorisasi: super admin & policy untuk model milik paket spatie.
     */
    protected function configureAuthorization(): void
    {
        // Super admin lolos SEMUA pemeriksaan $user->can(...), Policy & Gate.
        // Wajib mengembalikan null (bukan false) untuk user lain, supaya
        // pemeriksaan normal tetap berjalan.
        Gate::before(fn (User $user): ?bool => $user->hasRole(RoleName::Admin) ? true : null);

        Gate::policy(Role::class, RolePolicy::class);

        // Dokumentasi API (Scramble, /docs/api). Di APP_ENV=local terbuka untuk
        // semua; di environment lain hanya user login yang punya "api.access"
        // (admin selalu lolos lewat Gate::before di atas).
        Gate::define('viewApiDocs', fn (User $user): bool => $user->can('api.access'));

        // Pohon menu yang di-cache menyimpan NAMA role per menu. Saat role
        // diganti nama / dihapus, cache dibuang agar sidebar tetap benar.
        Role::saved(fn () => app(MenuTreeService::class)->flush());
        Role::deleted(fn () => app(MenuTreeService::class)->flush());
    }

    /**
     * Rate limiter untuk API. Limiter "login" (5x/menit per email+IP)
     * sudah didefinisikan di FortifyServiceProvider dan dipakai ulang
     * oleh endpoint login API.
     */
    protected function configureRateLimiting(): void
    {
        // 60 request/menit per user (jika login) atau per IP (jika tamu).
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });
    }
}
