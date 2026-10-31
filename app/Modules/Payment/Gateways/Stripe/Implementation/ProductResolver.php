<?php

namespace App\Modules\Payment\Gateways\Stripe\Implementation;

use Illuminate\Support\Facades\Cache;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient as Provider;

class ProductResolver {

	public function __construct(
		private Provider $provider
	){}

	/**
	 * id function
	 *
	 * @return string
	 */
	public function id() : string {

		$product_id = config('global.stripe_product_id');

		$cache_key = 'stripe_product_ok_'.$product_id;

		return Cache::remember($cache_key, now()->addDay(), function () use ($product_id){
			return $this->ensure($product_id);
		});
	}

	/**
	 * ensure function
	 *
	 * @param string $product_id
	 * @return string
	 */
	private function ensure(string $product_id) : string {

		try{

			$product = $this->provider->products->retrieve($product_id);

			if(!$product->active){
				$this->provider->products->update($product_id, ['active' => true]);
			}

			return $product_id;

		}catch(InvalidRequestException $e){

			if($e->getStripeCode() !== 'resource_missing'){
				throw $e;
			}

		}

		try{

			$this->provider->products->create([
				'id'   =>  $product_id,
				'name' =>  'Subscription Plan',
			]);

		}catch(InvalidRequestException $e){
			
			if($e->getStripeCode() !== 'resource_already_exists'){
				throw $e;
			}
		}

		return $product_id;
	}
}