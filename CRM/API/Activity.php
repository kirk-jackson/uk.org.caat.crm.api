<?php

class CRM_API_Activity extends CRM_API_TaggableExtendableEntity {
	protected static $properties;
	
	protected function isExtendedBy($customGroup) {
		return static::_isExtendedBy($customGroup) && (
			!isset($customGroup->extends_entity_column_value) ||
			in_array($this->activity_type_id, $customGroup->extends_entity_column_value)
		);
	}
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('activity_type_id', 'subject', 'activity_date_time'),
			'fieldsByType' => array(
				'int' => array('source_contact_id', 'target_contact_id')
			),
			'fieldsMayNotMatchApiParams' => array('subject'),
			'readOnlyFields' => array('created_date', 'modified_date')
		));
	}
}

CRM_API_Activity::init();

?>
