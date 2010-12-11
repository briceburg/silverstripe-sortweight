<?php
class SortWeightRegistry {

	public static $override_default_sort = true;
	public static $relations = array();
	public static $add_weight_columns = array();
	public static $direction = 'ASC';		// ASC || DESC
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
		if($relationName === null)
		{
			self::$add_weight_columns[$class] = true;
		}
		else
		{
			// TODO: rensure relationName is a valid method on class
			if(!$component = singleton($class)->$relationName())
			{
				user_error('Component ' . $relationName . ' must exist on ' . $class,E_USER_WARNING);
			}
			elseif(isset(self::$relations[$class][$relationName]))
			{
				user_error('Component ' . $relationName . ' already decorates ' . $class,E_USER_WARNING);
			}


			$relationClass = ($component->is_a('ComponentSet')) ?
				$component->childClass : $component->class;

			self::$relations[$class][$relationName] = $relationClass;
		}

		$current_sort = Object::get_static($class, 'default_sort');
		if(self::$override_default_sort || empty($current_sort))
		{
			// TODO: this doesn't seem to register
			Object::add_static_var($class,'default_sort','SortWeight ' . self::$direction);
		}

		Object::add_extension($class,'SortWeightDecoration');

	}


}


