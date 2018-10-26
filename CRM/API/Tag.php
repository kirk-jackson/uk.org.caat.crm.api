<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_Tag extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'lookups' => array('name'),
			'defaultStringLookup' => 'name',
			'displayFields' => array('name')
		));
	}
}

CRM_API_Tag::init();

?>
