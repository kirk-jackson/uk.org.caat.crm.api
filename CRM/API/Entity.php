<?php

use CRM_API_ExtensionUtil as E;

require_once 'api/v3/utils.php';
require_once 'api/Exception.php';

// This class is an intermediate layer that /represents/ an entity in the database - it is not the entity itself.
abstract class CRM_API_Entity {
	protected $id;
	protected $fields;
	protected $deleted = FALSE;
	protected $timestamp; // The time at which the fields were last updated.
	
	private static $childClasses = array(); // An array of all child classes that have been instantiated.
	
	// Update the entity in the database.
	public function update($params, $always = TRUE) {
		static::assertIdNotSupplied($params);
		$params = static::normaliseFields($params);
		
		$latest = $this->latest();
		$latest->assertNotDeleted();
		
		// If the fields aren't changing, there's no need to call the API.
		if (!$always && array_intersect_key($latest->fields, $params) == $params) return;
		
		// When using API create to update an entity, if certain fields aren't supplied, they get reset to defaults (see CRM-12144).
		// What's more, values that aren't explicitly supplied may not be available in pre and post hooks.
		// To avoid these problems, add cached fields to the update parameters.
		$params += array_filter(
			array_intersect_key($latest->fields, static::$properties->persistFields),
			function($value) {return !is_null($value) && $value !== '';}
		);
		
		// Call the API.
		$fieldSet = static::callApiSingle('create', array('id' => $this->id) + $params);
		
		// The fields returned by API create may not be complete, so supplement with existing fields.
		$fieldSet->fields += $latest->fields;
		
		$this->updateAndReconcile($fieldSet);
	}
	
	// Delete the entity from the database.
	public function delete($permanent = TRUE) {
		$params = array('id' => $this->id);
		if (static::$properties->canUndelete) $params['skip_undelete'] = $permanent ? 1 : 0;
		static::callApi('delete', $params);
		
		// Is the entity being moved to the trash rather than permanently deleted?
		if (static::$properties->canUndelete && !$permanent) {
			$latest = $this->latest();
			$latest->assertNotDeleted();
			$this->updateAndReconcile(new CRM_API_FieldSet(array('id' => $this->id, 'is_deleted' => TRUE) + $latest->fields));
			return;
		}
		
		$this->deleteAndReconcile();
	}
	
	// Reload the entity from the database, in case it has changed.
	public function refresh() {
		// Fetch the entity's field values from database.
		$fieldSet = static::callApiSingle('get', array('id' => $this->id), FALSE);
		
		// If no value is returned, the entity must've been deleted from the database.
		if (is_null($fieldSet)) {
			$this->deleteAndReconcile();
			return;
		}
		
		// Update the fields.
		$this->updateAndReconcile($fieldSet);
	}
	
	// Put the object in the cache.
	public function cache() {
		$this->assertNotDeleted();
		if (is_null(static::getFromCache($this->id))) $this->cacheObject();
	}
	
	// Take the object out of the cache.
	public function uncache() {
		// Make sure this entity isn't in another entity's child cache.
		foreach (static::$properties->parentRelationships as $parentRelationship)
			if ($parentRelationship->isChildsParentCached($this))
				throw new Exception(E::ts('Cannot uncache %1 as its parent %2 is cached', array(1 => $this, 2 => $parentRelationship->parentClass)));
		
		$entity = static::getFromCache($this->id);
		if (!is_null($entity)) $entity->uncacheObject();
	}
	
	// Has the entity been permanently deleted from the database?
	public function isDeleted() {
		return $this->deleted;
	}
	
	// Return an array of the entity's fields.
	public function fields() {
		$this->assertNotDeleted();
		return array_merge(array('id' => $this->id), $this->fields);
	}
	
	// Return the value of an individual field.
	public function __get($field) {
		if ($field === 'id') return $this->id;
		if ($field === 'entityType') return static::$properties->entityType;
		$this->assertNotDeleted();
		if (array_key_exists($field, $this->fields)) return $this->fields[$field];
		throw new Exception(E::ts('%1 does not have a field called %2', array(1 => $this, 2 => $field)));
	}
	
	// Field values cannot be set individually - they can only be changed using update().
	public function __set($field, $value) {
		throw new Exception(E::ts('Cannot set %1\'s field %2 - use update() to update the database', array(1 => static::$properties->entityType, 2 => $field)));
	}
	
	// Is a particular field set and not null?
	public function __isset($field) {
		if ($field === 'id') return TRUE;
		$this->assertNotDeleted();
		return isset($this->fields[$field]);
	}
	
