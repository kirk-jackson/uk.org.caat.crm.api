<?php

class CRM_API_MailingEventDelivered extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'displayFields' => array('event_queue_id', 'time_stamp')
		));
		static::addParentRelationship('Queue', 'Delivery', 'Deliveries', 'CRM_API_MailingEventQueue', 'event_queue_id');
	}
}

CRM_API_MailingEventDelivered::init();

?>
