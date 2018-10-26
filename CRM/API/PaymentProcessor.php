<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_PaymentProcessor extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('name', 'is_test')
		));
	}
}

CRM_API_PaymentProcessor::init();

?>