	// Dynamic methods to access parent entities or child entities
	public function __call($functionName, $args) {
		// Functions operating on one of the entity's parents
		if (preg_match('/^(get)(' . implode('|', array_keys(static::$properties->parentRelationships)) . ')$/u', $functionName, $matches)) {
			$parentRelationship = static::$properties->parentRelationships[$matches[2]];
			switch ($matches[1]) {
				case 'get':
					list($required, $cache, $readFromCache) = $args + array(TRUE, NULL, TRUE);
					$parentClass = $parentRelationship->parentClass;
					$parentIdField = $parentRelationship->parentIdField;
					
					// It's possible for a "child" entity to have no parent, e.g. not all addresses belong to contacts.
					if (is_null($this->$parentIdField)) {
						if (!$required) return NULL;
						throw new Exception(E::ts('%1 does not have a %2', array(1 => $this, 2 => $parentClass::$properties->entityType)));
					}
					
					return $parentClass::getSingle($this->$parentIdField, TRUE, $cache, $readFromCache);
			}
		}
		
		// Functions operating on one of the entity's children
		if (preg_match('/^(get|create|update|delete)(' . implode('|', array_keys(static::$properties->childRelationships)) . ')$/u', $functionName, $matches)) {
			$childRelationship = static::$properties->childRelationships[$matches[2]];
			if (!$childRelationship->isParentCached($this->id))
				static::getFromCache($this->id, TRUE)->cacheChildren($childRelationship);
			switch ($matches[1]) {
				case 'get':
					list($field, $value, $required) = $args + array(NULL, NULL, TRUE);
					switch (count($args)) {
						case 1: $value = $field; $field = NULL; break;
						case 2: if (is_bool($value)) {$required = $value; $value = $field; $field = NULL;} break;
					}
					return $childRelationship->getChild($this->id, $field, $value, $required);
				
				case 'create':
					list($params, $cache) = $args + array(NULL, NULL);
					$params[$childRelationship->parentIdField] = $this->id;
					if (isset($childRelationship->parentDbTableField))
						$params[$childRelationship->parentDbTableField] = static::$properties->dbTable;
					$childClass = $childRelationship->childClass;
					return $childClass::create($params, $cache);
				
				case 'update':
					list($field, $value, $params) = $args + array(NULL, NULL, NULL);
					if (count($args) === 2) {$params = $value; $value = $field; $field = NULL;}
					return $childRelationship->getChild($this->id, $field, $value)->update($params);
				
				case 'delete':
					list($field, $value, $required) = $args + array(NULL, NULL, TRUE);
					switch (count($args)) {
						case 1: $value = $field; $field = NULL; break;
						case 2: if (is_bool($value)) {$required = $value; $value = $field; $field = NULL;} break;
					}
					$child = $childRelationship->getChild($this->id, $field, $value, $required);
					if (!is_null($child)) $child->delete();
					return;
			}
		}
		
		// Functions operating on a set of the entity's children
		if (preg_match('/^(get|delete|uncache)(' . implode('|', array_keys(static::$properties->childRelationshipsPlural)) . ')$/u', $functionName, $matches)) {
			$childRelationship = static::$properties->childRelationshipsPlural[$matches[2]];
			
			if ($matches[1] === 'uncache') {
				if ($childRelationship->isParentCached($this->id))
					static::getFromCache($this->id, TRUE)->uncacheChildren($childRelationship);
				return;
			}
			
			if (!$childRelationship->isParentCached($this->id))
				static::getFromCache($this->id, TRUE)->cacheChildren($childRelationship);
			switch ($matches[1]) {
				case 'get':
					return $childRelationship->getChildren($this->id);
				
				case 'delete':
					foreach ($childRelationship->getChildren($this->id) as $child) $child->delete();
					return;
			}
		}
		
		throw new Exception(E::ts('Call to undefined method %1::%2()', array(1 => get_called_class(), 2 => $functionName)));
	}
	
	// Output the entity as a string (for diagnostic purposes).
	public function __toString() {
		$string = static::$properties->entityType . ' ' . $this->id;
		if ($this->isDeleted()) {
			$string .= ' (deleted)';
		} else {
			$displayFields = array();
			foreach (static::$properties->displayFields as $field)
				if (isset($this->$field) && $this->$field !== '')
					$displayFields[] = $field . ' = ' . CRM_API_Utils::toString($this->$field);
			if ($displayFields) $string .= ' (' . implode(', ', $displayFields) . ')';
		}
		return $string;
	}
	
	// Create an entity in the database.
	public static function create($params, $cache = NULL) {
		static::assertIdNotSupplied($params);
		static::assertRequiredFields($params, static::$properties->paramsRequiredByCreate);
		$params = static::normaliseFields($params);
		
		// Call the API to create the entity in the database.
		$fieldSet = static::callApiSingle('create', $params);
		if (!static::$properties->createReturnsFields) $fieldSet->fields = $params;
		
		if (is_null($cache)) $cache = static::$properties->cacheByDefault;
		if (!$cache) static::$properties->allCached = FALSE;
		
		// Check to see if the entity was just cached by a 'post' hook.
		$entity = static::getFromCache($fieldSet->id);
		if (!is_null($entity)) {
			if (!$cache) $entity->uncache();
			return $entity;
		}
		
		// The create action may not return the 'is_deleted' field.
		if (static::$properties->canUndelete)
			$fieldSet->fields += ['is_deleted' => FALSE];
		
		return new static($fieldSet, $cache, FALSE);
	}
	
