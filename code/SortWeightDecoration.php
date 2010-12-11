<?php
class SortWeightDecoration extends DataObjectDecorator {
	public function extraStatics($class = null)
	{
		if(!$class)
		{
			$class = $this->owner->class;
		}

		$statics = array();

		if(isset(SortWeightRegistry::$add_weight_columns[$class]))
		{
			$statics['db'] = array('SortWeight' => 'Int');
		}

		return $statics;
	}

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

		$set = false;

		foreach(SortWeightRegistry::$relations[$this->owner->class] as $relationName => $relationClass)
		{
			$table = $this->owner->class . '_SortWeight' . $relationName;
			$db = array(
				"Weight"					=>	'Int',
				"{$this->owner->class}ID"	=>	'Int',
				"{$relationClass}ID"		=>	'Int'
			);

			$indexes = array(
				"{$this->owner->class}ID"	=>	true,
				"{$relationClass}ID"		=>	true,
				"class-relation"			=>	array('type' => 'unique', 'value' => "{$this->owner->class}ID,{$relationClass}ID")
			);

			if(DB::getConn()->hasTable($table))
			{
				DB::requireTable($table, $db, $indexes);
			}
			else
			{
				// TODO: there's a bug w/ index creation... it works w/ requiretable, but not createtable?? for now,
				//   workarond by calling /dev/build twice (first to create table, 2nd to introduce indexes)....
				DB::createTable($table, $db);

				if(!$set && !$set = DataObject::get($this->owner->class))
				{
					continue;
				}

				foreach($set as $obj)
				{
					$this->initialPopulation($obj,true);
				}
			}

		}

	}

	function augmentSQL(&$query) {
		//TODO: do not augment when getting a specific record... (e.g. why add sortorder?)
		//		be sure to include cases for WHERE ClassID IN (...,...,...)
		if(empty($query->select) || $query->delete)
			return;

		if(empty($query->orderby) || $query->orderby == 'SortWeight')
		{
			// detect the relationship -- TODO: detection is very fragile! any way to properly hint this?
			//     prefer core patch to DataObject & SQLQuery: SQLQuery to holde reference of dataobject ($query->record?)			//

			//   scheme examines the query filter, trying to find a relation match in the registry.
			//   accounts for has_many & many_many relations
			foreach($query->where as $sql)
			{
				// parses strings like:
				//  [has_many] '"QuestionID" = '1''  ==>  array('Question','1')
				//  [many_many] '"WCCQuestion_Choices"."WCCQuestionID" = 1'  ==>  array('WCCQuestion','1')
				//  [multi-where] "QuestionID" = '1' AND "Correct" = 1 AND "Place" = 1

				preg_match_all('/(.*?)( AND|and|OR|or )/',$sql,$wheres);
				if(empty($wheres[1]))
				{
					$wheres[1][] = $sql;
				}

				foreach($wheres[1] as $sql)
				{
					$parts = explode('=', $sql);
					if(!isset($parts[1]) || !$pos = strrpos($parts[0],'ID'))
					{
						continue;
					}

	 				$parts[0] = substr(substr($parts[0],0,$pos),(int) strrpos($parts[0],'.') + 1);

					$joinClass = trim($parts[0],'"\' ');
					$value = trim($parts[1],'"\' ');

					$relationName = (isset(SortWeightRegistry::$relations[$this->owner->class][$joinClass])) ?
						$joinClass : array_search($joinClass,SortWeightRegistry::$relations[$this->owner->class]);

					if($relationName)
					{
						$table = $this->owner->class . '_SortWeight' . $relationName;
						$relationClass = SortWeightRegistry::$relations[$this->owner->class][$relationName];

						$query->leftJoin($table,"\"$table\".\"{$relationClass}ID\" = '{$value}' AND \"$table\".\"{$this->owner->class}ID\" = \"{$this->owner->class}\".\"ID\"");
						$query->orderby("\"$table\".\"Weight\" " . SortWeightRegistry::$direction);
						return;
					}
				}
			}

			// no relation found -- attempt to use SortWeight
			if(empty($query->orderby) && isset($query->select['SortWeight']))
			{
				$query->orderby("\"{$this->owner->class}\".\"SortWeight\" " . SortWeightRegistry::$direction);
			}

		}
	}


	// purpose is to create initial sort weight values when object is saved.
	// TODO: perhaps we can skip this & rely solely on ctf w/ default sort values.. (can get weighty on memory / db -- esp. w/ large amounts of relations)
	public function onAfterWrite(){
		parent::onAfterWrite();
		$this->initialPopulation($this->owner);
	}


	public function onBeforeDelete() {
		parent::onBeforeDelete();
		foreach(SortWeightRegistry::$relations[$this->owner->class] as $relationName => $relationClass)
		{
			$table = $this->owner->class . '_SortWeight' . $relationName;
			DB::query("DELETE FROM $table WHERE \"{$this->owner->class}ID\" = {$this->owner->ID}");
		}
	}

	function SortWeight(){
		return $this->owner->class . ' ' . $this->owner->ID;
	}

/*	function getRelationSortWeights($a = null, $b = null, $c = null)
	{
		$weights = new ViewableData();
		// TODO: optimize this -- we probably don't even need to do the select, but only do it on weight changes
		foreach(SortWeightRegistry::$relations[$this->owner->class] as $relationName => $relationClass) {
			//$weights->$relationName = $this->getRelationSortWeight($relationName);
		}
s
		return $weights;
	}*/

	private function initialPopulation(DataObject $do, $skipCheck = false)
	{
		// TODO: for efficiency; use an INSERT SELECT here?
		foreach(SortWeightRegistry::$relations[$do->class] as $relationName => $relationClass)
		{
			$components = $do->$relationName();

			if(!$components->is_a('DataObjectSet'))
			{
				$components = new DataObjectSet($components);
			}

			foreach($components as $component)
			{
				$table = $do->class . '_SortWeight' . $relationName;
				// TODO: could use a replace into -- although only tested on MySQL -- compat w/ pg && sql srvr?
				if($skipCheck || !(bool) DB::query("SELECT count(*) FROM \"$table\" WHERE \"{$do->class}ID\" = {$do->ID} AND \"{$relationClass}ID\" = {$component->ID}")->value())
				{
					DB::query("INSERT INTO $table (\"Weight\",\"{$do->class}ID\",\"{$relationClass}ID\") SELECT count(*)+1, {$do->ID}, {$component->ID}  FROM \"$table\" WHERE \"{$relationClass}ID\" = {$component->ID}");
				}
			}
		}
	}
}