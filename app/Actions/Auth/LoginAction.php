<?php

namespace App\Actions\Auth;

use App\Exceptions\ApiException;
use App\Models\Student;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginAction
{
    /**
     * Authenticate by phone OR student code and issue a Sanctum token.
     *
     * @return array{token: string, user: User}
     */
    public function handle(string $identifier, string $password, string $deviceName, ?string $ip = null): array
    {
        $user = $this->findUser($identifier);

        if ($user === null || ! Hash::check($password, $user->password)) {
            activity('auth')
                ->event('login_failed')
                ->withProperties(['identifier' => $identifier, 'ip' => $ip])
                ->log('login_failed');

            throw ApiException::invalidCredentials();
        }

        if (! $user->is_active) {
            activity('auth')
                ->causedBy($user)
                ->performedOn($user)
                ->event('login_blocked')
                ->withProperties(['ip' => $ip, 'reason' => 'inactive'])
                ->log('login_blocked');

            throw ApiException::accountInactive();
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $token = $user->createToken($deviceName)->plainTextToken;

        activity('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->event('login')
            ->withProperties(['ip' => $ip, 'device_name' => $deviceName])
            ->log('login');

        return ['token' => $token, 'user' => $user];
    }

    private function findUser(string $identifier): ?User
    {
        $phone = Phone::normalizeOrNull($identifier);

        if ($phone !== null) {
            return User::where('phone', $phone)->first();
        }

        return Student::query()
            ->whereRaw('LOWER(code) = ?', [Str::lower(trim($identifier))])
            ->first()
            ?->user;
    }
}
