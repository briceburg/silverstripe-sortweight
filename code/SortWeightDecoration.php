<?php
class SortWeightDecoration extends DataObjectDecorator {

	private static $new_tables = array();
	private static $current_relation = array(null,null,null);

	function updateSummaryFields(&$fields){

		parent::updateSummaryFields($fields);

		// TODO: better way to detect relation / name of complex table field --
		//    possible core patch.
		foreach(SS_Backtrace::filtered_backtrace() as $bt)
		{
			if($bt['function'] == '__construct' && $bt['class'] == 'ComplexTableField')
			{
				$obj = (is_object($bt['args'][0])) ? $bt['args'][0] : null;
				$class = ($obj) ? $obj->class : null;
				$classID = ($obj) ? $obj->ID : null;
				$relationName = $bt['args'][1];
				$relationClass = $bt['args'][2];

				if(isset(SortWeightRegistry::$relations[$class]) && isset(SortWeightRegistry::$relations[$class][$relationName]) && SortWeightRegistry::$relations[$class][$relationName] == $relationClass)
				{
					$fields['SortWeight'] = 'Sort Order';
					self::$current_relation = array($class,$classID,$relationName,$relationClass);
					Requirements::css(THIRDPARTY_DIR . '/jquery-ui-themes/smoothness/jquery.ui.all.css');
					Requirements::javascript(SAPPHIRE_DIR . '/thirdparty/jquery-ui/jquery.ui.core.js');
					Requirements::javascript(SAPPHIRE_DIR . '/thirdparty/jquery-ui/jquery.ui.widget.js');
					Requirements::javascript(SAPPHIRE_DIR . '/thirdparty/jquery-ui/jquery.ui.mouse.js');
					Requirements::javascript(SAPPHIRE_DIR . '/thirdparty/jquery-ui/jquery.ui.sortable.js');
					Requirements::javascript(SortWeightRegistry::$module_path . "/javascript/SortWeightFields.js");


					// lazily add weights
					// TODO: implement toggle to order newly related objects first|last. currently only support last.
					$components = $obj->$relationName();
					if(!$components->is_a('DataObjectSet'))
					{
						$components = new DataObjectSet($components);
					}

					foreach($components as $component)
					{
						$table = $this->getSWTableName($relationName, $class);
						// TODO: optimize this. could use a manual query [left join where SortWeight=null] vs. checking each component record for SortWeight...
						// TODO: provide support for tsql (MS SQL) nullif
						if(!(bool) DB::query("SELECT count(*) FROM \"$table\" WHERE \"sw{$class}ID\" = {$obj->ID} AND \"sw{$relationClass}ID\" = {$component->ID}")->value())
						{
							DB::query("INSERT INTO $table (\"SortWeight\",\"sw{$class}ID\",\"sw{$relationClass}ID\") SELECT IFNULL(MAX(SortWeight)+1,1), {$obj->ID}, {$component->ID}  FROM \"$table\"");
						}
					}

					return;
				}
			}
		}
	}

	function SortWeight(){
		list($class, $classID, $relationName, $relationClass) = self::$current_relation;
		return $class . ':' . $classID . ':' . $relationName . ':' . $relationClass . ':' . $this->owner->ID;
	}


	function augmentDatabase(){

		foreach($this->getRelations() as $relations)
		{
			list($class, $relationName, $relationClass) = $relations;
			$table = $this->getSWTableName($relationName, $class);
			$db = array(
				"SortWeight"				=>	'Int',
				"sw{$class}ID"				=>	'Int',
				"sw{$relationClass}ID"		=>	'Int'
			);

			$indexes = array(
				"sw{$class}ID"	=>	true,
				"sw{$relationClass}ID"		=>	true,
				"class-relation"			=>	array('type' => 'unique', 'value' => "sw{$class}ID,sw{$relationClass}ID")
			);

			if(!DB::getConn()->hasTable($table))
			{
				DB::requireTable($table, $db, $indexes);
				self::$new_tables[] = $relations;
			}
		}
	}

	function requireDefaultRecords(){
		foreach(self::$new_tables as $relations)
		{
			list($class, $relationName, $relationClass) = $relations;

			$table = $this->getSWTableName($relationName);

			$do = singleton($class);
			$components = $do->$relationName();

			if(!$components->is_a('DataObjectSet'))
			{
				$components = new DataObjectSet($components);
			}

			foreach($components as $component)
			{
				DB::query("INSERT INTO $table (\"SortWeight\",\"sw{$class}ID\",\"sw{$relationClass}ID\") SELECT count(*)+1, {$do->ID}, {$component->ID}  FROM \"$table\" WHERE \"sw{$relationClass}ID\" = {$component->ID}");
			}
		}
	}

	function augmentSQL(&$query) {
		if(empty($query->orderby) ||$query->orderby == '[SortWeight]')
		{

			// TODO: better way to detect relation getter?
			//    possible core patch.

			// relationship getters from DataObject
			$filter = array(
				'getManyManyComponents',
				'getComponents',
				'FieldHolder'			// ComplexTableField
			);

			foreach(SS_Backtrace::filtered_backtrace() as $bt)
			{
				if(in_array($bt['function'],$filter) && ($bt['class'] == 'DataObject' || $bt['class'] == 'ComplexTableField'))
				{
					$obj = ($bt['class'] == 'ComplexTableField') ? $bt['object']->controller : $bt['object'];

					foreach($this->getRelations($obj->class) as $relations)
					{
						list($class, $relationName, $relationClass) = $relations;
						$table = $this->getSWTableName($relationName,$class);
						$query->leftJoin($table,"\"$table\".\"sw{$class}ID\" = '{$obj->ID}' AND \"$table\".\"sw{$relationClass}ID\" = \"{$relationClass}\".\"ID\"");
						$query->orderby("\"$table\".\"SortWeight\" " . SortWeightRegistry::$direction);
						return;
					}
				}
			}

			$query->orderby = (isset(SortWeightRegistry::$default_sorts[$this->owner->class])) ?
				SortWeightRegistry::$default_sorts[$this->owner->class] : null;
		}
	}

	public function onBeforeDelete() {
		parent::onBeforeDelete();

		foreach($this->getRelations() as $relations)
		{
			list($class, $relationName, $relationClass) = $relations;
			$table = $this->getSWTableName($relationName, $class);
			DB::query("DELETE FROM $table WHERE \"sw{$relationClass}ID\" = {$this->owner->ID}");
		}

	}

	function getSWTableName($relationName, $class = null)
	{
		return ($class) ?
			$class . '_' . $relationName . 'Weight' :
			$this->owner->class . '_' . $relationName . 'Weight';
	}

	private function getRelations($classname = null)
	{
		$out = array();
		foreach(SortWeightRegistry::$relations as $class => $relations)
		{
			if($classname && $classname != $class)
				continue;

			foreach($relations as $relationName => $relationClass)
			{
				if($relationClass == $this->owner->class)
				{
					$out[] = array($class, $relationName, $relationClass);
				}
			}
		}
		return $out;
	}

}