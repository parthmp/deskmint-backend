<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Throwable;

class CustomThrottleResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next){
        try {
            return $next($request);
        } catch (ThrottleRequestsException $e) {
            return response([
                'message' 	=> 'Too many attempts. Please wait a moment.',
                'validity' 	=> 'throttle_limit'
            ], config('global.error_code'));
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
