<?php

class SortWeight_Controller extends Controller {

	public static $url_handlers = array(
		'$REL/ToTop/$TableHREF'					=> 'ToTop',
		'$REL/ToBottom/$TableHREF'				=> 'ToBottom',
		'$REL/NextTo/$TableHREF/$SiblingREL'	=> 'NextTo'
    );

    private $relationTable;
    private $relationName;
    private $sortObject;
    private $relation;


    public function init(){
    	parent::init();

    	if(!Permission::checkMember(Member::currentUser(), "CMS_ACCESS_LeftAndMain"))
    		die('permission denied');

    }

    public function ToTop(){
    	$this->sortObject = $this->getSortObject();
    	$this->relation = $this->getRelation();

    	$this->updateWeight();
    }

    public function ToBottom(){
    	$this->sortObject = $this->getSortObject();
    	$this->relation = $this->getRelation();

    	$oldDirection = strtolower(SortWeightRegistry::$direction);
		SortWeightRegistry::$direction = ($oldDirection == 'desc') ?
			'asc' : 'desc';

		$this->updateWeight();
		SortWeightRegistry::$direction = $oldDirection;
    }

    /*
    public function NextTo(){
    	$this->sortObject = $this->getSortObject();
    	$this->relation = $this->getRelation();

    	$siblingObject = $this->getSortObject('SiblingREL');
		if($this->request->param('up') == 'below')
		{
			$oldDirection = strtolower(SortWeightRegistry::$direction);
			SortWeightRegistry::$direction = ($oldDirection == 'desc') ?
				'asc' : 'desc';
			$this->updateWeight($siblingObject);
			SortWeightRegistry::$direction = $oldDirection;
		}
		else
		{
			$this->updateWeight($siblingObject);
		}
    }
    */
 	public function NextTo(){
    	$this->sortObject = $this->getSortObject();
    	$this->relation = $this->getRelation();

    	$siblingObject = $this->getSortObject('SiblingREL');


		$this->updateWeight($siblingObject);
    }

    private function getSortObject($param = 'REL') {
    	list($class,$id) = explode(' ',$this->request->param($param));

		/**
		 * VALIDATION
		 */
		if(!isset(SortWeightRegistry::$relations[$class]))
			die('invalid class');
		elseif(!is_numeric($id))
			die('unable to extract id');
		elseif(!$sortObject = DataObject::get_by_id($class, $id))
			die('unable to fetch ' . $class . ' object');

		return $sortObject;
    }


    // TODO: this is extremely fragile & should be unit tested against CTF.
	//   Patching CTF to supply a hint would help --
	//    (e.g. use the data attribute of the table that contains the name of
	//    the parent class / controller to tablelistfield div) -- alternatively, hint
	//   class when calling updateSummaryFields method of SortWeightDecoration &
	//   include in sortweight column.
	private function getRelation(){

		//  expected: "admin|manage-wcc|WCCQuestion|1|EditForm|field|Choices"
		//    will match: WCCQuestion
		if(!preg_match('/\|([A-Za-z0-9-_ ]+)\|([0-9]+)\|EditForm/',$this->request->param('TableHREF'),$matches))
			die('failed relation lookup');

		$relation = $matches[1];
		$relationId = $matches[2];

		foreach(SortWeightRegistry::$relations[$this->sortObject->class] as $relationName => $relationClass)
		{
			if($relation == $relationClass)
			{
				$this->relationName = $relationName;
				$this->relationTable = $this->sortObject->class . '_SortWeight' . $relationName;
				return DataObject::get_by_id($relationClass, $relationId);
			}
		}
		return false;
	}

	private function getComponentMethod(){
		//  expected: "admin|manage-wcc|WCCQuestion|1|EditForm|field|Choices"
		//    will match: Choices
		if(!preg_match('/EditForm|field|([A-Za-z0-9-_]+)/',$xmlID,$matches))
			die('failed component lookup');

		return $matches[1];
	}



	private function updateWeight($sibling = false)
	{
		$direction = SortWeightRegistry::$direction;

    	if($this->relation)
		{
			$currentWeight = DB::query("SELECT Weight FROM \"{$this->relationTable}\" WHERE \"{$this->relation->class}ID\" = {$this->relation->ID} AND \"{$this->sortObject->class}ID\" = {$this->sortObject->ID}")->value();

			$targetWeight = ($sibling) ?
				DB::query("SELECT Weight FROM \"{$this->relationTable}\" WHERE \"{$this->relation->class}ID\" = {$this->relation->ID} AND \"{$this->sortObject->class}ID\" = {$sibling->ID}")->value() :
				DB::query("SELECT Weight FROM \"{$this->relationTable}\" WHERE \"{$this->relation->class}ID\" = {$this->relation->ID} ORDER BY Weight $direction LIMIT 1")->value();

			if($targetWeight && $targetWeight < $currentWeight)
			{
				DB::query("UPDATE \"{$this->relationTable}\" SET \"Weight\"=\"Weight\"+1 WHERE \"{$this->relation->class}ID\" = {$this->relation->ID} AND \"Weight\" >= $targetWeight AND \"Weight\" < $currentWeight");
			}
			elseif($targetWeight && $targetWeight > $currentWeight)
			{
				DB::query("UPDATE \"{$this->relationTable}\" SET \"Weight\"=\"Weight\"-1 WHERE \"{$this->relation->class}ID\" = {$this->relation->ID} AND \"Weight\" > $currentWeight AND \"Weight\" <= $targetWeight");
			}
			else
			{
				return false;
			}

			DB::query("UPDATE \"{$this->relationTable}\" SET Weight=$targetWeight WHERE \"{$this->relation->class}ID\" = {$this->relation->ID} AND \"{$this->sortObject->class}ID\" = {$this->sortObject->ID} ");
		}
		elseif(SortWeightRegistry::$add_weight_columns[$this->sortObject->class])
		{

			$currentWeight = $this->sortObject->SortWeight;

			$targetWeight = ($sibling) ?
				$sibling->SortWeight :
				DataObject::get_one($this->sortObject->class,null,true,"\SortWeight\" $direction")->SortWeight;


			if($targetWeight && $targetWeight < $currentWeight)
			{

				$set = DataObject::get($this->sortObject->class,"\"SortWeight\" >= $targetWeight AND \"SortWeight\" < $currentWeight");
				foreach($set as $do)
				{
					$do->SortWeight = $do->SortWeight + 1;
					$do->write();
				}
			}
			elseif($targetWeight && $targetWeight > $currentWeight)
			{
				$set = DataObject::get($this->sortObject->class,"\"SortWeight\" > $currentWeight AND \"SortWeight\" <= $targetWeight");
				foreach($set as $do)
				{
					$do->SortWeight = $do->SortWeight - 1;
					$do->write();
				}
			}
			else
			{
				return false;
			}

			$this->sortObject->SortWeight = $targetWeight;
			$this->sortObject->write();
		}

	}


}