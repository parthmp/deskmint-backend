<?php

namespace App\Modules\InvoiceGeneration\Traits;

trait Generic {

	
	/**
	 * replaceBetweenTags function
	 *
	 * @param string $text
	 * @param string $starting_tag
	 * @param string $ending_tag
	 * @param string $new_content
	 * @return string
	 */
	private function replaceBetweenTags(string $text, string $starting_tag, string $ending_tag, string $new_content) : string {

		$start_pos = strpos($text, $starting_tag);

		if ($start_pos === false) return $text;
		
		$end_pos = strpos($text, $ending_tag, $start_pos + strlen($starting_tag));

		if ($end_pos === false) return $text;
		
		$before = substr($text, 0, $start_pos + strlen($starting_tag));
		$after = substr($text, $end_pos);
		
		return $before.$new_content.$after;

	}

}