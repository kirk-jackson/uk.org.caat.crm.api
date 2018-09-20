<?php

abstract class CRM_API_ExtendableEntity extends CRM_API_Entity {
	protected $customFields;
	
	// Allow update method to update custom fields by name.
	public function update($params, $always = TRUE) {
		// TODO: Here's the problem: Unrecognised parameters must be checked to see if they map to valid custom fields.
		//       HOWEVER, changes to other parameters may affect whether a custom field name is valid.
		//       For example, changing a contact sub-type may change which custom fields are available.
		//       Temp fix: Do the update in two stages, with possible custom field names after known parameters.
		//       Proper fix: work out whether custom fields are valid based on parameters, not current entity state.
		$knownParams =
			array_intersect_key($params, static::$properties->validFields) +
			array_filter($params, function ($paramName) {return preg_match('/^custom_\d+$/u', $paramName);}, ARRAY_FILTER_USE_KEY);
		parent::update($knownParams, $always);
		$unknownParams = array_diff_key($params, $knownParams);
		if ($unknownParams)
			parent::update($this->mapCustomFieldNames($unknownParams), $always);
	}
	
	public function __get($field) {
		// TODO: Empty multiselect fields are being returned by CiviCRM's shitty API as NULL values. Needs fixing.
		try {
			return parent::__get($field);
		} catch (Exception $exception) {
			// Is it a custom field?
			$customField = $this->getMatchingCustomField($field, FALSE);
			if (!is_null($customField))
				return $this->getCustomValue($customField);
			
			throw $exception;
		}
	}
	
	public function __set($field, $value) {
		try {
			return parent::__set($field, $value);
		} catch (Exception $exception) {
			// Is it a custom field?
			$customField = $this->getMatchingCustomField($field, FALSE);
			if (!is_null($customField)) {
				$this->setCustomValue($customField, $value);
				return;
			}
			
			throw $exception;
		}
	}
	
	public function __isset($field) {
		if (parent::__isset($field)) return TRUE;
		
		// Is it a custom field?
		$customField = $this->getMatchingCustomField($field, FALSE);
		return !is_null($customField) && !is_null($this->getCustomValue($customField));
	}
	
	public function __call($functionName, $args) {
		try {
			return parent::__call($functionName, $args);
		} catch (Exception $exception) {
			if (preg_match('/^(add|update)(.+)$/u', $functionName, $matches)) {
				$customField = $this->getMatchingCustomField($matches[2], FALSE);
				if (!is_null($customField)) {
					switch ($matches[1]) {
						case 'add':
							$this->addCustomValues($customField, $args[0]);
							return;
						
						case 'update':
							$this->updateCustomValues($customField, $args[0]);
							return;
					}
				}
			}
			
			throw $exception;
		}
	}
	
	public function getCustomValue($customField) {
		// If the value is already stored, return it.
		if (array_key_exists($customField->id, $this->customFields))
			return $this->customFields[$customField->id];
		
		$value = static::callCustomValueApiGet($this->id, $customField);
		
		// Store the value.
		$this->customFields[$customField->id] = $value;
		
		return $value;
	}
	
	public function setCustomValue($customField, $value) {
		static::callCustomValueApiSet($this->id, $customField, $value);
		
		// Store the value.
		$this->customFields[$customField->id] = $customField->normaliseValue($value);
	}
	
	public function addCustomValues($customField, $values) {
		$customField = $this->getCustomField($customField);
		
		static::callCustomValueApiMultiAdd($this->id, $customField, $values);
		
		// IDs of added values are unknown so the custom field must be unset.
		unset($this->customFields[$customField->id]);
	}
	
	public function updateCustomValues($customField, $values) {
		$customField = $this->getCustomField($customField);
		
		static::callCustomValueApiMultiUpdate($this->id, $customField, $values);
		
		// Update the stored values.
		if (isset($this->customFields[$customField->id]))
			foreach ($values as $recordId => $value)
				$this->customFields[$customField->id][$recordId] = $customField->normaliseValue($value);
	}
	
	// Allow create method to set custom fields by name.
	public static function create($params, $cache = NULL) {
		return parent::create(static::_mapCustomFieldNames($params), $cache);
	}
	
	// Allow get method to specify custom fields by name.
	public static function get($params = array(), $cache = NULL, $readFromCache = TRUE) {
		return parent::get(static::_mapCustomFieldNames($params), $cache, $readFromCache);
	}
	
