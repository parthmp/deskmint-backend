<?php

	return [
		'otp_expiry' 	=> 	env('OTP_EXPIRY_IN_SECONDS', 600),
		'error_code'	=>	env("ERROR_CODE", 422)
	];