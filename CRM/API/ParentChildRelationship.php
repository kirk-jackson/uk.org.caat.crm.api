<?php

class CRM_API_ParentChildRelationship {
	private $parentClass, $childClass; // The classes of the parent and child entities.
	private $parentIdField; // The field of a child entity that contains the ID of its parent entity.
	private $parentDbTableField; // The database table in which the parent entities are stored (optional).
	private $intLookupField, $stringLookupField; // Fields of child entities that can be used to look them up in the child cache.
	private $childCache = array(); // A cache of child entities, having the structure $childCache[$lookupField][$parentId][$lookupValue] = $child
	
	private static $readOnlyMembers = array('parentClass', 'childClass', 'parentIdField', 'parentDbTableField', 'intLookupField', 'stringLookupField');
	
	public function __construct($parentClass, $childClass, $parentIdField, $intLookupField, $stringLookupField, $extraLookupFields, $parentDbTableField) {
		$this->childClass = $childClass;
		$this->parentClass = $parentClass;
		$this->parentIdField = $parentIdField;
		$this->intLookupField = $intLookupField;
		$this->stringLookupField = $stringLookupField;
		$this->parentDbTableField = $parentDbTableField;
		
		$lookupFields = array('id');
		if (!is_null($intLookupField)) $lookupFields[] = $intLookupField; else $this->intLookupField = 'id';
		if (!is_null($stringLookupField)) $lookupFields[] = $stringLookupField;
		$lookupFields = array_unique(array_merge($lookupFields, $extraLookupFields));
		
		// Initialise the child cache.
		foreach ($lookupFields as $lookupField) $this->childCache[$lookupField] = array();
	}
	
	public function __get($member) {
		if (in_array($member, self::$readOnlyMembers)) return $this->$member;
		throw new Exception(ts('Parent-child relationship does not have a member variable called "%1"', array(1 => $member)));
	}
	
	public function __isset($member) {
		if (in_array($member, self::$readOnlyMembers)) return isset($this->$member);
	}
	
	public function hasLookupField($field) {
		return array_key_exists($field, $this->childCache);
	}
	
	// Create an entry in the cache for the specified parent entity, over-writing any previous data.
	public function cacheParent($parentId) {
		if (!$parentId) throw new Exception(ts('Attempt to cache a non-existent parent entity'));
		foreach ($this->childCache as &$lookup) $lookup[$parentId] = array();
	}
	
	// Delete an entry in the cache for the specified parent entity.
	public function uncacheParent($parentId) {
		foreach ($this->childCache as &$lookup) unset($lookup[$parentId]);
	}
	
	public function isParentCached($parentId) {
		return array_key_exists($parentId, $this->childCache['id']);
	}
	
	public function cacheChild($child) {
		$this->assertIsChildEntity($child);
		$parentIdField = $this->parentIdField;
		$parentId = $child->$parentIdField;
		$this->assertParentCached($parentId);
		
		// If children are ordered by weight, then find the position to insert the child.
		if (isset($child->weight)) {
			$insertPosition = 0;
			foreach ($this->childCache['id'][$parentId] as $aChild) {
				if (isset($aChild->weight) && $aChild->weight >= $child->weight)
					break;
				++$insertPosition;
			}
		}
		
		foreach ($this->childCache as $lookupField => &$lookup) {
			if (isset($child->$lookupField)) {
				$lookupValue = $child->$lookupField;
				if ($lookupValue !== '') {
					if (is_string($lookupValue)) $lookupValue = mb_strtolower($lookupValue);
					if (array_key_exists($lookupValue, $lookup[$parentId]))
						throw new Exception(ts('%1 %2 has more than one %3 with a %4 of %5', array(1 => $this->parentClass, 2 => $parentId, 3 => $this->childClass, 4 => $lookupField, 5 => $lookupValue)));
					
					if (isset($insertPosition)) {
						// Insert child in correct position so as to maintain ordering.
						$lookup[$parentId] =
							array_slice($lookup[$parentId], 0, $insertPosition, TRUE) +
							array($lookupValue => $child) +
							array_slice($lookup[$parentId], $insertPosition, NULL, TRUE);
					} else {
						$lookup[$parentId][$lookupValue] = $child;
					}
				}
			}
		}
	}
	
	public function uncacheChild($child) {
		$this->assertIsChildEntity($child);
		$parentIdField = $this->parentIdField;
		$parentId = $child->$parentIdField;
		$this->assertParentCached($parentId);
		foreach ($this->childCache as $lookupField => &$lookup) {
			if (isset($child->$lookupField)) {
				$lookupValue = $child->$lookupField;
				if ($lookupValue !== '') {
					if (is_string($lookupValue)) $lookupValue = mb_strtolower($lookupValue);
					if (!array_key_exists($lookupValue, $lookup[$parentId]))
						throw new Exception(ts('%1 %2 does not have a %3 with a %4 of %5', array(1 => $this->parentClass, 2 => $parentId, 3 => $this->childClass, 4 => $lookupField, 5 => $lookupValue)));
					unset($lookup[$parentId][$lookupValue]);
				}
			}
		}
	}
	
	public function isChildsParentCached($child) {
		$this->assertIsChildEntity($child);
		$parentIdField = $this->parentIdField;
		return $this->isParentCached($child->$parentIdField);
	}
	
	// Returns a collection of all of a parent entity's children.
	public function getChildren($parentId) {
		$this->assertParentCached($parentId);
		return $this->childCache['id'][$parentId];
	}
	
	// Returns one of a parent entity's children.
	public function getChild($parentId, $field, $value, $required = TRUE) {
		if (func_num_args() === 3 && is_bool($field)) {$required = $field; $field = NULL;}
		$this->assertParentCached($parentId);
		if (is_null($value) || $value === '')
			throw new Exception(ts('Cannot use a null/empty value to look up a child %1', array(1 => $this->childClass)));
		$field = $this->resolveLookupField($value, $field);
		if (is_string($value)) $value = mb_strtolower($value);
		if (!array_key_exists($value, $this->childCache[$field][$parentId])) {
			if ($required)
				throw new Exception(ts('%1 %2 does not have a %3 with %4 of "%5"', array(1 => $this->parentClass, 2 => $parentId, 3 => $this->childClass, 4 => $field, 5 => $value)));
			return NULL;
		}
		return $this->childCache[$field][$parentId][$value];
	}
	
	private function resolveLookupField($value, $field = NULL) {
		if (is_null($field))
			if (!is_null($this->intLookupField) && is_int($value))
				$field = $this->intLookupField;
			elseif (!is_null($this->stringLookupField) && is_string($value))
				$field = $this->stringLookupField;
			else
				throw new Exception(ts('There is no default lookup that can be used with the value %1', array(1 => $value)));
		else
			$this->assertLookupField($field);
		return $field;
	}
	
	private function assertIsChildEntity($child) {
		if (!is_a($child, $this->childClass))
			throw new Exception(ts('%1 is not %2', array(1 => $child, 2 => $this->childClass)));
	}
	
	private function assertLookupField($field) {
		if (!$this->hasLookupField($field))
			throw new Exception(ts('Child %1 cache is not indexed by "%2"', array(1 => $this->childClass, 2 => $field)));
	}
	
	private function assertParentCached($parentId) {
		if (!$this->isParentCached($parentId))
			throw new Exception(ts('%1 %2 is not in relationship cache', array(1 => $this->parentClass, 2 => $parentId)));
	}
}

?>
