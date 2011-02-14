<?php
class SortWeightRegistry {

	public static $override_default_sort = true;
	public static $relations = array();
	public static $default_sorts = array();				// original default_sort
	public static $add_weight_columns = array();
	public static $direction = 'ASC';					// ASC || DESC
	public static $module_path;

	public static function set_module_path($directory)
	{
		self::$module_path = $directory;
	}

	public static function decorate($class, $relationName = null) {

		if(!isset(self::$relations[$class]))
		{
			self::$relations[$class] = array();
		}

		// if relationName is false, enable the sorting on object iteslf (skip SortWeight map)
		if(!class_exists($class) || !$sng = new $class())
		{
			user_error('Unknown class passed (' . $class .')', E_USER_WARNING);
		}
		elseif($relationName === null )
		{
			user_error('You must provide the Component to order for ' . $class, E_USER_WARNING);
		}
		elseif(!$sng->hasMethod($relationName) || !$component = $sng->$relationName())
		{
			user_error('Component "' . $relationName . '" must exist on ' . $class,E_USER_WARNING);
		}
		elseif(isset(self::$relations[$class][$relationName]))
		{
			user_error('Component "' . $relationName . '" already decorates ' . $class,E_USER_WARNING);
		}
		else
		{
			$relationClass = ($component->is_a('ComponentSet')) ?
				$component->childClass : $component->class;

			self::$relations[$class][$relationName] = $relationClass;

			$current_sort = Object::get_static($relationClass, 'default_sort');
			if(self::$override_default_sort || empty($current_sort))
			{
				Object::set_static($relationClass,'default_sort','[SortWeight]');
				if($current_sort != '[SortWeight]')
				{
					self::$default_sorts[$relationClass] = $current_sort;
				}
			}

			if(!Object::has_extension($relationClass,'SortWeightDecoration'))
			{
				Object::add_extension($relationClass,'SortWeightDecoration');
			}

			return;
		}

		return user_error('SortWeight decoration failed for ' . __CLASS__ . '::' . __FUNCTION__ . "(\"$class\",\"$relationName\")",E_USER_WARNING);

	}
}