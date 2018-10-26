<?php

class CRM_API_Participant extends CRM_API_ExtendableEntity {
	protected static $properties;
	
	protected function isExtendedBy($customGroup) {
		if (!static::_isExtendedBy($customGroup)) return FALSE;
		if (!isset($customGroup->extends_entity_column_id)) return TRUE;
		
		$participantCustomDataTypeName = CRM_API_OptionGroup::getSingle('custom_data_type')->getValue('value', $customGroup->extends_entity_column_id)->name;
		switch ($participantCustomDataTypeName) {
			case 'ParticipantRole':
				return array_intersect($customGroup->extends_entity_column_value, $this->role_id);
			
			case 'ParticipantEventName':
				return in_array($this->event_id, $customGroup->extends_entity_column_value);
			
			case 'ParticipantEventType':
				return in_array(CRM_API_Event::getSingle($this->event_id)->event_type_id, $customGroup->extends_entity_column_value);
			
			default:
				throw new Exception(E::ts('Unexpected participant custom data type "%1"', array(1 => $participantCustomDataTypeName)));
		}
	}
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('contact_id', 'event_id'),
			'fieldsByType' => array(
				'string' => array('note', 'discount_name'),
				'array' => array('role_id')
			)
		));
		static::addParentRelationship('Contact', 'Participation', 'Participations', 'CRM_API_Contact', 'contact_id');
	}
}

CRM_API_Participant::init();

?>
