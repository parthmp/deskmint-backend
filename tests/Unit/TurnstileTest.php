<?php

namespace Tests\Unit;

use App\Helpers\Turnstile;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Request;

class TurnstileTest extends TestCase
{

    public function test_turnstile_validate_returns_false_on_invalid_token(): void{
		
		$token = md5(uniqid());
        $result = Turnstile::validate($token, '127.0.0.1');

        $this->assertFalse($result);
	}
}
