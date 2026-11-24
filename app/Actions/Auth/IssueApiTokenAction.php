<?php

namespace App\Actions\Auth;

use App\Enums\PermissionName;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\NewAccessToken;

/**
 * Login klien API (mobile / pihak ketiga) → menghasilkan Bearer token.
 *
 * Aturan:
 * 1. Email & password harus benar.
 * 2. Akun harus aktif.
 * 3. Jika user mengaktifkan 2FA, wajib mengirim "code" (dari aplikasi
 *    authenticator) atau "recovery_code". Tanpa ini, 2FA yang dipasang
 *    di web bisa dilewati lewat API.
 * 4. Role user harus punya permission "api.access".
 */
class IssueApiTokenAction
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly TwoFactorAuthenticationProvider $twoFactor,
    ) {}

    /**
     * @param  array{email: string, password: string, device_name: string, code?: string|null, recovery_code?: string|null}  $credentials
     * @return array{user: User, token: NewAccessToken}
     */
    public function execute(array $credentials): array
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Please contact an administrator.',
            ]);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $this->verifyTwoFactor($user, $credentials['code'] ?? null, $credentials['recovery_code'] ?? null);
        }

        // Role tanpa "api.access" tidak diberi token sama sekali (403), bukan
        // diberi token yang nanti selalu ditolak. Dicek SETELAH password & 2FA
        // benar, agar tamu tidak bisa menebak role sebuah email.
        if (! $user->can(PermissionName::ApiAccess)) {
            throw new AuthorizationException('Your role is not allowed to use the API.');
        }

        return [
            'user' => $user,
            'token' => $this->tokens->issue($user, $credentials['device_name']),
        ];
    }

    private function verifyTwoFactor(User $user, ?string $code, ?string $recoveryCode): void
    {
        if (blank($code) && blank($recoveryCode)) {
            throw ValidationException::withMessages([
                'code' => 'This account uses two-factor authentication. Send a code from your authenticator app or a recovery code.',
            ]);
        }

        if (filled($code)) {
            $secret = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

            if ($this->twoFactor->verify($secret, $code)) {
                return;
            }
        }

        if (filled($recoveryCode)) {
            /** @var list<string> $codes */
            $codes = $user->recoveryCodes();
            $match = collect($codes)->first(fn (string $c) => hash_equals($c, $recoveryCode));

            if ($match !== null) {
                // Recovery code hanya boleh dipakai SEKALI → diganti kode baru.
                $user->replaceRecoveryCode($match);

                return;
            }
        }

        throw ValidationException::withMessages([
            'code' => 'The two-factor authentication code is invalid.',
        ]);
    }
}
