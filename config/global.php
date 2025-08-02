<?php

	return [
		'otp_expiry' 			=> 	env('OTP_EXPIRY_IN_SECONDS', 600),
		'reset_code_expiry' 	=> 	env('RESET_CODE_EXPIRY_IN_SECONDS', 600),
		'error_code'			=>	env("ERROR_CODE", 422),
		'user_types'			=>	[
										'admin'		=>	1,
										'user'		=>	2
									],
		'skip_routes'			=>	['create', 'edit']
	];