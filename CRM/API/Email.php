<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_Email extends CRM_API_Entity {
	protected static $properties;
	
	public function update($params, $always = TRUE) {
		// Updating on_hold automatically sets hold_date or reset_date so in order to update both, on_hold must be updated first and then the date.
		if (array_key_exists('on_hold', $params) && (!isset($this->on_hold) || $this->on_hold !== $params['on_hold'])) {
			$dateField = $params['on_hold'] === 0 ? 'reset_date' : 'hold_date';
			if (array_key_exists($dateField, $params)) {
				parent::update(array_diff_key($params, array($dateField => NULL))); // Update all fields except the date.
				parent::update(array($dateField => $params[$dateField])); // Update the date.
				return;
			}
		}
		
		parent::update($params, $always);
	}
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('contact_id', 'email'),
			'fieldsByType' => array(
				'int' => array('on_hold')
			),
			'fieldsMayNotMatchApiParams' => array('hold_date', 'reset_date')
		));
		static::addParentRelationship('Contact', 'Email', 'Emails', 'CRM_API_Contact', 'contact_id');
	}
}

CRM_API_Email::init();

?>