	// Get entities from the database.
	public static function get($params = array(), $cache = NULL, $readFromCache = TRUE) {
		if (!is_array($params))
			throw new Exception(E::ts('%1 passed instead of parameter array', array(1 => CRM_API_Utils::toString($params))));
		$params = static::normaliseFields($params, TRUE);
		
		$gettingAll = !$params;
		
		if ($readFromCache) {
			// If getting all entities, and they're already all cached, then return the cache.
			if ($gettingAll && static::$properties->allCached) return static::$properties->lookups['id'];
			
			// Can a lookup be used to avoid querying the database?
			foreach (static::$properties->lookupFields as $lookupKey => $lookupFields) {
				if (count($params) === count($lookupFields) && !array_diff_key($params, $lookupFields)) {
					$lookupValue = static::lookupValue($lookupKey, $params);
					if (!is_null($lookupValue) && array_key_exists($lookupValue, static::$properties->lookups[$lookupKey])) {
						$entity = static::$properties->lookups[$lookupKey][$lookupValue];
						return array($entity->id => $entity);
					}
					break;
				}
			}
			
			// Can a parent relationship be used to avoid querying the database?
			foreach (static::$properties->parentRelationships as $parentRelationship) {
				if (isset($params[$parentRelationship->parentIdField]) && $parentRelationship->isParentCached($parentId = $params[$parentRelationship->parentIdField])) {
					if (count($params) === 1)
						return $parentRelationship->getChildren($parentId);
					
					if (count($params) === 2) {
						list($otherParam, $otherValue) = each(array_diff_key($params, array($parentRelationship->parentIdField => NULL)));
						if ($parentRelationship->hasLookupField($otherParam)) {
							$entity = $parentRelationship->getChild($parentId, $otherParam, $otherValue, FALSE);
							if (is_null($entity)) return array();
							return array($entity->id => $entity);
						}
					}
				}
			}
		}
		
		// Call API get.
		$entities = array();
		foreach (static::callApi('get', $params) as $fieldSet) {
			$entity = static::getObjectFromFieldSet($fieldSet, $cache);
			$entities[$entity->id] = $entity;
		}
		
		// If the search returned all entities, mark that they're all cached.
		if ($gettingAll) static::$properties->allCached = TRUE;
		
		return $entities;
	}
	
	// Get a single entity from the database.
	public static function getSingle($params, $required = TRUE, $cache = NULL, $readFromCache = TRUE) {
		// Check the parameters.
		if (is_array($params))
			$getParams = $params;
		elseif (is_string($params) && !is_null(static::$properties->defaultStringLookup))
			$getParams = array(static::$properties->defaultStringLookup => $params);
		elseif (is_int($params) || is_string($params) && ctype_digit($params))
			$getParams = array('id' => $params);
		elseif (!is_array($params))
			throw new Exception(E::ts('Cannot use a %1 to look up a %2', array(1 => CRM_API_Utils::toString($params), 2 => static::$properties->entityType)));
		
		$entities = static::get($getParams, $cache, $readFromCache);
		
		// Check that only one result was returned.
		if (count($entities) !== 1) {
			if (!$required && !$entities) return NULL;
			$message = E::ts('Expected to get a single %1 from database but got %2 with parameter(s) %3', array(1 => static::$properties->entityType, 2 => count($entities), 3 => CRM_API_Utils::toString($params)));
			if (is_string($params) && ctype_digit($params))
				$message .= ' ' . E::ts('(ID must be specified as an integer value, not a string value.)');
			throw new Exception($message);
		}
		
		return reset($entities);
	}
	
	// When API get is not sophisticated enough, a query can be used.
	// The query must select all fields of the entity including ID.
	public static function getFromQuery($query, $params = array(), $cache = NULL) {
		$entities = array();
		$dao = CRM_Core_DAO::executeQuery($query, $params);
		while ($dao->fetch()) {
			$entity = static::getObject($dao, $cache);
			$entities[$entity->id] = $entity;
		}
		$dao->free();
		return $entities;
	}
	
	// Return an object that represents a database entity with known field values.
	public static function getObject($fields, $cache = NULL) {
		if (is_object($fields)) $fields = get_object_vars($fields);
		return static::getObjectFromFieldSet(new CRM_API_FieldSet(static::filterFields($fields)), $cache);
	}
	
	// Update when only the ID is known and getting from the database is unnecessary.
	public static function updateById($id, $params, $cache = NULL) {
		$params = static::normaliseFields($params);
		$entity = static::getFromCache($id);
		if (is_null($entity)) {
			static::assertIdNotSupplied($params);
			$params['id'] = $id;
			// For some APIs, the 'create' action alters the value of fields that aren't supplied. (See CRM-12144.)
			// TODO: Check if this is still the case, and if so, develop a workaround.
			$fieldSet = static::callApiSingle('create', $params);
			$entity = new static($fieldSet, $cache);
		} else {
			$entity->update($params);
		}
		return $entity;
	}
	
	// Delete when only the ID is known and getting from the database is unnecessary.
	public static function deleteById($id, $permanent = TRUE) {
		$entity = static::getFromCache($id);
		if (is_null($entity)) {
			$params = array('id' => $id);
			if (static::$properties->canUndelete) $params['skip_undelete'] = $permanent ? 1 : 0;
			static::callApi('delete', $params);
		} else {
			$entity->delete($permanent);
		}
	}
	
