<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_MailingEventQueue extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('job_id', 'contact_id')
		));
		static::addParentRelationship('Job', 'EventQueue', 'EventQueues', 'CRM_API_MailingJob', 'job_id');
	}
}

CRM_API_MailingEventQueue::init();

require_once 'CRM/API/MailingEventDelivered.php';

?>
