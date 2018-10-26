<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_MailingGroup extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('mailing_id', 'group_type', 'entity_table', 'entity_id')
		));
		static::addParentRelationship('Mailing', 'Group', 'Groups', 'CRM_API_Mailing', 'mailing_id');
	}
}

CRM_API_MailingGroup::init();

?>
