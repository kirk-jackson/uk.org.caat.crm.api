<?php

class CRM_API_ContributionSoft extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('contact_id', 'contribution_id', 'currency', 'amount')
		));
		static::addParentRelationship('Contact', 'SoftCredit', 'SoftCredits', 'CRM_API_Contact', 'contact_id');
		static::addParentRelationship('Contribution', 'SoftCredit', 'SoftCredits', 'CRM_API_Contribution', 'contribution_id');
	}
}

CRM_API_ContributionSoft::init();

?>
