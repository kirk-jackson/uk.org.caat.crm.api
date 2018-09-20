<?php

class CRM_API_LocBlock extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('address_id')
		));
	}
}

CRM_API_LocBlock::init();

?>
