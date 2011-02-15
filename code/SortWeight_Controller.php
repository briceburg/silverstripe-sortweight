<?php

class SortWeight_Controller extends Controller {

	public static $url_handlers = array(
		'$REL/ToTop'				=> 'ToTop',
		'$REL/ToBottom'				=> 'ToBottom',
		'$REL/NextTo/$SiblingREL'	=> 'NextTo'
    );

    public function init(){
    	parent::init();

    	if(!Permission::checkMember(Member::currentUser(), "CMS_ACCESS_LeftAndMain"))
    		die('permission denied');

    }

    public function ToTop(){
    	$this->updateWeight();
    }

    public function ToBottom(){
    	$oldDirection = strtolower(SortWeightRegistry::$direction);
		SortWeightRegistry::$direction = ($oldDirection == 'desc') ?
			'asc' : 'desc';

		$this->updateWeight();
		SortWeightRegistry::$direction = $oldDirection;
    }

 	public function NextTo(){
		$this->updateWeight(true);
    }

	private function updateWeight($sibling = false)
	{
		$direction = SortWeightRegistry::$direction;

		list($class, $classID, $relationName, $relationClass, $relationID) = explode(':',$this->request->param('REL'));
		if($sibling)
		{
			list($class, $classID, $relationName, $relationClass, $siblingID) = explode(':',$this->request->param('SiblingREL'));
		}

		$obj = singleton($relationClass);
		$table = $obj->getSWTableName($relationName,$class);

		$currentWeight = DB::query("SELECT SortWeight FROM \"{$table}\" WHERE \"sw{$relationClass}ID\" = {$relationID} AND \"sw{$class}ID\" = {$classID}")->value();


		$targetWeight = ($sibling) ?
			DB::query("SELECT SortWeight FROM \"{$table}\" WHERE \"sw{$class}ID\" = {$classID} AND \"sw{$relationClass}ID\" = {$siblingID}")->value() :
			DB::query("SELECT SortWeight FROM \"{$table}\" WHERE \"sw{$class}ID\" = {$classID} ORDER BY SortWeight $direction LIMIT 1")->value();

		if($targetWeight && $targetWeight < $currentWeight)
		{
			DB::query("UPDATE \"{$table}\" SET \"SortWeight\"=\"SortWeight\"+1 WHERE \"sw{$class}ID\" = {$classID} AND \"SortWeight\" >= $targetWeight AND \"SortWeight\" < $currentWeight");
		}
		elseif($targetWeight && $targetWeight > $currentWeight)
		{
			DB::query("UPDATE \"{$table}\" SET \"SortWeight\"=\"SortWeight\"-1 WHERE \"sw{$class}ID\" = {$classID} AND \"SortWeight\" > $currentWeight AND \"SortWeight\" <= $targetWeight");
		}
		else
		{
			return false;
		}

		DB::query("UPDATE \"{$table}\" SET SortWeight=$targetWeight WHERE \"sw{$class}ID\" = {$classID} AND \"sw{$relationClass}ID\" = {$relationID} ");
	}


}