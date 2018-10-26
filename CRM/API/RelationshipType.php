<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_RelationshipType extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'lookups' => array('name_a_b', 'name_b_a'),
			'defaultStringLookup' => 'name_a_b',
			'displayFields' => array('label_a_b')
		));
	}
	
	public static function init() {
		parent::init();
		static::cacheAll();
	}
}

CRM_API_RelationshipType::init();

?>