	public static function getCustomFieldValue($id, $customField) {
		$id = static::getId($id);
		$customField = static::_getMatchingCustomField($customField);
		
		// If the entity is cached, use the cached object.
		$entity = static::getFromCache($id);
		if (!is_null($entity))
			return $entity->getCustomValue($customField);
		
		return static::callCustomValueApiGet($id, $customField);
	}
	
	public static function setCustomFieldValue($id, $customField, $value) {
		$id = static::getId($id);
		$customField = static::_getMatchingCustomField($customField);
		
		// If the entity is cached, use the cached object.
		$entity = static::getFromCache($id);
		if (!is_null($entity)) {
			$entity->setCustomValue($customField, $value);
			return;
		}
		
		static::callCustomValueApiSet($id, $customField, $value);
	}
	
	public static function addCustomFieldValues($id, $customField, $values) {
		$id = static::getId($id);
		$customField = static::_getMatchingCustomField($customField);
		
		// If the entity is cached, use the cached object.
		$entity = static::getFromCache($id);
		if (!is_null($entity)) {
			$entity->addCustomValues($customField, $values);
			return;
		}
		
		static::callCustomValueApiMultiAdd($id, $customField, $values);
	}
	
	public static function updateCustomFieldValues($id, $customField, $values) {
		$id = static::getId($id);
		$customField = static::_getMatchingCustomField($customField);
		
		// If the entity is cached, use the cached object.
		$entity = static::getFromCache($id);
		if (!is_null($entity)) {
			$entity->updateCustomValues($customField, $values);
			return;
		}
		
		static::callCustomValueApiMultiUpdate($id, $customField, $values);
	}
	
	// Create a new object to represent an entity in the database.
	protected function __construct($fieldSet, $cache, $mayHaveChildren = TRUE) {
		parent::__construct($fieldSet, $cache, $mayHaveChildren);
		$this->customFields = $fieldSet->customFields;
	}
	
	// In the specified set of API parameters, replace custom field names with API keys.
	protected function mapCustomFieldNames($params) {
		foreach (array_diff_key($params, static::$properties->validFields) as $param => $value) {
			if (preg_match('/^custom_\d+$/u', $param)) continue;
			
			$customField = $this->getMatchingCustomField($param, FALSE);
			if (!is_null($customField)) {
				unset($params[$param]);
				$params[$customField->apiKey] = $value;
			}
		}
		return $params;
	}
	
	protected function getCustomField($customField) {
		static $debug;
		if (is_null($debug)) $debug = CRM_Core_Config::singleton()->debug;
		
		if (is_a($customField, 'CRM_API_CustomField')) {
			if ($debug && !$this->isExtendedBy($customField->getGroup()))
				throw new Exception(ts('%1 does not extend %2', array(1 => $customField, 2 => static::$properties->entityType)));
			return $customField;
		}
		
		if (is_int($customField)) return CRM_API_CustomField::getSingle($customField);
		if (is_string($customField)) return $this->getMatchingCustomField($customField);
		throw new Exception(ts('%1 is not a valid custom field', array(1 => $customField)));
	}
	
	// TODO: Sort out the multiple, duplicated functions (static and non-static) for matching custom field by name,
	//       possibly using current version of API's ability to do the same.
	//       Hint: look at how it's inherited.
	protected function getMatchingCustomField($customFieldName, $required = TRUE) {
		$matchingCustomFields = array();
		foreach (CRM_API_CustomField::getByName($customFieldName) as $customField)
			if ($this->isExtendedBy($customField->getGroup()))
				$matchingCustomFields[] = $customField;
		if (count($matchingCustomFields) === 0 && !$required) return NULL;
		if (count($matchingCustomFields) !== 1)
			throw new Exception(ts('%1 matching custom fields named "%2" for %3', array(1 => count($matchingCustomFields), 2 => $customFieldName, 3 => CRM_API_Utils::toString($this))));
		return reset($matchingCustomFields);
	}
	
	protected function isExtendedBy($customGroup) {
		return static::_isExtendedBy($customGroup);
	}
	
	// In the specified set of API parameters, replace custom field names with API keys.
	protected static function _mapCustomFieldNames($params) {
		foreach (array_diff_key($params, static::$properties->validFields) as $param => $value) {
			$customField = static::_getMatchingCustomField($param, FALSE);
			if (!is_null($customField)) {
				unset($params[$param]);
				$params[$customField->apiKey] = $value;
			}
		}
		return $params;
	}
	
