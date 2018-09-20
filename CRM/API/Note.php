<?php

class CRM_API_Note extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('entity_table', 'entity_id', 'subject', 'note')
		));
		static::addParentRelationship('Contact', 'Note', 'Notes', 'CRM_API_Contact', 'entity_id', NULL, NULL, array(), 'entity_table');
	}
}

CRM_API_Note::init();

?>
