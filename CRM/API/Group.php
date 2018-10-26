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
		$apiResult = civicrm_api('GroupContact', 'get', array(
			'version' => '3',
			'group_id' => $this->id
		));
		if (civicrm_error($apiResult))
			throw new CRM_API_Exception(E::ts('Failed to retrieve contacts in %1', array(1 => $this)), $apiResult);
		
		$contactIds = array();
		foreach ($apiResult['values'] as $fields) $contactIds[] = $fields['contact_id'];
		return $contactIds;
	}
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'lookups' => array('name', 'title'),
			'defaultStringLookup' => 'title',
			'displayFields' => array('title'),
			'fieldsByType' => array(
				'array' => array('group_type')
			),
			'fieldsMayNotMatchApiParams' => array('where_clause', 'select_tables', 'where_tables')
		));
	}
}

CRM_API_Group::init();

?>
