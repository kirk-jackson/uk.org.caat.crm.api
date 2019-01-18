<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_ContributionRecur extends CRM_API_ExtendableEntity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('contact_id', 'amount', 'start_date', 'frequency_interval', 'frequency_unit')
		));
		static::addParentRelationship('Contact', 'RecurringContribution', 'RecurringContributions', 'CRM_API_Contact', 'contact_id');
	}
}

CRM_API_ContributionRecur::init();

?>
