<?php

class CRM_API_Contribution extends CRM_API_ExtendableEntity {
	protected static $properties;
	
	protected function isExtendedBy($customGroup) {
		return static::_isExtendedBy($customGroup) && (
			!isset($customGroup->extends_entity_column_value) ||
			in_array($this->financial_type_id, $customGroup->extends_entity_column_value)
		);
	}
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'lookups' => array('trxn_id', 'invoice_id'),
			'displayFields' => array('contact_id', 'currency', 'total_amount', 'contact_id', 'financial_type_id', 'receive_date'),
			'fieldsByType' => array(
				'int' => array('soft_credit_to', 'soft_credit_id'),
				'array' => array('soft_credit')
			)
		));
		static::addParentRelationship('Contact', 'Contribution', 'Contributions', 'CRM_API_Contact', 'contact_id');
	}
}

CRM_API_Contribution::init();

require_once 'CRM/API/ContributionSoft.php';

?>
