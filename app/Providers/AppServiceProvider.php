<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
 use Illuminate\Database\Eloquent\Model;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
		
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
		Model::preventLazyLoading();
		
		// Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
		// 	$trace = (new \Exception)->getTraceAsString();
		// 	if(!str_contains($trace, 'Validation.php')){
		// 		\Log::error('LAZY_LOAD', [
		// 			'model'    => get_class($model),
		// 			'relation' => $relation,
		// 			'trace'    => $trace,
		// 		]);
		// 	}
		// });
    }
}
