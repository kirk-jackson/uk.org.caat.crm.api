<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_OptionValue extends CRM_API_Entity {
	protected static $properties;
	
	public static function create($params, $cache = NULL) {
		// If creating a new OptionValue without supplying name, then derive name from label.
		if ((!array_key_exists('name', $params) || $params['name'] === '') && array_key_exists('label', $params)) {
			$name = $params['label'];
			$name = preg_replace('/\b\s+/u', '_', $name);
			$name = preg_replace('/[^\w]+/u', '_', $name);
			$params['name'] = $name;
		}
		
		return parent::create($params, $cache);
	}
	
	// Convert a date into a positive integer weight for sorting values in (reverse) date order.
	public static function weightFromDate($date, $reverse = FALSE) {
		if (!is_a($date, 'DateTime')) {
			if (is_int($date) || is_string($date) && ctype_digit($date))
				$date .= '-01'; // If the date is just a year then add a month.
			$date = new DateTime($date);
		}
		$dateDiff = $reverse ? $date->diff(new DateTime('2038-01-19')) : date_create('1901-12-14')->diff($date);
		return $dateDiff->format('%a');
	}
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('label', 'value')
		));
		static::addParentRelationship('Group', 'Value', 'Values', 'CRM_API_OptionGroup', 'option_group_id', NULL, 'name', array('label', 'value'));
	}
}

CRM_API_OptionValue::init();

?>
