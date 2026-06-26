<?php

namespace App\Services\Invoice;

use App\Helpers\Sanitize;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;

class InvoiceCalculationService{

	private array $ignore_keys = [
		'tax_amount',
		'line_subtotal',
		'line_total',
		'unit_price',
		'quantity'
	];

	private BigDecimal $global_subtotal;
	private BigDecimal $global_tax_amount;
	private BigDecimal $global_total;
	private BigDecimal $global_discount_amount_post_tax;
	private BigDecimal $global_discount_amount_pre_tax;

	public function __construct(){
		$this->global_subtotal = BigDecimal::of(0);
		$this->global_tax_amount = BigDecimal::of(0);
		$this->global_total = BigDecimal::of(0);
		$this->global_discount_amount_post_tax = BigDecimal::of(0);
		$this->global_discount_amount_pre_tax = BigDecimal::of(0);
	}

	/**
	 * calculateRow function
	 *
	 * @param array $product_row
	 * @return array
	 */
	private function calculateRow(array $product_row) : array {

		$line_tax_amount = BigDecimal::of(0);
		$line_subtotal = BigDecimal::of(0);
		$line_total = BigDecimal::of(0);
		$discount = BigDecimal::of($product_row['discount'] ?? 0);
		$discount_amount = BigDecimal::of(0);
		
		
		$raw_unit = Sanitize::input($product_row['unit_price'] ?? 0);
		$unit_price = is_numeric($raw_unit) ? $raw_unit : "0";
		
		$raw_qty = Sanitize::input($product_row['quantity'] ?? 1);
		$quantity = ctype_digit((string) $raw_qty) ? (int) $raw_qty : 1;

		
		if($quantity < 1){
			$quantity = 1;
		}

		$unit_price = BigDecimal::of($unit_price);
		$quantity = BigInteger::of($quantity);

		$line_subtotal = $unit_price->multipliedBy($quantity);
		$line_total = $line_subtotal;

		$discount_amount = $discount->multipliedBy($line_subtotal)->dividedBy(100, 4, RoundingMode::HalfUp);
		$discounted_subtotal = $line_subtotal->minus($discount_amount);

		$cols = [];
		
		foreach($product_row as $key => $product_column){

			
			
			if(preg_match('/^custom_tax_/', $key) || $key === 'tax'){

				$product_column = max(0, min(100, (float) $product_column));

				/* tax */
				$rate = BigDecimal::of($product_column)->dividedBy(100, 4, RoundingMode::HalfUp);
				$tax  = $rate->multipliedBy($discounted_subtotal)->toScale(4, RoundingMode::HalfUp);

				$line_tax_amount = $line_tax_amount->plus($tax);

			}
			
			if(!in_array($key, $this->ignore_keys)){
				$cols[$key] = $product_column;
			}

		}
		
		$line_total = $discounted_subtotal->plus($line_tax_amount);

		$this->global_subtotal = $this->global_subtotal->plus($line_subtotal);
		$this->global_tax_amount = $this->global_tax_amount->plus($line_tax_amount);
		
		$this->global_total = $this->global_total->plus($line_total);

		$cols['unit_price'] = $unit_price->toScale(2, RoundingMode::HalfUp)->__toString();
		$cols['quantity'] = $quantity->toInt();

		$cols['discount'] = $discount->toScale(4, RoundingMode::HalfUp)->toString();
		//$discount_amount = $discount->multipliedBy($line_subtotal)->dividedBy(100, 4, RoundingMode::HALF_UP);

		$this->global_discount_amount_pre_tax = $this->global_discount_amount_pre_tax->plus($discount_amount);

		$cols['discount_amount'] = $discount_amount->toScale(2, RoundingMode::HalfUp)->__toString();

		$cols['tax_amount'] = $line_tax_amount->toScale(2, RoundingMode::HalfUp)->__toString();
		$cols['line_subtotal'] = $discounted_subtotal->toScale(2, RoundingMode::HalfUp)->__toString();
		$cols['line_total'] = $line_total->toScale(2, RoundingMode::HalfUp)->__toString();

		return $cols;
	}

	/**
	 * calculateInvoice function
	 *
	 * @param array $product_rows
	 * @param string $discount_type
	 * @param string $discount_number
	 * @return array
	 */
	public function calculateInvoice(array $product_rows, string $discount_type, string $discount_number) : array {

		$rows = [];

		foreach($product_rows as $product_row){
			$rows[] = $this->calculateRow($product_row);
		}

		$discount_number_dec = BigDecimal::of($discount_number);

		if($discount_type === 'amount'){
			$this->global_discount_amount_post_tax = $discount_number_dec;
			$discount_number_dec = $discount_number_dec->toScale(2, RoundingMode::HalfUp);
		}else{
			$global_discount_rate = $discount_number_dec->dividedBy(100, 4, RoundingMode::HalfUp);
			$this->global_discount_amount_post_tax  = $global_discount_rate->multipliedBy($this->global_total)->toScale(4, RoundingMode::HalfUp);
			$discount_number_dec = $discount_number_dec->toScale(4, RoundingMode::HalfUp);
		}

		$global_total = $this->global_total->minus($this->global_discount_amount_post_tax);

		$global_subtotal = $this->global_subtotal->toScale(2, RoundingMode::HalfUp)->__toString();
		$global_tax_amount = $this->global_tax_amount->toScale(2, RoundingMode::HalfUp)->__toString();
		$global_total = $global_total->toScale(2, RoundingMode::HalfUp)->__toString();
		$global_discount_amount_post_tax = $this->global_discount_amount_post_tax->toScale(2, RoundingMode::HalfUp)->__toString();
		$global_discount_amount_pre_tax = $this->global_discount_amount_pre_tax->toScale(2, RoundingMode::HalfUp)->__toString();
		$discount_number_str = $discount_number_dec->__toString();

		return [
			'global_total'						=>	$global_total,
			'global_subtotal'					=>	$global_subtotal,
			'global_tax_amount'					=>	$global_tax_amount,
			'global_discount_amount_post_tax'	=>	$global_discount_amount_post_tax,
			'global_discount_amount_pre_tax'	=>	$global_discount_amount_pre_tax,
			'discount_number'					=>	$discount_number_str,
			'rows'								=>	$rows
		];

	}

}