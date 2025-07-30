<?php

namespace App\Http\Middleware;

use App\Helpers\Sanitize;
use App\Models\Company;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class DefaultCompany
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response{

		$v = Validator::make($request->all(), [
			'company_id'		=>	'required'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request'], config('global.error_code'));
		}
		
		$company_id = Sanitize::input($request->input('company_id'));

		$company = Company::where('id', '=', $company_id)->first();
		if(!$company){
			return response(['message' => 'Invalid request'], config('global.error_code'));
		}


		$response = $next($request);
		
		if ($response instanceof JsonResponse) {
			$original = $response->getData(true);

			if(is_array($original)){
				$original['company_id'] = $company_id;
				/*$response->setData($original);*/
			}
		}

        return $response;
    }
}
