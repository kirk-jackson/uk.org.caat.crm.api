<?php

class CRM_API_Log extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('entity_table', 'entity_id', 'data', 'modified_date')
		));
	}
}

CRM_API_Log::init();

?>