	// Set whether entities of this type should be cached by default.
	public static function setCacheByDefault($cacheByDefault) {
		static::$properties->cacheByDefault = $cacheByDefault;
	}
	
	// Cache all entities of this type.
	public static function cacheAll($readFromCache = TRUE) {
		static::get(array(), TRUE, $readFromCache);
	}
	
	// Uncache all entities of this type.
	public static function uncacheAll() {
		foreach (static::$properties->lookups['id'] as $entity) $entity->uncache();
	}
	
	// Uncache all contact entities and entities that depend on them.
	// Helps avoid memory overflow when importing many contacts.
	final public static function uncacheAllContactData() {
		foreach ([
			'Contact',
			'Email', 'Phone', 'Address', 'Note',
			'SubscriptionHistory',
			'Relationship',
			'ActivityContact', 'Activity',
			'ContributionRecur', 'Contribution', 'ContributionSoft',
			'Participant',
			'MailingRecipients', 'MailingEventQueue', 'MailingEventDelivered'
		] as $entityType) {
			$class = 'CRM_API_' . $entityType;
			$class::uncacheAll();
		}
	}
	
	// Convert a mixed-type argument to an entity ID. Useful for flexible arguments in public methods.
	public static function getId($arg, $required = TRUE) {
		if (is_int($arg)) return $arg; // Assume it's a valid ID to avoid a potentially unnecessary database query.
		$entity = static::getObj($arg, $required);
		if (!is_null($entity)) return $entity->id;
	}
	
	// Convert a mixed-type argument to an object. Useful for flexible arguments in public methods.
	public static function getObj($arg, $required = TRUE) {
		if (is_a($arg, get_called_class())) return $arg;
		if (is_int($arg) || is_string($arg) && !is_null(static::$properties->defaultStringLookup))
			return static::getSingle($arg, $required);
		throw new Exception(E::ts('%1 is not a valid %2', array(1 => CRM_API_Utils::toString($arg), 2 => static::$properties->entityType)));
	}
	
	// Return data about the cache.
	public static function diagnostics() {
		$diagnostics = array();
		foreach (self::$childClasses as $childClass)
			$diagnostics[$childClass] = count($childClass::$properties->lookups['id']);
		return $diagnostics;
	}
	
	// Create a new object to represent an entity in the database.
	protected function __construct($fieldSet, $cache, $mayHaveChildren = TRUE) {
		// Initialise the object's properties.
		$this->id = $fieldSet->id;
		$this->fields = $fieldSet->fields;
		$this->timestamp = $fieldSet->timestamp;
		
		// Cache if required.
		if (is_null($cache)) $cache = static::$properties->cacheByDefault;
		if ($cache) $this->cacheObject($mayHaveChildren);
	}
	
	// The entity has been updated so update the called object, the cached object and the cache if necessary.
	protected function updateAndReconcile($fieldSet) {
		$entity = static::getFromCache($this->id);
		
		if (!is_null($entity)) $entity->updateAndRecache($fieldSet);
		
		if ($entity !== $this) $this->setFields($fieldSet);
	}
	
	// The entity has been deleted so update the called object, the cached object and the cache if necessary.
	protected function deleteAndReconcile() {
		$entity = static::getFromCache($this->id);
		
		if (!is_null($entity)) {
			$entity->uncacheObject(TRUE);
			$entity->setDeleted();
		}
		
		if ($entity !== $this) $this->setDeleted();
	}
	
	// Return the object with the most up-to-date representation of the entity -
	// the called object or the cached object (in case they're not the same).
	protected function latest() {
		$entity = static::getFromCache($this->id);
		if (!is_null($entity) && $entity->timestamp > $this->timestamp)
			return $entity;
		return $this;
	}
	
	// Update a cached object and the cache.
	protected function updateAndRecache($fieldSet) {
		if ($fieldSet->fields == $this->fields) return;
		
		// Work out a list of parent-child relationships in which this entity's parent is cached.
		$activeParentRelationships = array();
		foreach (static::$properties->parentRelationships as $parentRelationship)
			if ($parentRelationship->isChildsParentCached($this))
				$activeParentRelationships[] = $parentRelationship;
		
		// Remove from parent-child relationship caches.
		foreach ($activeParentRelationships as $parentRelationship)
			$parentRelationship->uncacheChild($this);
		
		$this->removeFromLookups();
		$this->setFields($fieldSet);
		$this->addToLookups();
		
		// Add to parent-child relationship caches.
		foreach ($activeParentRelationships as $parentRelationship)
			$parentRelationship->cacheChild($this);
	}
	
