<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    //return 'welcome';
	return ini_get('memory_limit');

	/*
	sudo setfacl -R -m u:$USER:rwx .
	sudo setfacl -R -d -m u:$USER:rwx .
	*/

});
