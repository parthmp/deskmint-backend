<?php

	namespace App\Services;

	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	class ManageFlatTable{

		private $flat_table_name = '';
		private $ref_table = '';
		private $foreign = '';

		public function __construct(string $flat_table_name, string $ref_table,string $foreign){
			$this->flat_table_name = $flat_table_name;
			$this->ref_table = $ref_table;
			$this->foreign = $foreign;
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
			if(!Schema::hasColumn($this->flat_table_name, $name)){
				Schema::table($this->flat_table_name, function (Blueprint $table) use ($name, $type){
					$this->applyColumn($table, $type, $name);
				});
			}
		}

		public function editFlatTableColumn(string $from, string $to, ?string $type = null):void{
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
			if(Schema::hasColumn($this->flat_table_name, $name)){
				Schema::table($this->flat_table_name, function (Blueprint $table) use ($name){
					$table->dropColumn($name);
				});
			}
		}

		protected function applyColumn(Blueprint $table, string $type, string $name): void{
			
			$parts = explode(':', $type);
			$base_type = $parts[0];
			$params = isset($parts[1]) ? explode(',', $parts[1]) : [];

			switch($base_type){
				case 'string':
					$length = $params[0] ?? 191;
					$table->string($name, $length)->default('');
					break;

				case 'decimal':
					$precision = $params[0] ?? 12;
					$scale = $params[1] ?? 2;
					$table->decimal($name, $precision, $scale)->default(0);
					break;

				case 'text':
					$table->text($name)->nullable();
					break;

				case 'integer':
				case 'bigInteger':
					$table->$base_type($name)->default(0);
					break;

				case 'boolean':
					$table->boolean($name)->default(false);
					break;

				case 'date':
				case 'datetime':
				case 'time':
					$table->$base_type($name)->nullable();
					break;

				case 'json':
					$table->json($name)->nullable();
					break;

				default:
					$table->$base_type($name);
			}

		}




	}