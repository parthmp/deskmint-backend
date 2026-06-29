<?php

namespace App\Modules\CustomFieldsFeature\FlatTable;

use App\Helpers\General;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

/**
 * FlatTable class
 */
class FlatTable{

	private string $flat_table_name = '';
	private string $ref_table = '';
	private string $foreign = '';

	/**
	 * __construct function
	 *
	 * @param string $flat_table_name
	 * @param string $ref_table
	 * @param string $foreign
	 */
	public function __construct(string $flat_table_name, string $ref_table, string $foreign){
		$this->flat_table_name = $flat_table_name;
		$this->ref_table = $ref_table;
		$this->foreign = $foreign;
		$this->generateDefaultTable();
	}

	/**
	 * generateDefaultTable function
	 *
	 * @return void
	 */
	public function generateDefaultTable() : void {

		if(!$this->ifFlatTableExists()){

			Schema::create($this->flat_table_name, function (Blueprint $table) {
				$table->id();
				$table->unsignedBigInteger($this->foreign);
				$table->foreign($this->foreign)->references('id')->on($this->ref_table)->onDelete('cascade');
				$table->timestamps();
			});

		}
		
	}

	/**
	 * ifFlatTableExists function
	 *
	 * @return boolean
	 */
	public function ifFlatTableExists() : bool {
		
		if(Schema::hasTable($this->flat_table_name)){
			return true;
		}

		return false;
	}

	/**
	 * addFlatTableColumn function
	 *
	 * @param string $name
	 * @param string $type
	 * @return void
	 */
	public function addFlatTableColumn(string $name, string $type = 'string') : void {
		$name = General::replaceWithUnderscores($name);
		if(!Schema::hasColumn($this->flat_table_name, $name)){
			Schema::table($this->flat_table_name, function (Blueprint $table) use ($name, $type){
				$this->applyColumn($table, $type, $name);
			});
		}
	}

	/**
	 * editFlatTableColumn function
	 *
	 * @param string $from
	 * @param string $to
	 * @param string|null $type
	 * @return void
	 */
	public function editFlatTableColumn(string $from, string $to, ?string $type = null) : void {

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

	/**
	 * dropColumn function
	 *
	 * @param string $name
	 * @return void
	 */
	public function dropColumn(string $name) : void {
		$name = General::replaceWithUnderscores($name);
		if(Schema::hasColumn($this->flat_table_name, $name)){
			Schema::table($this->flat_table_name, function (Blueprint $table) use ($name){
				$table->dropColumn($name);
			});
		}
	}

	/**
	 * dropColumns function
	 *
	 * @param array $names
	 * @return void
	 */
	public function dropColumns(array $names) : void {

		$names = (array) $names;
		$names = array_map(fn($n) => General::replaceWithUnderscores($n), $names);

		$names = array_filter($names, fn($n) => Schema::hasColumn($this->flat_table_name, $n));

		if($names){
			Schema::table($this->flat_table_name, function (Blueprint $table) use ($names) {
				$table->dropColumn($names);
			});
		}
	}

	/**
	 * applyColumn function
	 *
	 * @param Blueprint $table
	 * @param string $type
	 * @param string $name
	 * @return ColumnDefinition
	 */
	protected function applyColumn(Blueprint $table, string $type, string $name) : ColumnDefinition {
		
		$parts = explode(':', $type);
		$base_type = strtolower($parts[0]);
		$params = isset($parts[1]) ? explode(',', $parts[1]) : [];

		switch($base_type){

			case 'select':
			case 'email':
			case 'time':
			case 'telephone':
				$length = $params[0] ?? 191;
				return $table->string($name, $length)->nullable()->default('');
				break;

			case 'text':
			case 'textarea':
			case 'multiselect':
				return $table->text($name)->nullable();
				break;

			case 'number':
				return $table->integer($name)->nullable();
				break;
			
			case 'date':
			case 'datetime':
				return $table->timestamp($name)->nullable();
				break;

			case 'json':
				return $table->json($name)->nullable();
				break;

			default:
				$length = $params[0] ?? 191;
				return $table->string($name, $length)->nullable()->default('');
				break;
		}

	}




}