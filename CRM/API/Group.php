<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_Group extends CRM_API_ExtendableEntity {
	protected static $properties;
	
	// The CiviCRM API expects the group type array to be flipped.
	protected static function serialiseParams($params) {
		if (isset($params['group_type']))
			$params['group_type'] = array_fill_keys($params['group_type'], NULL);
		return parent::serialiseParams($params);
	}
	
	public function getContactIds() {
		try {
			$apiResult = civicrm_api3('GroupContact', 'get', ['group_id' => $this->id]);
		} catch (CiviCRM_API3_Exception $e) {
			throw new CRM_API_Exception(E::ts('Failed to retrieve contacts in %1', [1 => $this]), $e);
		}
		
		$contactIds = array();
		foreach ($apiResult['values'] as $fields) $contactIds[] = $fields['contact_id'];
		return $contactIds;
	}
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType([
			'lookups' => ['name', 'title'],
			'defaultStringLookup' => 'title',
			'displayFields' => ['title'],
			'fieldsByType' => [
				'array' => ['group_type']
			],
			'fieldsMayNotMatchApiParams' => ['where_clause', 'select_tables', 'where_tables']
		]);
	}
}

CRM_API_Group::init();

?>
