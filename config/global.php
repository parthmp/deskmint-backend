<?php

	return [
		'otp_expiry' 			=> 	env('OTP_EXPIRY_IN_SECONDS', 600),
		'reset_code_expiry' 	=> 	env('RESET_CODE_EXPIRY_IN_SECONDS', 600),
		'error_code'			=>	env("ERROR_CODE", 422),
		'user_types'			=>	[
										'admin'		=>	1,
										'user'		=>	2
									],
		'skip_routes'			=>	['create', 'edit'],
		'field_types'			=>	[
										'text'			=>		1,
										'textarea'		=>		2,
										'email'			=>		3,
										'select'		=>		4,
										'number'		=>		5,
										'date'			=>		6,
										'time'			=>		7,
										'datetime'		=>		8,
										'telephone'		=>		9,
										'multiselect'	=>		10
									]
	];