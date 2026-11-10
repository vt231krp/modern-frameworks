<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthService
{
    /**
     * Create a client account together with its member profile.
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = new User;
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->password = $data['password'];
            // Role is never taken from the request: self-registration is always a client.
            $user->role = Role::Client;
            $user->save();

            $member = new Member;
            $member->user_id = $user->id;
            $member->name = $user->name;
            $member->phone = $data['phone'] ?? null;
            $member->join_date = now()->toDateString();
            $member->save();

            return $user;
        });
    }

    /**
     * Authenticate the given credentials and return a JWT, or null on failure.
     */
    public function attempt(array $credentials): ?string
    {
        $token = $this->guard()->attempt($credentials);

        return is_string($token) ? $token : null;
    }

    /**
     * Issue a JWT for an already resolved user.
     */
    public function login(User $user): string
    {
        return (string) $this->guard()->login($user);
    }

    /**
     * Rotate the JWT; the previous token is blacklisted.
     */
    public function refresh(): string
    {
        return (string) $this->guard()->refresh();
    }

    /**
     * Invalidate the current JWT.
     */
    public function logout(): void
    {
        $this->guard()->logout();
    }

    /**
     * JWT lifetime in seconds.
     */
    public function expiresIn(): int
    {
        return (int) $this->guard()->getTTL() * 60;
    }

    /**
     * Resolve the authenticated user or abort with 401.
     */
    public function currentUser(): User
    {
        $user = $this->guard()->user();

        if (! $user instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        return $user;
    }

    /**
     * Resolve the JWT guard with a concrete type for static analysis.
     */
    public function guard(): JWTGuard
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');

        assert($guard instanceof JWTGuard);

        return $guard;
    }
}
