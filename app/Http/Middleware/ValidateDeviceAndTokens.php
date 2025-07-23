<?php

namespace App\Http\Middleware;

use App\Helpers\Sanitize;
use App\Models\AccessTokenData;
use App\Models\RefreshToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ValidateDeviceAndTokens
{

	private ?string $generated_access_token = null;
	private ?string $generated_refresh_token = null;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response{
		
		$user = Auth::user();
		if(!$user){
            return response(['message' => 'Unauthorized', 'validity' => 'unauthorized'], 401);
        }

		$v = Validator::make($request->all(), [
			'device_id'		=>	'required',
			'refresh_token'	=>	'required',
		]);

		if($v->fails()){
			return response(['message' => 'Unauthorized', 'validity' => 'unauthorized'], 401);
		}

		$device_id = Sanitize::input($request->input('device_id'));
        $refresh_token = Sanitize::input($request->input('refresh_token'));

		$access_token_id = $this->getAccessTokenId($user);

		$access_token_data = $this->checkAccessTokenWithDevice($access_token_id, $device_id);

		if(!$access_token_data){
			return response(['message' => 'Unauthorized', 'validity' => 'unauthorized'], 401);
		}

		$refresh_token_data = $this->checkRefreshTokenWithDevice($refresh_token, $device_id, $user->id);
		if(!$refresh_token_data){
			return response(['message' => 'Unauthorized', 'validity' => 'unauthorized'], 401);
		}

		if(!$this->isRefreshTokenValid($refresh_token_data)){
			return response(['message' => 'Unauthorized', 'validity' => 'unauthorized'], 401);
		}

		if(!$this->isAccessTokenValid($access_token_data) && !$this->isRefreshTokenValid($refresh_token_data)){
			return response(['message' => 'Unauthorized', 'validity' => 'unauthorized'], 401);
		}

		/* 1) if access token and refresh token are valid but refresh token is near expiry (13 days passed), issue both tokens */
		/* 2) if access token is invalid and refresh token is valid, issue both tokens */

		if(($this->isAccessTokenValid($access_token_data) && $this->isRefreshTokenValid($refresh_token_data) && $this->refreshTokenIsNearExpiry($refresh_token_data)) || (!$this->isAccessTokenValid($access_token_data) && $this->isRefreshTokenValid($refresh_token_data))){

			$this->invalidatePastAccessTokens($user->id, $device_id);
			$new_access_token = $this->issueNewAccessToken($user, $device_id, $request);

			$this->makeRefreshTokenUsed($refresh_token_data);
			$this->invalidatePastRefreshTokens($user->id, $device_id);
			$new_refresh_token = $this->issueNewRefreshToken($user, $device_id);

			$this->generated_access_token = $new_access_token;
			$this->generated_refresh_token = $new_refresh_token;

		}

		$response = $next($request);

		if($this->generated_access_token && $this->generated_refresh_token){

			$original = $response->getData(true);

			if(is_array($original)){
				$original['access_token'] = $this->generated_access_token;
				$original['refresh_token'] = $this->generated_refresh_token;
				$response->setData($original);
			}

		}
		
		return $response;

    }

	private function getAccessTokenId(User $user) : int{
		$access_token = $user->currentAccessToken();
		return $access_token->id;
	}

	private function checkAccessTokenWithDevice(int $access_token_id, string $device_id){
		return AccessTokenData::where([['token_id', '=', $access_token_id],['device', '=', $device_id]])->orderBy('id', 'desc')->first();
	}

	private function checkRefreshTokenWithDevice(string $refresh_token, string $device_id, int $user_id){
		return RefreshToken::where([['refresh_token', '=', $refresh_token], ['device', '=', $device_id], ['user_id', '=', $user_id], ['used', '=', 0]])->orderBy('id', 'desc')->first();
	}

	private function isAccessTokenValid(AccessTokenData $access_token_data) : bool{

		$diff = (now())->diffInSeconds($access_token_data->created_at, true);
		if($diff < 3600){ /* 1 hour */
			return true;
		}

		return false;

	}

	private function isRefreshTokenValid(RefreshToken $refresh_token) : bool{

		$diff = (now())->diffInSeconds($refresh_token->created_at, true);
		if($diff < 1209600){ /* 14 days */
			return true;
		}

		return false;

	}

	private function invalidatePastAccessTokens(int $user_id, string $device_id){
		AccessTokenData::where([['user_id', '=', $user_id], ['device', '=', $device_id]])->delete();
	}

	private function issueNewAccessToken(User $user, string $device_id, Request $request) : string{

		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;

		$access_token_data = new AccessTokenData();
		$access_token_data->token_id = $token_model->id;
		$access_token_data->user_id = $user->id;
		$access_token_data->device = $device_id;
		$access_token_data->user_agent = $request->header('User-Agent');
		$access_token_data->ip_address = $request->ip();
		$access_token_data->save();

		return $access_token->plainTextToken;

	}

	private function refreshTokenIsNearExpiry(RefreshToken $refresh_token) : bool{

		$diff = (now())->diffInSeconds($refresh_token->created_at, true);
		if($diff > 1123200){ /* 13 days */
			return true;
		}

		return false;

	}

	private function invalidatePastRefreshTokens(int $user_id, string $device_id){
		RefreshToken::where([['user_id', '=', $user_id], ['device', '=', $device_id]])->delete();
	}

	private function issueNewRefreshToken(User $user, string $device) : string{

		$refresh_token_plain_text = bin2hex(random_bytes(32));
		$refresh_token_hash = hash('sha512', $refresh_token_plain_text);
		$refresh_token = new RefreshToken();
		$refresh_token->user_id = $user->id;
		$refresh_token->refresh_token = $refresh_token_hash;
		$refresh_token->device = $device;
		$refresh_token->save();

		return $refresh_token_hash;

	}

	private function makeRefreshTokenUsed(RefreshToken $refresh_token){
		$refresh_token->used = 1;
		$refresh_token->update();
	}

}
