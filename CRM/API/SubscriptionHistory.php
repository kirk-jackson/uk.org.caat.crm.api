<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_SubscriptionHistory extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('contact_id', 'status', 'group_id', 'date')
		));
	}
}

CRM_API_SubscriptionHistory::init();

?>