	// Add an uncached object to the cache.
	protected function cacheObject($mayHaveChildren = TRUE) {
		$this->addToLookups();
		
		// Add this entity's children to child caches.
		if (static::$properties->autoloadChildren) {
			foreach (static::$properties->childRelationships as $childRelationship) {
				if ($mayHaveChildren)
					$this->cacheChildren($childRelationship);
				else
					$childRelationship->cacheParent($this->id);
			}
		}
		
		// Add this entity to the extant child caches of any parent entities.
		foreach (static::$properties->parentRelationships as $parentRelationship)
			if ($parentRelationship->isChildsParentCached($this))
				$parentRelationship->cacheChild($this);
	}
	
	// Remove a cached object from the cache.
	protected function uncacheObject($deleted = FALSE) {
		// Remove this entity from the extant child caches of any parent entities.
		foreach (static::$properties->parentRelationships as $parentRelationship)
			if ($parentRelationship->isChildsParentCached($this))
				$parentRelationship->uncacheChild($this);
		
		// Remove this entity's children from child caches.
		foreach (static::$properties->childRelationships as $childRelationship) {
			if ($childRelationship->isParentCached($this->id)) {
				$children = $childRelationship->getChildren($this->id);
				$childRelationship->uncacheParent($this->id);
				foreach ($children as $child) $child->uncacheObject($deleted);
			}
		}
		
		$this->removeFromLookups();
		
		if (!$deleted) static::$properties->allCached = FALSE;
	}
	
	// Record that the database entity has been updated.
	protected function setFields($fieldSet) {
		if ($fieldSet->id !== $this->id)
			throw new Exception(E::ts('Fields set\'s ID %1 does not match entity\'s ID %2', array(1 => $fieldSet->id, 2 => $this->id)));
		$this->fields = $fieldSet->fields;
		$this->timestamp = $fieldSet->timestamp;
	}
	
	protected function setDeleted() {
		$this->fields = NULL;
		$this->deleted = TRUE;
		$this->timestamp = microtime(TRUE);
	}
	
	// Raise an error if the entity has been deleted.
	protected function assertNotDeleted() {
		if ($this->deleted)
			throw new Exception(E::ts('%1 has been deleted', array(1 => $this)));
	}
	
	// Add the object to the cache's lookups.
	protected function addToLookups() {
		foreach (static::$properties->lookups as $lookupKey => &$refLookup) {
			if ($lookupKey === 'id') {
				$lookupValue = $this->id;
			} else {
				$lookupValue = static::lookupValue($lookupKey, $this->fields);
				if (is_null($lookupValue)) continue;
			}
			$refLookup[$lookupValue] = $this;
		}
	}
	
	// Remove the object from the cache's lookups.
	protected function removeFromLookups() {
		foreach (static::$properties->lookups as $lookupKey => &$refLookup) {
			if ($lookupKey === 'id') {
				$lookupValue = $this->id;
			} else {
				$lookupValue = static::lookupValue($lookupKey, $this->fields);
				if (is_null($lookupValue)) continue;
			}
			unset($refLookup[$lookupValue]);
		}
	}
	
	// Cache this object's children and its relationship to them.
	protected function cacheChildren($childRelationship) {
		$childClass = $childRelationship->childClass;
		$params = array($childRelationship->parentIdField => $this->id);
		if (isset($childRelationship->parentDbTableField))
			$param[$childRelationship->parentDbTableField] = static::$properties->dbTable;
		$children = $childClass::get($params, TRUE);
		
		$childRelationship->cacheParent($this->id);
		foreach ($children as $child) $childRelationship->cacheChild($child);
	}
	
	// Uncache this object's children and its relationship to them.
	protected function uncacheChildren($childRelationship) {
		$children = $childRelationship->getChildren($this->id);
		$childRelationship->uncacheParent($this->id);
		foreach ($children as $child) $child->uncache();
	}
	
	// Return an object that represents an existing entity in the database.
	protected static function getObjectFromFieldSet($fieldSet, $cache) {
		// If there's a cached object representing the entity then just update it.
		$entity = static::getFromCache($fieldSet->id);
		if (!is_null($entity)) {
			$entity->updateAndRecache($fieldSet);
			return $entity;
		}
		
		return new static($fieldSet, $cache);
	}
	
	// If there's a cached object with the specified ID then return it.
	protected static function getFromCache($id, $required = FALSE) {
		if (!array_key_exists($id, static::$properties->lookups['id'])) {
			if (!$required) return NULL;
			throw new Exception(E::ts('%1 is not cached', array(1 => $entity)));
		}
		
		return static::$properties->lookups['id'][$id];
	}
	
