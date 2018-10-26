<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_Address extends CRM_API_ExtendableEntity {
	protected static $properties;
	
	public static function create($params, $cache = NULL) {
		static $fieldsNotInheritedFromMaster;
		if (is_null($fieldsNotInheritedFromMaster))
			$fieldsNotInheritedFromMaster = array_fill_keys(array('location_type_id', 'is_primary', 'is_billing'), NULL);
		
		// If this address is shared with another contact, then copy address fields from the master address.
		if (!empty($params['master_id'])) {
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
		static::addParentRelationship('Contact', 'Address', 'Addresses', 'CRM_API_Contact', 'contact_id');
	}
}

CRM_API_Address::init();

?>
