<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthenticateWithJwt
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->bearerToken()) {
            return $next($request);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                throw new AuthenticationException('Invalid token.');
            }
        } catch (\Throwable $e) {
            throw new AuthenticationException($e->getMessage(), 0, $e);
        }

        return $next($request);
    }
}