	// Call CiviCRM's native API and return an array of field sets.
	protected static function callApi($action, $params) {
		static $debug;
		if (is_null($debug)) $debug = CRM_Core_Config::singleton()->debug;
		
		if ($action !== 'get' && $readOnlyFields = array_intersect_key($params, static::$properties->readOnlyFields))
			throw new Exception(E::ts('Read-only field(s) %1 supplied to API %2 %3', array(1 => implode(', ', $readOnlyFields), 2 => $action, 3 => static::$properties->entityType)));
		
		$apiParams = array('version' => '3');
		if ($debug) $apiParams['debug'] = 1;
		$apiParams += static::serialiseParams($params);
		
		if ($action === 'get') {
			// Override the default row limit.
			if (!isset($apiParams['options']['limit']))
				$apiParams['options']['limit'] = PHP_INT_MAX;
			
			// If the entity has a weight field, use it as the default sort order.
			if (in_array('weight', static::$properties->fieldsByType['int']) && !isset($apiParams['options']['sort']))
				$apiParams['options']['sort'] = 'weight';
		}
		
		// The API seems to ignore NULL values, so change them to empty strings.
		$apiParams = array_map(function ($value) {if (is_null($value)) return ''; return $value;}, $apiParams);
		
		if ($action === 'create' && array_key_exists('id', $params) && !static::$properties->baoCreateUpdates) {
			// If the entity type doesn't support use of the create action to update, then use fallback function instead.
			$apiResult = static::callApiBasicCreate($apiParams);
		} else {
			// Call the specific API.
			$apiResult = civicrm_api(static::$properties->entityType, $action, $apiParams);
			if (civicrm_error($apiResult) && $apiResult['error_code'] === 'not-found') {
				if (mb_strpos(static::$properties->daoClass, '_BAO_') === FALSE && $action === 'create') {
					// If the entity's DAO class is actually a BAO class, the generic create function won't work, so use fallback function instead.
					$apiResult = static::callApiBasicCreate($apiParams);
				} else {
					// Call the generic API.
					$apiFunction = '_civicrm_api3_basic_' . $action;
					$apiResult = $apiFunction(static::$properties->daoClass, $apiParams);
				}
			}
		}
		if (civicrm_error($apiResult))
			throw new CRM_API_Exception(E::ts('Error in API call to %1 %2 %3', array(1 => $action, 2 => static::$properties->entityType, 3 => CRM_API_Utils::toString($params))), $apiResult);
		
		$apiValues = $apiResult['values'];
		if ($action === 'get' && $apiValues === FALSE) $apiValues = array();
		
		// If the API has not returned a valid array of results then quit.
		if (!is_array($apiValues) || $apiValues && !is_array(reset($apiValues))) {
			if (in_array($action, array('create', 'get')))
				throw new Exception(E::ts('API call to %1 %2 %3 returned %4 instead of array of values', array(1 => $action, 2 => static::$properties->entityType, 3 => CRM_API_Utils::toString($params), 4 => $apiValues)));
			return;
		}
		
		// Validate the returned values and convert to an array of field sets.
		$fieldSets = array();
		$createAsUpdate = $action === 'create' && array_key_exists('id', $params);
		$checkFieldsMatchParams = $debug && !($action === 'create' && !static::$properties->createReturnsFields);
		if ($checkFieldsMatchParams) $filteredParams = static::filterParamsForMatching($params);
		foreach ($apiValues as $fields) {
			// There are some fields that aren't returned by API get when empty but might be needed, so they must be explicitly created.
			if ($action === 'get')
				$fields += static::$properties->fillInFields;
			
			// When using the create action to update, the API may erroneously return empty values for fields that were not supplied.
			if ($createAsUpdate)
				foreach (array_diff_key($fields, $params) as $field => $value)
					if (is_null($value) || $value === '') unset($fields[$field]);
			
			$fieldSets[$fields['id']] = $fieldSet = new CRM_API_FieldSet(static::filterFields($fields));
			
			// Check that the returned values match the supplied parameters.
			if ($checkFieldsMatchParams) static::assertFieldsMatchParams($filteredParams, $fieldSet->fields + array('id' => $fields['id']));
		}
		
		return $fieldSets;
	}
	
	// Execute a basic 'create' action without trying to use BAO methods.
	protected static function callApiBasicCreate($params, $entity = NULL) {
		$hook = array_key_exists('id', $params) ? 'create' : 'edit';
		
		CRM_Utils_Hook::pre($hook, static::$properties->entityType, array_key_exists('id', $params) ? $params['id'] : NULL, $params);
		$daoClass = static::$properties->daoClass;
		$dao = new $daoClass();
		$dao->copyValues($params);
		$dao->save();
		CRM_Utils_Hook::post($hook, static::$properties->entityType, $dao->id, $dao);
		
		$fields = array();
		_civicrm_api3_object_to_array($dao, $fields);
		return civicrm_api3_create_success(array($dao->id => $fields), $params, $entity, 'create', $dao);
	}
	
	// Call CiviCRM's native API expecting a single entity to be returned.
	protected static function callApiSingle($action, $params, $required = TRUE) {
		$fieldSets = static::callApi($action, $params);
		if (count($fieldSets) !== 1) {
			if (!$required && !$fieldSets) return NULL;
			throw new Exception(E::ts('API call to %1 %2 %3 returned %4 values instead of the expected one', array(1 => $action, 2 => static::$properties->entityType, 3 => CRM_API_Utils::toString($params), 4 => count($entities))));
		}
		return reset($fieldSets);
	}
	
	// Convert parameters into the form required by CiviCRM's native API.
	protected static function serialiseParams($params) {
		// Convert boolean parameters to 0 or 1.
		foreach (static::$properties->fieldsByType['bool'] as $field)
			if (isset($params[$field]))
				$params[$field] = $params[$field] ? 1 : 0;
		
		// Convert DateTime parameters to strings.
		foreach (static::$properties->fieldsByType['DateTime'] as $field)
			if (isset($params[$field]))
				$params[$field] = $params[$field]->format('YmdHis');
		
		return $params;
	}
	
