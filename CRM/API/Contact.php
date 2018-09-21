<?php

class CRM_API_Contact extends CRM_API_TaggableExtendableEntity {
	protected static $properties;
	
	protected static $groupIdCache = array();
	
	public function updateGroupStatus($group, $status, $dateTime = NULL) {
		static::updateIdGroupStatus($this->id, $group, $status, $dateTime);
	}
	
	public function inGroup($group, $status = 'Added', $readFromCache = TRUE) {
		return static::idInGroup($this->id, $group, $status, $readFromCache);
	}
	
	public function getGroupIds($status = 'Added', $readFromCache = TRUE) {
		return static::getIdGroupIds($this->id, $status, $readFromCache);
	}
	
	public function getGroups($status = 'Added', $readFromCache = TRUE) {
		$groups = array();
		foreach ($this->getGroupIds($status, $readFromCache) as $groupId)
			$groups[$groupId] = CRM_API_Group::getSingle($groupId);
		return $groups;
	}
	
	protected function uncacheObject($deleted = FALSE) {
		unset(static::$groupIdCache[$this->id]);
		parent::uncacheObject($deleted);
	}
	
	public static function updateIdGroupStatus($contactId, $group, $status, $dateTime = NULL) {
		$groupId = CRM_API_Group::getId($group);
		
		if (is_null($dateTime)) {
			$apiResult = civicrm_api('GroupContact', 'create', array(
				'version' => '3',
				'contact_id' => $contactId,
				'group_id' => $groupId,
				'status' => $status
			));
			if (civicrm_error($apiResult))
				throw new CRM_API_Exception(ts('Failed to set contact %1\'s status in group %2 to %3', array(1 => $contactId, 2 => $groupId, 3 => $status)), $apiResult);
		} else {
			// The CiviCRM API assumes that the status update is immediate.
			// However, it is often necessary to record a past status update.
			// The API cannot be used for this, so the database must be updated directly.
			
			$timestamp = $dateTime->getTimestamp();
			if ($timestamp > time())
				throw new Exception(ts('Cannot set contact %1\'s group %2 status at a future time (%3)', [1 => $contactId, 2 => $groupId, 3 => CRM_API_Utils::toString($dateTime)]));
			
			// Build an array of the contact's subscription changes for this group.
			$subscriptionHistory = [];
			foreach (CRM_API_SubscriptionHistory::get([
				'contact_id' => $contactId,
				'group_id' => $groupId
			]) as $subscriptionUpdate) {
				$subscriptionHistory[new DateTime($subscriptionUpdate->date)->getTimestamp()][] = $subscriptionUpdate;
			}
			
			// Find out whether the subscription history record already exists, and delete any simultaneous ones.
			if (array_key_exists($timestamp, $subscriptionHistory)) {
				foreach ($subscriptionHistory[$timestamp] as $subscriptionUpdate) {
					if (
						$subscriptionUpdate->method === 'API' &&
						$subscriptionUpdate->status === $status &&
						!isset($subscriptionUpdate->tracking) &&
						!isset($existingSubscriptionUpdate)
					) {
						$existingSubscriptionUpdate = $subscriptionUpdate;
					} else {
						$subscriptionUpdate->delete();
					}
				}
			}
			$subscriptionHistory[$timestamp] = NULL;
			ksort($subscriptionHistory);
			
			// Delete redundant subscription updates.
			foreach ($subscriptionHistory as $aTimestamp => $subscriptionUpdates) {
				if ($aTimestamp === $timestamp) break;
			}
			
			$previousStatus = 'Removed';
			$previousStatus = static::subscriptionUpdatesStatus($subscriptionUpdates);
			
			if ($status === $previousStatus) {
				// If this update has the same status as the previous one, then it's redundant, so delete it.
				if (isset($existingSubscriptionUpdate)) {
					$existingSubscriptionUpdate->delete();
				}
			} elseif (!isset($existingSubscriptionUpdate)) {
				// Create the subscription update if it doesn't already exist.
				CRM_API_SubscriptionHistory::create([
					'contact_id' => $contactId,
					'group_id' => $groupId,
					'date' => $dateTime,
					'method' => 'API',
					'status' => $status
				]);
				$previousStatus = $status;
			}
			
			// If the next update has the same status then it's redundant, so delete it.
			$subscriptionUpdates = next($subscriptionHistory);
			if ($subscriptionUpdates) {
				$statuses = [];
				foreach ($subscriptionUpdates as $subscriptionUpdate) {
					$statuses[$subscriptionUpdate->status] = NULL;
				}
				if (count($statuses) === 1) {
					if (key($statuses) === $previousStatus) {
						foreach ($subscriptionUpdates as $subscriptionUpdate) {
							$subscriptionUpdate->delete();
						}
					}
					$previousStatus = key($statuses);
				} else {
					$previousStatus = NULL;
				}
			}
			
			// If the
			$groupContactDao = CRM_Core_DAO::executeQuery("
				SELECT id, status
				FROM civicrm_group_contact
				WHERE group_id = %1 AND contact_id = %2
			", [
				1 => [$groupId, 'Positive'],
				2 => [$contactId, 'Positive']
			]);
			if ($groupContactDao->fetch()) {
				
			}
			$groupContactDao->free();
			
			if (!is_null($groupContact)) {
				if ($groupContact->status !== $status && $status === $previousStatus) {
					CRM_Core_DAO::executeQuery("
						UPDATE civicrm_group_contact
						SET status = %1
						WHERE id = %2
					", [
						1 => [$status, 'String'],
						2 => [$groupContact->id, 'Positive']
					]);
				}
			} else {
				CRM_Core_DAO::executeQuery("
					INSERT INTO civicrm_group_contact
					(group_id, contact_id, status)
					VALUES (%1, %2, %3)
				", [
					1 => [$groupId, 'Positive'],
					2 => [$contactId, 'Positive'],
					3 => [$status, 'String']
				]);
			}
		}
		
		// Update the cache.
		if (array_key_exists($contactId, static::$groupIdCache)) {
			foreach (static::$groupIdCache[$contactId] as $aStatus => &$groups) {
				if ($aStatus === $status)
					$groups[$groupId] = NULL;
				else
					unset($groups[$groupId]);
			}
		}
	}
	
	public static function idInGroup($contactId, $group, $status = 'Added', $readFromCache = TRUE) {
		return in_array(CRM_API_Group::getId($group), static::getIdGroupIds($contactId, $status, $readFromCache));
	}
	
	public static function getIdGroupIds($contactId, $status = 'Added', $readFromCache = TRUE) {
		$groupIds = array();
		
		if ($status === 'Any') $status = array('Added', 'Removed', 'Pending');
		if (is_array($status)) {
			foreach ($status as $aStatus)
				$groupIds = array_merge($groupIds, static::getIdGroupIds($contactId, $aStatus));
			return $groupIds;
		}
		
		// If cached then use the cache.
		if ($readFromCache && array_key_exists($contactId, static::$groupIdCache) && array_key_exists($status, static::$groupIdCache[$contactId]))
			return array_keys(static::$groupIdCache[$contactId][$status]);
		
		$apiResult = civicrm_api('GroupContact', 'get', array(
			'version' => '3',
			'contact_id' => $contactId,
			'status' => $status
		));
		if (civicrm_error($apiResult))
			throw new CRM_API_Exception(ts('Failed to retrieve groups for which contact %1 is %2', array(1 => $contactId, 2 => $status)), $apiResult);
		
		foreach ($apiResult['values'] as $fields)
			$groupIds[] = $fields['group_id'];
		
		if (!is_null(static::getFromCache($contactId)))
			static::$groupIdCache[$contactId][$status] = array_fill_keys($groupIds, NULL);
		
		return $groupIds;
	}
	
	protected function isExtendedBy($customGroup) {
		return static::_isExtendedBy($customGroup) && (
			$customGroup->extends === 'Contact' || $customGroup->extends === $this->contact_type
		) && (
			!isset($customGroup->extends_entity_column_value) ||
			array_intersect($customGroup->extends_entity_column_value, $this->contact_sub_type)
		);
	}
	
	protected static function _isExtendedBy($customGroup) {
		return parent::_isExtendedBy($customGroup) || in_array($customGroup->extends, array('Individual', 'Household', 'Organization'));
	}
	
	public function getActivities($params = array(), $cache = NULL, $readFromCache = TRUE) {
		return CRM_API_Activity::get(array('target_contact_id' => $this->id) + $params, $cache, $readFromCache);
	}
	
	// Given an array of group subscription history updates, return their status.
	protected static function subscriptionUpdatesStatus($subscriptionUpdates) {
		if (!$subscriptionUpdates) return NULL;
		$status = array_shift($subscriptionUpdates)->status;
		foreach ($subscriptionUpdates as $subscriptionUpdate) {
			if ($subscriptionUpdate->status !== $status) return NULL;
		}
		return $status;
	}
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType(array(
			'lookups' => array('external_identifier'),
			'defaultStringLookup' => 'external_identifier',
			'displayFields' => array('external_identifier', 'display_name', 'email', 'postal_code'),
			'fieldsByType' => array(
				'string' => array('individual_prefix', 'individual_suffix', 'gender', 'current_employer', 'email', 'postal_code'),
				'array' => array('contact_sub_type', 'preferred_communication_method')
			),
			'readOnlyFields' => array('sort_name', 'display_name'),
			'fieldsMayNotMatchApiParams' => array('employer_id', 'is_deleted', 'preferred_communication_method'),
			'canUndelete' => TRUE
		));
	}
}

CRM_API_Contact::init();

require_once 'CRM/API/Address.php';
require_once 'CRM/API/Phone.php';
require_once 'CRM/API/Email.php';
require_once 'CRM/API/Note.php';
require_once 'CRM/API/Contribution.php';
require_once 'CRM/API/ContributionSoft.php';
require_once 'CRM/API/ContributionRecur.php';
require_once 'CRM/API/Participant.php';

?>
