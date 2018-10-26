<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_Mailing extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('name', 'created_date'),
			'fieldsByType' => array(
				'array' => array('template_options')
			)
		));
	}
}

CRM_API_Mailing::init();

require_once 'CRM/API/MailingJob.php';
require_once 'CRM/API/MailingGroup.php';
require_once 'CRM/API/MailingRecipients.php';

?>
