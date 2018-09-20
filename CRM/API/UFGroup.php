<?php

class CRM_API_UFGroup extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('title', 'group_type'),
			'fieldsByType' => array(
				'int' => array('is_cms_user')
			)
		));
	}
}

CRM_API_UFGroup::init();

?>
