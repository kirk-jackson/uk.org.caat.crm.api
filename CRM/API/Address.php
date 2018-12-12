<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_Address extends CRM_API_ExtendableEntity {
	protected static $properties;
	
	public function update($params, $always = TRUE) {
		// A bug in CiviCRM causes a fatal error if master_id is supplied but contact_id is not.
		if (!empty($params['master_id']) && !array_key_exists('contact_id', $params) && isset($this->contact_id)) {
			$params['contact_id'] = $this->contact_id;
		}
		
		parent::update($params, $always);
	}
	
	public static function create($params, $cache = NULL) {
		// If this address is shared with another contact, then copy address fields from the master address.
		if (!empty($params['master_id'])) {
			$fieldsNotInheritedFromMaster = array_fill_keys(['location_type_id', 'is_primary', 'is_billing'], NULL);
			$masterAddress = CRM_API_Address::getSingle((int)$params['master_id'], TRUE, $cache);
			foreach (array_diff_key($masterAddress->fields, $params, $fieldsNotInheritedFromMaster) as $masterField => $masterValue) {
				if (!is_null($masterValue) && trim($masterValue) !== '')
					$params[$masterField] = $masterValue;
			}
		}
		
		return parent::create($params, $cache);
	}
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('contact_id', 'street_address', 'city', 'postal_code', 'country_id'),
			'fieldsMayNotMatchApiParams' => array('postal_code', 'geo_code_1', 'geo_code_2')
		));
		unset(static::$properties->persistFields['master_id']);
		static::addParentRelationship('Contact', 'Address', 'Addresses', 'CRM_API_Contact', 'contact_id');
	}
}

CRM_API_Address::init();

?>
