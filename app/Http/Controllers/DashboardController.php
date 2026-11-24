<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Halaman dashboard. Statistik user hanya dihitung & dikirim untuk user
 * yang berhak melihat data user (permission users.view).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('dashboard', [
            'account' => [
                'roles' => $user->getRoleNames()->values(),
                'active_devices' => $user->tokens()->count(),
                'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
                'member_since' => $user->created_at?->toIso8601String(),
            ],
            'stats' => $user->can(PermissionName::UsersView) ? $this->stats() : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        // Satu query agregat untuk 3 angka (bukan 3 query COUNT terpisah).
        /** @var object{total: int|string, active: int|string, new_this_week: int|string} $counts */
        $counts = DB::table('users')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(is_active = 1) AS active')
            ->selectRaw('SUM(created_at >= ?) AS new_this_week', [now()->subDays(7)])
            ->first();

        $roles = DB::table('roles')
            ->leftJoin('model_has_roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->groupBy('roles.id', 'roles.name')
            ->orderByDesc(DB::raw('COUNT(model_has_roles.model_id)'))
            ->get(['roles.name', DB::raw('COUNT(model_has_roles.model_id) AS users_count')])
            ->map(fn (object $row) => ['name' => $row->name, 'users_count' => (int) $row->users_count]);

        $recentUsers = User::query()
            ->select(['id', 'name', 'email', 'is_active', 'created_at'])
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_active' => $u->is_active,
                'created_at' => $u->created_at?->toIso8601String(),
            ]);

        return [
            'total_users' => (int) $counts->total,
            'active_users' => (int) $counts->active,
            'new_this_week' => (int) $counts->new_this_week,
            'active_tokens' => PersonalAccessToken::query()
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count(),
            'roles' => $roles,
            'recent_users' => $recentUsers,
        ];
    }
}
