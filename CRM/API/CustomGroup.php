<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_CustomGroup extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'lookups' => array('name', 'extends,title'),
			'defaultStringLookup' => 'name',
			'displayFields' => array('title', 'extends'),
			'fieldsByType' => array(
				'array' => array('extends_entity_column_value')
			),
			'autoloadChildren' => TRUE
		));
	}
}

CRM_API_CustomGroup::init();

require_once 'CRM/API/CustomField.php';

?>
