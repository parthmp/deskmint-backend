<?php

namespace Tests\Feature;

use App\Models\AccessTokenData;
use App\Models\Company;
use App\Models\CustomFieldType;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ClientsControllerStoreTest extends TestCase{

	use RefreshDatabase;

    private function set_access(User $user, string $device) :Array{

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
			'token'				=>		$plain_text_token,
			'refresh_token'		=>		$refresh_token_hash
		];

	}

	private function set_default_company() : int{

		$company = Company::factory()->create([
			'company_name' 	=>  'ABC Company',
			'default'		=>	1
		]);

		return $company->id;

	}

	private function setCustomFieldTypes() : void{

		/* set custom field types */
		foreach(config('global.field_types') as $field){

			CustomFieldType::factory()->create([
				'input_type'	=>	$field,
				'input_name'	=>	'client '.$field
			]);

		}
		

	}

}
