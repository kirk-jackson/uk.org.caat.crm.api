<?php

class CRM_API_Country extends CRM_API_Entity {
	protected static $properties;
	
	public static function getDefault() {
		return static::getSingle((int)CRM_Core_Config::singleton()->defaultContactCountry);
	}
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'lookups' => array('name', 'iso_code'),
			'defaultStringLookup' => 'name',
			'displayFields' => array('name', 'iso_code')
		));
	}
}

CRM_API_Country::init();

require_once 'CRM/API/StateProvince.php';

?>
