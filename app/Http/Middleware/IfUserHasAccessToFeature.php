<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IfUserHasAccessToFeature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response{

		$user = Auth::user();
		
		if($user->user_type !== config('global.user_types.admin')){
			return response(['message' => 'You are not allowed to view/use this feature', 'validity' => 'not_allowed'], 401);
		}

		/* handle user features access here in the future */

        return $next($request);

    }
}
