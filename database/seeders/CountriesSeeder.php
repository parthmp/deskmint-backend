<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CountriesSeeder extends Seeder{

    /**
     * Run the database seeds.
     */
    public function run(): void{
		
		$countries = config('countries');

		foreach($countries as $country){
			Country::create($country);
		}

    }
}
