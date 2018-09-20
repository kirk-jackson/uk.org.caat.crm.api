<?php

class CRM_API_CustomField extends CRM_API_Entity {
	protected static $properties;
	protected static $lookupByName = array();
	
	public function __get($field) {
		try {
			return parent::__get($field);
		} catch (Exception $exception) {
			if ($field === 'apiKey') return 'custom_' . $this->id;
			throw $exception;
		}
	}
	
	public function getOptionGroup() {
		if (!$this->isMultipleChoice())
			throw new Exception(ts('%1 is not a multiple-choice field so it doesn\'t have an option group', array(1 => $this)));
		return CRM_API_OptionGroup::getSingle($this->option_group_id);
	}
	
	public function isMultipleChoice() {
		return isset($this->option_group_id);
	}
	
	public function isMultiSelect() {
		return mb_strpos($this->html_type, 'Multi-Select') !== FALSE;
	}
	
	// Convert a value (possibly multi-value) into a useful standard representation for this custom field.
	public function normaliseValue($value, $getObjects = FALSE) {
		if (is_null($value)) return NULL;
		
		if ($this->isMultiSelect()) {
			if ($value === '') return NULL;
			foreach ($value as &$aValue)
				$aValue = $this->normaliseAtomicValue($aValue, $getObjects);
		} else {
			$value = $this->normaliseAtomicValue($value, $getObjects);
		}
		
		return $value;
	}
	
	// Convert an individual value to a useful standard representation for this custom field.
	protected function normaliseAtomicValue($value, $getObjects, $fromOptionValue = FALSE) {
		switch ($this->data_type) {
			case 'String':
				if (is_string($value))
					$primitiveValue = $value;
				elseif (is_int($value) || is_float($value))
					$primitiveValue = (string)$value;
				break;
			
			case 'Int':
				if ($value === '')
					return NULL;
				elseif (is_int($value))
					$primitiveValue = $value;
				elseif (CRM_API_Utils::isSignedIntString($value))
					$primitiveValue = (int)$value;
				break;
			
			case 'Float':
			case 'Money':
				if ($value === '')
					return NULL;
				elseif (is_float($value))
					$primitiveValue = $value;
				elseif (is_numeric($value))
					$primitiveValue = (float)$value;
				break;
			
			case 'Boolean':
				if (is_bool($value)) return $value;
				if ($value === '') return NULL;
				if ($value === 0 || $value === '0')	return FALSE;
				if ($value === 1 || $value === '1') return TRUE;
				break;
			
			case 'Date':
				if (is_a($value, 'DateTime')) return $value;
				if ($value === '') return NULL;
				if (is_string($value)) return new DateTime($value);
				break;
			
			case 'Memo':
			case 'Link':
				if (is_string($value)) return $value;
				break;
			
			case 'StateProvince':
			case 'Country':
			case 'File':
			case 'ContactReference':
				$class = 'CRM_API_' . $this->data_type;
				if ($this->data_type === 'ContactReference') $class = 'CRM_API_Contact';
				
				if (is_int($value))
					return $getObjects ? $class::getSingle($value) : $value;
				
				if ($value === '') return NULL;
				
				// Allow custom value to be specified using an entity object.
				if (is_a($value, $class))
					return $getObjects ? $value : $value->id;
				
				// Allow countries to be specified by name.
				if ($this->data_type === 'Country' && is_string($value)) {
					$country = CRM_API_Country::getSingle($value);
					return $getObjects ? $country : $country->id;
				}
				
				if (is_string($value) && ctype_digit($value))
					return $getObjects ? $class::getSingle((int)$value) : (int)$value;
				
				break;
		}
		
		// If the field is multiple choice and objects are requested, return the option value object; otherwise return the primitive value.
		if (isset($primitiveValue)) {
			if ($this->isMultipleChoice() && $getObjects) {
				if ($primitiveValue === '') return NULL;
				return $this->getOptionGroup()->getValue('value', $primitiveValue);
			}
			return $primitiveValue;
		}
		
		// Allow custom value to be specified using an option value object or name.
		if (!$fromOptionValue && $this->isMultipleChoice()) {
			if (is_a($value, 'CRM_API_OptionValue') && $value->option_group_id === $this->option_group_id)
				$optionValue = $value;
			elseif (is_string($value))
				$optionValue = $this->getOptionGroup()->getValue($value, FALSE);
			
			if (isset($optionValue))
				return $this->normaliseAtomicValue($optionValue->value, $getObjects, TRUE);
		}
		
		throw new Exception(ts('Invalid %1 custom value %2', array(1 => $this->data_type, 2 => CRM_API_Utils::toString($value))));
	}
	
	public static function getByName($name) {
		if (!array_key_exists($name, static::$lookupByName)) return array();
		return static::$lookupByName[$name];
	}
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('label', 'custom_group_id', 'html_type', 'data_type')
		));
		static::addParentRelationship('Group', 'Field', 'Fields', 'CRM_API_CustomGroup', 'custom_group_id', NULL, 'name', array('label'));
	}
	
	public static function init() {
		parent::init();
		
		// Cache all custom fields by name.  (There may be more than one custom field for each name.)
		foreach (CRM_API_CustomField::get() as $customField) {
			if (!array_key_exists($customField->name, static::$lookupByName))
				static::$lookupByName[$customField->name] = array();
			static::$lookupByName[$customField->name][] = $customField;
		}
	}
}

CRM_API_CustomField::init();

?>