	// Filter a set of API parameters to use for checking the returned fields.
	protected static function filterParamsForMatching($params) {
		foreach ($params as $param => &$value) {
			// Ignore options, API chaining parameters and fields known to be unreliable.
			if (
				$param === 'options' ||
				strncmp($param, 'api.', 4) === 0 ||
				array_key_exists($param, static::$properties->fieldsMayNotMatchApiParams) ||
				strncmp($param, 'custom_', 7) === 0 ||
				is_null($value) || $value === ''
			) {
				unset($params[$param]);
				continue;
			}
			
			// If this parameter is an array then sort it for comparison.
			if (is_array($value)) sort($value);
		}
		
		return $params;
	}
	
	// Check that the fields returned by an API call match the parameters passed to the API.
	protected static function assertFieldsMatchParams($params, $fields) {
		// Check for missing fields.
		$missingFields = array_diff_key($params, $fields);
		if ($missingFields)
			throw new Exception(E::ts('Error in %1 API call: Field(s) %2 not present in results', array(1 => static::$properties->entityType, 2 => CRM_API_Utils::toString($missingFields))));
		
		$unequalFieldsSupplied = array();
		$unequalFieldsReturned = array();
		foreach ($params as $param => $suppliedValue) {
			$returnedValue = $fields[$param];
			
			// If this field is an array then sort it for comparison.
			if (is_array($returnedValue)) sort($returnedValue);
			
			// Does the returned field have a different value to the supplied parameter?
			if (
				is_string($suppliedValue) && is_string($returnedValue) && !CRM_Core_DAO::singleValueQuery('SELECT %1 LIKE %2', array(1 => array(trim($returnedValue), 'String'), 2 => array(trim($suppliedValue), 'String'))) ||
				(!is_string($suppliedValue) || !is_string($returnedValue)) && $returnedValue != $suppliedValue &&
				!(in_array($param, static::$properties->fieldsByType['array']) && !is_array($suppliedValue) && is_array($returnedValue) && in_array($suppliedValue, $returnedValue))
			) {
				$unequalFieldsSupplied[$param] = $suppliedValue;
				$unequalFieldsReturned[$param] = $returnedValue;
			}
		}
		
		if ($unequalFieldsSupplied)
			throw new Exception(E::ts('Error in %1 API call: Values of field(s) returned %2 did not match values of parameter(s) supplied %3', array(1 => static::$properties->entityType, 2 => CRM_API_Utils::toString($unequalFieldsReturned), 3 => CRM_API_Utils::toString($unequalFieldsSupplied))));
	}
	
	// Filter and normalise fields retrieved from the data access layer.
	protected static function filterFields($rawFields) {
		$fields = array();
		$misnamedCustomFields = array();
		foreach ($rawFields as $field => $value) {
			// Replace 'null' strings with NULL values.
			if (is_string($value) && strcasecmp($value, 'null') === 0) $value = NULL;
			
			if (preg_match('/^custom_(\d+)(?:_(\w+))?$/u', $field, $matches)) {
				// Some API functions return incorrectly named custom fields.
				if (array_key_exists(2, $matches) && $matches[2] === 'id') {
					$misnamedCustomFields['custom_' . $matches[1]] = $value;
					continue;
				}
			} elseif (!static::isValidField($field)) {
				// Ignore fields that are not properties of the entity.
				if (preg_match('/^' . preg_quote(static::$properties->fieldPrefix) . '(\w+)$/u', $field, $matches)) {
					$field = $matches[1];
					if (!static::isValidField($field)) continue;
				} else {
					continue;
				}
			}
			
			// Ensure that array fields have array values by replacing empty strings with empty arrays and wrapping single values in arrays.
			if (in_array($field, static::$properties->fieldsByType['array'])) {
				if ($value === '')
					$value = array();
				elseif (!is_array($value))
					$value = array($value);
			}
			
			$fields[$field] = $value;
		}
		
		return static::normaliseFields($misnamedCustomFields + $fields);
	}
	
