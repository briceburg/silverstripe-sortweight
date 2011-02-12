<?php
class SortWeightDecoration extends DataObjectDecorator {

	static $new_tables = array();

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

		if(!isset(SortWeightRegistry::$relations[$this->owner->class]))
		{
			return;
		}

		foreach(SortWeightRegistry::$relations[$this->owner->class] as $relationName => $relationClass)
		{
			$table = $this->getDBTableName($relationName);
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

			if(!DB::getConn()->hasTable($table))
			{
				DB::requireTable($table, $db, $indexes);
				if(!isset(self::$new_tables[$this->owner->class]))
				{
					self::$new_tables[$this->owner->class] = array();
				}
				self::$new_tables[$this->owner->class][] = $relationName;
			}

		}
	}

	function requireDefaultRecords(){
		if(isset(self::$new_tables[$this->owner->class]))
		{

			foreach(self::$new_tables[$this->owner->class] as $relationName)
			{
				$table = $this->getDBTableName($relationName);

				$do = singleton($this->owner->class);
				$components = $do->$relationName();

				if(!$components->is_a('DataObjectSet'))
				{
					$components = new DataObjectSet($components);
				}

				foreach($components as $component)
				{
					DB::query("INSERT INTO $table (\"Weight\",\"{$do->class}ID\",\"{$relationClass}ID\") SELECT count(*)+1, {$do->ID}, {$component->ID}  FROM \"$table\" WHERE \"{$relationClass}ID\" = {$component->ID}");
				}
			}

			unset(self::$new_tables[$this->owner->class]);
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
						$table = $this->getDBTableName($relationName);
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

	public function onBeforeDelete() {
		parent::onBeforeDelete();
		foreach(SortWeightRegistry::$relations[$this->owner->class] as $relationName => $relationClass)
		{
			$table = $this->getDBTableName($relationName);
			DB::query("DELETE FROM $table WHERE \"{$this->owner->class}ID\" = {$this->owner->ID}");
		}
	}

	function SortWeight(){
		return $this->owner->class . ' ' . $this->owner->ID;
	}

	function getDBTableName($relationName)
	{
		return $this->owner->class . '_SortWeight' . $relationName;
	}

}