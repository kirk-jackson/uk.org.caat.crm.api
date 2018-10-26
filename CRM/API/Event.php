<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_Event extends CRM_API_ExtendableEntity {
	protected static $properties;
	
	protected function isExtendedBy($customGroup) {
		return static::_isExtendedBy($customGroup) && (
			!isset($customGroup->extends_entity_column_value) ||
			in_array($this->event_type_id, $customGroup->extends_entity_column_value)
		);
	}
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'lookups' => array('title'),
			'defaultStringLookup' => 'title',
			'displayFields' => array('title', 'start_date')
		));
	}
}

CRM_API_Event::init();

?>
