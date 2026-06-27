<?php

namespace Tests\Traits;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Assert;

trait GeneralFunctions{

	public function assertJsonWithoutIds(array $expected, array $actual): void {
		$strip = fn($arr) => collect($arr)
			->map(fn($item) => Arr::except($item, ['id']))
			->values()
			->toArray();

		Assert::assertEquals($strip($expected), $strip($actual));
	}
}