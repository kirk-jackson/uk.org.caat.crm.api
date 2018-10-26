<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_StateProvince extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('country_id', 'name')
		));
		static::addParentRelationship('Country', 'StateProvince', 'StateProvinces', 'CRM_API_Country', 'country_id', NULL, 'name', ['abbreviation']);
	}
}

CRM_API_StateProvince::init();

?>
