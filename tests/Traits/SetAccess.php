<?php

namespace Tests\Traits;

use App\Models\AccessTokenData;
use App\Models\RefreshToken;
use App\Models\User;

trait SetAccess{

	protected function set_tokens(User $user, string $device):array{
		$access_token = $user->createToken(env("APP_NAME"));
		$token_model = $access_token->accessToken;
		$plain_text_token = $access_token->plainTextToken;

		AccessTokenData::factory()->create([
			'token_id' 		=> 	$token_model->id,
			'user_id'		=> 	$user->id,
			'device'		=>	$device,
			'created_at'	=>	now()->subSeconds(3599)
		]);

		$refresh_token_plain_text = bin2hex(random_bytes(32));
		$refresh_token_hash = hash('sha512', $refresh_token_plain_text);

		RefreshToken::factory()->create([
			'user_id'		=>	$user->id,
			'refresh_token'	=>	$refresh_token_hash,
			'device'		=>	$device,
			'used'			=>	0,
			'used_at'		=>	null,
			'created_at'	=>	(now())->subSeconds(100)
		]);

		return [
			'token'			=>	$plain_text_token,
			'refresh_token'	=>	$refresh_token_hash
		];

	}

	protected function userHeaders($tokens, $device){
		return 	[
					'Accept' => 'application/json',
					'Authorization' => 'Bearer '.$tokens['token'],
					'X-Refresh-Token' => $tokens['refresh_token'],
					'X-Device-Id' => $device
		];
	}
	
	protected function set_access(string $device, $user = 'admin') :Array{

		if($user === 'admin'){
			$user = User::factory()->create([
				'user_type'		=>		config('global.user_types.admin')
			]);
		}else{

			/* handle other users here */

		}
		
		$tokens = $this->set_tokens($user, $device);

		return [
			'headers' => $this->userHeaders($tokens, $device),
			'user'	=>	$user
		];

	}

	protected function headers(User $user, string $device):array{

		$tokens = $this->set_tokens($user, $device);
		return $this->userHeaders($tokens, $device);
		
	}

}