	protected static function _getMatchingCustomField($customField, $required = TRUE) {
		if (is_a($customField, 'CRM_API_CustomField'))
			return $customField;
		
		if (is_int($customField))
			return CRM_API_CustomField::getSingle($customField);
		
		$matchingCustomFields = array();
		foreach (CRM_API_CustomField::getByName($customField) as $aCustomField)
			if (static::_isExtendedBy($aCustomField->getGroup()))
				$matchingCustomFields[] = $aCustomField;
		if (count($matchingCustomFields) === 0 && !$required) return NULL;
		if (count($matchingCustomFields) !== 1)
			throw new Exception(ts('%1 matching custom fields named "%2" for %3', array(1 => count($matchingCustomFields), 2 => $customField, 3 => static::$properties->entityType)));
		return reset($matchingCustomFields);
	}
	
	protected static function _isExtendedBy($customGroup) {
		return $customGroup->extends === static::$properties->entityType;
	}
	
	protected static function callCustomValueApiGet($id, $customField) {
		$apiValues = static::callCustomValueApi('get', array('entity_id' => $id, 'return.' . $customField->apiKey => 1));
		
		// If the custom group allows multiple records then return an array.
		if ($customField->getGroup()->is_multiple) {
			$value = array();
			if ($apiValues) {
				foreach (reset($apiValues) as $recordId => $apiValue)
					if (is_int($recordId)) $value[$recordId] = $customField->normaliseValue($apiValue);
			}
		} else {
			if ($apiValues) {
				$values = reset($apiValues);
				$value = $customField->normaliseValue($values[0]);
			} else {
				$value = NULL;
			}
		}
		
		return $value;
	}
	
	protected static function callCustomValueApiSet($id, $customField, &$value) {
		if ($customField->getGroup()->is_multiple)
			throw new Exception(ts('Can\'t set multi-value %1 - use add and update functions instead', array(1 => $customField)));
		
		$value = static::serialiseCustomValue($customField, $value);
		static::callCustomValueApi('create', array('entity_id' => $id, $customField->apiKey => $value));
	}
	
	protected static function callCustomValueApiMultiAdd($id, $customField, &$values) {
		if (!$customField->getGroup()->is_multiple)
			throw new Exception(ts('Can\'t add values to %1 as it is not a multi-value field', array(1 => $customField)));
		if (!is_array($values)) $values = array($values);
		
		$params = array('entity_id' => $id);
		$id = 0;
		foreach ($values as &$value) {
			$value = static::serialiseCustomValue($customField, $value);
			$params[$customField->apiKey . ':' . --$id] = $value;
		}
		$apiValues = static::callCustomValueApi('create', $params);
	}
	
	protected static function callCustomValueApiMultiUpdate($id, $customField, &$values) {
		if (!$customField->getGroup()->is_multiple)
			throw new Exception(ts('Can\'t update values in %1 as it is not a multi-value field', array(1 => $customField)));
		
		$params = array('entity_id' => $id);
		foreach ($values as $recordId => &$value) {
			$value = static::serialiseCustomValue($customField, $value);
			$params[$customField->apiKey . ':' . $recordId] = $value;
		}
		$apiValues = static::callCustomValueApi('create', $params);
	}
	
	protected static function callCustomValueApi($action, $params) {
		$apiResult = civicrm_api('CustomValue', $action, array('version' => '3') + $params);
		if (civicrm_error($apiResult))
			throw new CRM_API_Exception(ts('Error in API call to %1 CustomValue with parameters %2', array(1 => $action, 2 => $params)), $apiResult);
		return $apiResult['values'];
	}
	
	// Convert fields, including custom fields, to a standard internal representation.
	protected static function normaliseFields($fields, $get = FALSE) {
		$customFields = array();
		foreach ($fields as $field => $value) {
			if (preg_match('/^custom_(\d+)$/u', $field, $matches)) {
				unset($fields[$field]);
				$customFields[$field] = CRM_API_CustomField::getSingle((int)$matches[1])->normaliseValue($value);
			}
		}
		
		return parent::normaliseFields($fields, $get) + $customFields;
	}
	
	// Convert parameters into the form required by CiviCRM's native API.
	protected static function serialiseParams($params) {
		$customFields = array();
		foreach ($params as $param => $value) {
			if (preg_match('/^custom_(\d+)$/u', $param, $matches)) {
				unset($params[$param]);
				$customFields[$param] = static::serialiseCustomValue(CRM_API_CustomField::getSingle((int)$matches[1]), $value);
			}
		}
		
		return parent::serialiseParams($params) + $customFields;
	}
	
	// Convert a custom value into the format expected by CiviCRM's native API.
	protected static function serialiseCustomValue($customField, $value) {
		switch ($customField->data_type) {
			case 'Date':
				return $value->format('YmdHis');
			
			case 'Boolean':
				return $value ? 1 : 0;
		}
		
		return $value;
	}
}

?>
