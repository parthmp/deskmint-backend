<?php

	return [
		'otp_expiry' 						=> 	env('OTP_EXPIRY_IN_SECONDS', 600),
		'reset_code_expiry' 				=> 	env('RESET_CODE_EXPIRY_IN_SECONDS', 600),
		'error_code'						=>	env("ERROR_CODE", 422),
		'user_types'			=>	[
										'admin'		=>	1,
										'user'		=>	2
									],
		'skip_routes'			=>	['create', 'edit'],
		'field_types'			=>	[
										'text',
										'textarea',
										'email',
										'select',
										'number',
										'date',
										'time',
										'datetime',
										'telephone',
										'multiselect'
									],

		'invoice_lock_key'			=>	env('INVOICES_LOCK_KEY', 'invoice_lock'),
		'invoice_lock_seconds'		=>	env('INVOICES_LOCK_SECONDS', 10),
		'paypal_product_id'			=>	env('PAYPAL_PRODUCT_ID', 'PROD-DESKMINTRECURRING'),
		'stripe_product_id'			=>	env('STRIPE_PRODUCT_ID', 'deskmint_subscription'),

	];