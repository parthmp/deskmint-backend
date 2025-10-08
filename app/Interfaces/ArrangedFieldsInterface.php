<?php

	namespace App\Interfaces;

	interface ArrangedFieldsInterface{

		public function fetchDefaultArrangedFieldsData(int $company_id);

		public function getType() : string;

		public function getIdColumn() : string;

		public function getJsonColumn() : string;

	}