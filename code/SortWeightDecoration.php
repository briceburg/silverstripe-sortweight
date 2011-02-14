<?php
class SortWeightDecoration extends DataObjectDecorator {

	private static $new_tables = array();

	function updateSummaryFields(&$fields){
		// TODO: any way to detect relation / name of complex table field --
		//    possible core patch.

		$fields['SortWeight'] = 'Sort Order';
		// NOTE: SS 2.4.2 does not take advantage of jquery-ui in core, but links to the below 1.8.1 versions
		//  in DateField. Using the same for potential cache reasons. TODO: look to update this in future SS verions..
		Requirements::css('http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.1/themes/smoothness/jquery-ui.css');
		Requirements::javascript('http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.1/jquery-ui.min.js');

		Requirements::javascript(SortWeightRegistry::$module_path . "/javascript/SortWeightFields.js");
	}

	function augmentDatabase(){
		foreach($this->getRelations() as $relations)
		{
			list($class, $relationName, $relationClass) = $relations;
			$table = $this->getDBTableName($relationName, $class);
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

			$table = $this->getDBTableName($relationName);

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

			// relationship getters from DataObject
			$filter = array(
				'getManyManyComponents',
				'getComponents'
			);

			foreach(SS_Backtrace::filtered_backtrace() as $bt)
			{
				if(in_array($bt['function'],$filter) && $bt['class'] == 'DataObject')
				{
					foreach($this->getRelations($bt['object']->class) as $relations)
					{
						list($class, $relationName, $relationClass) = $relations;
						$table = $this->getDBTableName($relationName,$class);
						$query->leftJoin($table,"\"$table\".\"sw{$class}ID\" = '{$bt['object']->ID}' AND \"$table\".\"sw{$relationClass}ID\" = \"{$relationClass}\".\"ID\"");
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
		foreach(SortWeightRegistry::$relations[$this->owner->class] as $relationName => $relationClass)
		{
			$table = $this->getDBTableName($relationName);
			DB::query("DELETE FROM $table WHERE \"sw{$this->owner->class}ID\" = {$this->owner->ID}");
		}
	}

	function SortWeight(){
		return $this->owner->class . ' ' . $this->owner->ID;
	}

	function getDBTableName($relationName, $class = null)
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