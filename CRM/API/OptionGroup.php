<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_OptionGroup extends CRM_API_Entity {
	protected static $properties;
	
	// Sort option values in reverse date order using the last ISO 8601 date in each option value's label.
	public function sortValuesByLabelDate() {
		$optionValues = array_values($this->getValues()); // Option value array should already be sorted in weight order.
		$optionValueDates = array();
		$noDateValuesTop = array();
		$noDateValuesBottom = array();
		$medianPosition = (count($optionValues) - 1) / 2;
		foreach ($optionValues as $position => $optionValue) {
			if (preg_match_all('/\d+-\d{2}-\d{2}/u', $optionValue->label, $matches)) {
				// Label contains at least one date, so record the last date in the label to use for sorting.
				$optionValueDates[$position] = new DateTime(end($matches[0]));
			} else {
				unset($optionValues[$position]);
				// Option values whose labels don't contain a date get moved to the nearest end of list.
				if ($position < $medianPosition)
					$noDateValuesTop[] = $optionValue;
				else
					$noDateValuesBottom[] = $optionValue;
			}
		}
		
		arsort($optionValueDates); // Calculate the sort order for dated option values.
		
		// Merge the option values back into a single list.
		$optionValues = array_merge($noDateValuesTop, array_replace($optionValueDates, $optionValues), $noDateValuesBottom);
		
		// Update option value weights to effect the new order.
		foreach ($optionValues as $position => $optionValue) {
			if ($optionValue->weight !== $position + 1)
				$optionValue->update(array('weight' => $position + 1));
		}
	}
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'lookups' => array('name'),
			'defaultStringLookup' => 'name',
			'displayFields' => array('title'),
			'paramsRequiredByCreate' => array('title'),
			'autoloadChildren' => TRUE
		));
	}
}

CRM_API_OptionGroup::init();

require_once 'CRM/API/OptionValue.php';

?>
