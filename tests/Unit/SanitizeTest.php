<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Helpers\Sanitize;

class SanitizeTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_sanitize_input_function_trim(): void
    {
        $temp = Sanitize::input('test123 ');
		$this->assertEquals('test123', $temp);
    }

	public function test_sanitize_input_function_strip_tags(): void
    {
        $temp = Sanitize::input('test123<h1></h1><script>BLA</script>');
		$this->assertEquals('test123BLA', $temp);
    }

	public function test_sanitize_input_function_strip_slashes(): void
    {
        $temp = Sanitize::input('tes/t12\3');
		$this->assertEquals('tes/t123', $temp);
    }
}
