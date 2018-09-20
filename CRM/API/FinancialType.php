<?php

class CRM_API_FinancialType extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'lookups' => array('name'),
			'defaultStringLookup' => 'name',
			'displayFields' => array('name')
		));
	}
	
	public static function init() {
		parent::init();
		static::cacheAll();
	}
}

CRM_API_FinancialType::init();

?>
