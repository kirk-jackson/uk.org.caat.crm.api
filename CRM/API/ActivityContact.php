<?php

class CRM_API_ActivityContact extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('contact_id', 'activity_id', 'record_type_id')
		));
	}
}

CRM_API_ActivityContact::init();

?>
