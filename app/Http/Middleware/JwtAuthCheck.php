<?php

namespace App\Http\Middleware;

use Closure;
use Cookie;
use Illuminate\Support\Carbon;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class JwtAuthCheck
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $token = $request->cookie('jwt_auth');

        // Check JWT auth header
        if ($token) {
            JWTAuth::setToken($token);
            try {
                // Parse token if user found
                $user = JWTAuth::authenticate();
                if (!$user) {
                    $content = [
                        'success' => false,
                        'error' => 'User not found.',
                    ];

                    return response()->json($content)->setStatusCode(404);
                } else {
                    return $next($request);
                }
            } catch (TokenBlacklistedException $e) {
                $content = [
                    'success' => false,
                    'error' => 'Token Blacklisted.',
                ];
                return response()->json($content)->setStatusCode(400);
            } catch (TokenInvalidException $e) {
                $cookie = \Cookie::forget('jwt_auth');

                $content = [
                    'success' => false,
                    'error' => 'Token invalid.',
                ];

                return response()->json($content)->withCookie($cookie)->setStatusCode(400);
            } catch (JWTException $e) {
                $cookie = \Cookie::forget('jwt_auth');

                error("JWT threw an exception!!", [
                    'error' => $e->getMessage(),
                ]);

                $content = [
                    'success' => false,
                    'error' => 'Token absent.',
                ];

                return response()->json($content)->withCookie($cookie)->setStatusCode(500);
            }
        } else {
            return $next($request);
        }
    }
}
