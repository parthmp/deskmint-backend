<?php

	namespace App\Services;

use App\Helpers\General;
use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	class ManageFlatTable{

		private $flat_table_name = '';
		private $ref_table = '';
		private $foreign = '';

		public function __construct(string $flat_table_name, string $ref_table, string $foreign){
			$this->flat_table_name = $flat_table_name;
			$this->ref_table = $ref_table;
			$this->foreign = $foreign;
			$this->generateDefaultTable();
		}

		public function generateDefaultTable() : void{

			if(!$this->ifFlatTableExists()){

				Schema::create($this->flat_table_name, function (Blueprint $table) {
					$table->id();
					$table->unsignedBigInteger($this->foreign);
					$table->foreign($this->foreign)->references('id')->on($this->ref_table)->onDelete('cascade');
					$table->timestamps();
				});

			}
			
		}

		public function ifFlatTableExists() : bool{
			
			if(Schema::hasTable($this->flat_table_name)){
				return true;
			}

			return false;
		}

		/* $object->addFlatTableColumn('price', 'decimal:12,2'); */
		/* $object->addFlatTableColumn('email', 'string:191'); */
		public function addFlatTableColumn(string $name, string $type = 'string'){
			$name = General::replaceWithUnderscores($name);
			if(!Schema::hasColumn($this->flat_table_name, $name)){
				Schema::table($this->flat_table_name, function (Blueprint $table) use ($name, $type){
					$this->applyColumn($table, $type, $name);
				});
			}
		}

		public function editFlatTableColumn(string $from, string $to, ?string $type = null):void{

			$from = General::replaceWithUnderscores($from);
			$to = General::replaceWithUnderscores($to);
			
			if(Schema::hasColumn($this->flat_table_name, $from)){
				
				Schema::table($this->flat_table_name, function (Blueprint $table) use ($from, $to, $type){
					
					if($from !== $to){
						$table->renameColumn($from, $to);
					}

					if($type !== null){
						$this->applyColumn($table, $type, $to)->change();
					}
					
				});
			}

		}

		public function dropColumn(string $name){
			$name = General::replaceWithUnderscores($name);
			if(Schema::hasColumn($this->flat_table_name, $name)){
				Schema::table($this->flat_table_name, function (Blueprint $table) use ($name){
					$table->dropColumn($name);
				});
			}
		}

		public function dropColumns(array $names){

			$names = (array) $names;
			$names = array_map(fn($n) => General::replaceWithUnderscores($n), $names);

			$names = array_filter($names, fn($n) => Schema::hasColumn($this->flat_table_name, $n));

			if($names){
				Schema::table($this->flat_table_name, function (Blueprint $table) use ($names) {
					$table->dropColumn($names);
				});
			}
		}

		protected function applyColumn(Blueprint $table, string $type, string $name){
			
			$parts = explode(':', $type);
			$base_type = strtolower($parts[0]);
			$params = isset($parts[1]) ? explode(',', $parts[1]) : [];

			switch($base_type){

				case 'select':
				case 'email':
				case 'time':
				case 'telephone':
					$length = $params[0] ?? 191;
					return $table->string($name, $length)->default('');
					break;

				case 'text':
				case 'textarea':
				case 'multiselect':
					return $table->text($name);
					break;

				case 'number':
					return $table->integer($name)->nullable();
					break;
				
				case 'date':
					return $table->date($name)->nullable();
					break;

				case 'datetime':
					return $table->timestamp($name)->nullable();
					break;

				case 'json':
					return $table->json($name)->nullable();
					break;

				default:
					return $table->$base_type($name);
			}

		}




	}