	// Convert field values to a standard internal representation.
	protected static function normaliseFields($fields, $get = FALSE) {
		// Ensure that text fields have string values.
		foreach (static::$properties->fieldsByType['string'] as $field) {
			if (isset($fields[$field]) && !is_string($fields[$field])) {
				$value =& $fields[$field];
				if (is_int($value) || is_float($value))
					$value = (string)$value;
				elseif (!array_key_exists($field, static::$properties->fieldsMayNotMatchApiParams))
					throw new Exception(E::ts('Invalid value %1 for string field %2', array(1 => CRM_API_Utils::toString($value), $field)));
			}
		}
		
		// Ensure that integer fields have int values.
		foreach (static::$properties->fieldsByType['int'] as $field) {
			if (isset($fields[$field]) && !is_int($fields[$field])) {
				$value =& $fields[$field];
				if ($value === '')
					$value = NULL;
				elseif (CRM_API_Utils::isSignedIntString($value))
					$value = (int)$value;
				else
					throw new Exception(E::ts('Invalid value %1 for integer field %2', array(1 => CRM_API_Utils::toString($value), $field)));
			}
		}
		
		// Ensure that boolean fields have boolean values.
		foreach (static::$properties->fieldsByType['bool'] as $field) {
			if (isset($fields[$field]) && !is_bool($fields[$field])) {
				$value =& $fields[$field];
				if ($value === '')
					unset($fields[$field]);
				elseif ($value === 0 || $value === '0')
					$value = FALSE;
				elseif ($value === 1 || $value === '1')
					$value = TRUE;
				else
					throw new Exception(E::ts('Invalid value %1 for boolean field %2', array(1 => CRM_API_Utils::toString($value), $field)));
			}
		}
		
		// Ensure that real number fields have float values.
		foreach (static::$properties->fieldsByType['float'] as $field) {
			if (isset($fields[$field]) && !is_float($fields[$field])) {
				$value =& $fields[$field];
				if ($value === '')
					$value = NULL;
				elseif (is_int($value) || is_string($value) && is_numeric($value))
					$value = (float)$value;
				else
					throw new Exception(E::ts('Invalid value %1 for real number field %2', array(1 => CRM_API_Utils::toString($value), $field)));
			}
		}
		
		// Ensure that date/time fields are DateTime objects.
		foreach (static::$properties->fieldsByType['DateTime'] as $field) {
			if (isset($fields[$field]) && !is_a($fields[$field], 'DateTime')) {
				$value =& $fields[$field];
				if ($value === '')
					$value = NULL;
				elseif (is_string($value))
					$value = new DateTime($value);
				else
					throw new Exception(E::ts('Invalid value %1 for DateTime field %2', array(1 => CRM_API_Utils::toString($value), $field)));
			}
		}
		
		// Ensure that array fields have array values
		// (unless the fields are parameters for a 'get' action, in which case single values may be supplied).
		if (!$get) {
			foreach (static::$properties->fieldsByType['array'] as $field) {
				if (isset($fields[$field]) && !is_array($fields[$field])) {
					$value =& $fields[$field];
					throw new Exception(E::ts('Invalid value %1 for array field %2', array(1 => CRM_API_Utils::toString($value), $field)));
				}
			}
		}
		
		return $fields;
	}
	
	// Does the named field belong to this entity type as an intrinsic or extrinsic field?
	protected static function isValidField($field) {
		return array_key_exists($field, static::$properties->validFields);
	}
	
	// Check that an array of parameters does not contain an ID.
	protected static function assertIdNotSupplied($params) {
		if (array_key_exists('id', $params))
			throw new Exception(E::ts('Parameters %1 should not contain an ID', array(1 => CRM_API_Utils::toString($params))));
	}
	
	// Check that the supplied set of fields contains the fields required for the type of entity.
	protected static function assertRequiredFields($fields, $requiredFields) {
		$missingFields = array_keys(array_diff_key(array_fill_keys($requiredFields, NULL), $fields));
		if ($missingFields)
			throw new Exception(E::ts('Required field(s) %1 are missing for %2', array(1 => implode(', ', $missingFields), 2 => static::$properties->entityType)));
	}
	
	// Derive the value under which an entity is cached in a particular lookup.
	protected static function lookupValue($lookupKey, $fields) {
		$values = array();
		foreach (static::$properties->lookupFields[$lookupKey] as $lookupField => $null) {
			if (!isset($fields[$lookupField])) return NULL;
			$value = $fields[$lookupField];
			if (is_string($value)) {
				if ($value === '') return NULL;
				$value = mb_strtolower($value);
			}
			$values[] = $value;
		}
		return implode(chr(31), $values);
	}
	
	// Set up and register parent-child relationships.
	protected static function addParentRelationship($parentRelationshipName, $childRelationshipName, $childRelationshipPluralName, $parentClass, $parentIdField, $intLookupField = NULL, $stringLookupField = NULL, $extraLookupFields = array(), $parentDbTableField = NULL) {
		$relationship = new CRM_API_ParentChildRelationship($parentClass, get_called_class(), $parentIdField, $intLookupField, $stringLookupField, $extraLookupFields, $parentDbTableField);
		static::$properties->parentRelationships[$parentRelationshipName] = $relationship;
		$parentClass::$properties->childRelationships[$childRelationshipName] = $relationship;
		$parentClass::$properties->childRelationshipsPlural[$childRelationshipPluralName] = $relationship;
		
		// If lookup fields are not returned by API get, they should be assumed to be NULL.
		static::$properties->fillInFields += array_fill_keys(array_merge(array_filter(array($intLookupField, $stringLookupField, $parentIdField, $parentDbTableField)), $extraLookupFields), NULL);
	}
	
	// Set up the characteristics of a particular entity type.
	public static function init() {
		if (is_null(static::$properties)) {
			static::initProperties();
			self::$childClasses[] = get_called_class();
		}
	}
}

?>
