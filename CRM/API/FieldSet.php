<?php

class CRM_API_FieldSet {
	public $id;
	public $fields;
	public $customFields;
	public $timestamp;
	
	public function __construct($fields) {
		if (!array_key_exists('id', $fields) || !is_int($fields['id']))
			throw new Exception(E::ts('Fields do not include a valid integer ID: %1', array(1 => CRM_API_Utils::toString($fields))));
		
		// Separate the ID.
		$this->id = $fields['id'];
		unset($fields['id']);
		
		// Separate the custom fields.
		$this->customFields = array();
		foreach ($fields as $field => $value) {
			if (preg_match('/^custom_(\d+)$/u', $field, $matches)) {
				unset($fields[$field]);
				$this->customFields[(int)$matches[1]] = $value;
			}
		}
		
		$this->fields = $fields;
		$this->timestamp = microtime(TRUE);
	}
}

?>
