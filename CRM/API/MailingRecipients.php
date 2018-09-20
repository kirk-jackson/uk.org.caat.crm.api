<?php

class CRM_API_MailingRecipients extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('mailing_id', 'contact_id')
		));
		static::addParentRelationship('Mailing', 'Recipient', 'Recipients', 'CRM_API_Mailing', 'mailing_id');
	}
}

CRM_API_MailingRecipients::init();

?>
