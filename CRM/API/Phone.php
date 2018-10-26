<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_Phone extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('contact_id', 'phone', 'phone_ext')
		));
		static::addParentRelationship('Contact', 'Phone', 'Phones', 'CRM_API_Contact', 'contact_id');
	}
}

CRM_API_Phone::init();

?>
