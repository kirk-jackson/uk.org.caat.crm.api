<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_MailingJob extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('mailing_id', 'status', 'start_date'),
			'baoCreateUpdates' => FALSE
		));
		static::addParentRelationship('Mailing', 'Job', 'Jobs', 'CRM_API_Mailing', 'mailing_id');
	}
}

CRM_API_MailingJob::init();

require_once 'CRM/API/MailingEventQueue.php';

?>
