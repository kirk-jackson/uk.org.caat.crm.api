<?php

class CRM_API_Relationship extends CRM_API_ExtendableEntity {
	protected static $properties;
	
	public function getType() {
		return CRM_API_RelationshipType::getSingle($this->relationship_type_id);
	}
	
	protected function isExtendedBy($customGroup) {
		return static::_isExtendedBy($customGroup) && (
			!isset($customGroup->extends_entity_column_value) ||
			in_array($this->relationship_type_id, $customGroup->extends_entity_column_value)
		);
	}
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('contact_id_a', 'contact_id_b', 'relationship_type_id'),
			'createReturnsFields' => FALSE
		));
	}
}

CRM_API_Relationship::init();

?>
