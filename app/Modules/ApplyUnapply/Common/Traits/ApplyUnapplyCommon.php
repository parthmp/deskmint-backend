<?php

namespace App\Modules\ApplyUnapply\Common\Traits;

use App\Helpers\Sanitize;
use App\Services\Invoice\Exceptions\InvoiceException;

trait ApplyUnapplyCommon {

	/**
	 * getIds function
	 *
	 * @param array $applied
	 * @return array
	 */
	public function getIds(array $applied) : array {

		$ids = [];

		foreach($applied as $ele){
			$ele['id'] = Sanitize::input($ele['id']);
			if(!in_array($ele['id'], $ids)){
				array_push($ids, $ele['id']);
			}else{
				throw new InvoiceException('Duplicate entries found to apply this entry', 'duplicate_entry_ids', (int) config('global.error_code'));
			}
		}

		return $ids;

	}

}