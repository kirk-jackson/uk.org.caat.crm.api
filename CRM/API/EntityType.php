<?php

// This class defines the properties and holds the cache for each entity type
class CRM_API_EntityType {
	public $entityType; // The type of entity
	public $daoClass; // BAO/DAO class
	public $dbTable; // The name of the database table that stores entities of this type
	
	// Lists of fields
	public $fieldsByType = array(
		'string' => array(),
		'int' => array(),
		'bool' => array(),
		'float' => array(),
		'DateTime' => array(),
		'array' => array()
	); // Field names grouped by data type
	public $displayFields; // The fields used in a string representation of the object
	public $fieldsMayNotMatchApiParams; // Fields that API functions may return with a different value (due to side-effects) or not return at all
	public $paramsRequiredByCreate; // The parameters that must be supplied to create an entity in the database
	public $readOnlyFields; // Fields that cannot be set using the API.
	public $validFields; // The fields that objects of this type may possess.
	public $persistFields; // Fields that are re-supplied when using API create to update an object
	public $fillInFields = array(); // Fields that should be set to NULL when not returned by the API
	
	public $canUndelete; // True if entities of this type can be undeleted, i.e. restored from the trash
	public $createReturnsFields; // False if CiviCRM's API create action does not return the new entity's fields
	public $baoCreateUpdates; // False if the BAO's create function can't be used to update
	public $fieldPrefix; // A string that the API may "helpfully" prepend to names of fields it returns
	
	// The cache consists of several data structures, including look-ups, parent-child relationships and tag IDs.
	// Look-ups are part of the cache in which objects are indexed under unique keys, each one comprised of one or more fields.
	public $lookups = array(); // The actual look-ups, in the form $lookups[$lookupKey][$lookupValue] = $entity
	public $lookupFields = array(); // A map from each look-up key to the fields it's comprised of
	public $defaultStringLookup; // The field used to retrieve a cached object if only a string is supplied
	public $allCached = FALSE; // True iff all entities of this type are currently cached
	// Parent-child relationships are part of the cache.
	public $parentRelationships = array(); // The relationships in which this entity type is a child
	public $childRelationships = array(); // The relationships in which this entity type is a parent
	public $childRelationshipsPlural = array(); // The relationships in which this entity type is a parent
	public $autoloadChildren; // Whether or not any child entities will be automatically got and cached when an entity of this type is cached
	// For taggable entities, tag IDs are an extra part of the cache.
	public $tagIdCache = array(); // An array of the entity's tag IDs
	// Cache settings
	public $cacheByDefault = TRUE; // Determines whether entities of this type are automatically cached
	
	public function __construct($properties = array()) {
		$defaultProperties = array(
			'lookups' => array(),
			'defaultStringLookup' => NULL,
			'fieldsByType' => array(),
			'displayFields' => array(),
			'dbTablePrefix' => 'civicrm',
			'fieldsMayNotMatchApiParams' => array(),
			'paramsRequiredByCreate' => array(),
			'readOnlyFields' => array(),
			'autoloadChildren' => FALSE,
			'createReturnsFields' => TRUE,
			'baoCreateUpdates' => TRUE,
			'canUndelete' => FALSE
		);
		if (array_diff_key($properties, $defaultProperties))
			throw new Exception(ts('Invalid properties: %1', array(1 => implode(', ', array_keys(array_diff_key($properties, $defaultProperties))))));
		$properties += $defaultProperties;
		
		// Derive the entity type from the name of the calling class.
		$backtrace = debug_backtrace();
		$class = $backtrace[1]['class'];
		if (!is_subclass_of($class, 'CRM_API_Entity') || !preg_match('/^CRM_((?:[A-Za-z0-9]+_)*)API_([A-Za-z0-9]+)$/u', $class, $matches))
			throw new Exception(ts('Class %1 does not represent a valid API entity type', array(1 => $class)));
		$this->entityType = $matches[2];
		
		// Determine the database table.
		$lcEntityType = mb_strtolower(preg_replace('/(?<=[A-Za-z])(?=[A-Z][a-z])|(?<=[a-z])(?=[A-Z])/u', '_', $this->entityType));
		$this->dbTable = $properties['dbTablePrefix'] . '_' . $lcEntityType;
		$this->fieldPrefix = $lcEntityType . '_';
		
		// Determine the BAO/DAO class.
		$daoClass = CRM_Core_DAO_AllCoreTables::getClassForTable($this->dbTable);
		if ($daoClass) {
			// If the entity has a BAO, use that instead of the DAO.
			$baoClass = str_replace("_DAO_", "_BAO_", $daoClass);
			$this->daoClass = stream_resolve_include_path(strtr($baoClass, '_', '/') . '.php') ? $baoClass : $daoClass;
		} else {
			if ($matches[1]) {
				foreach (array('BAO', 'DAO') as $daoType) {
					$daoClass = 'CRM_' . $matches[1] . $daoType . '_' . $matches[2];
					if (stream_resolve_include_path(strtr($daoClass, '_', '/') . '.php')) {
						$this->daoClass = $daoClass;
						break;
					}
				}
			}
			if (!$this->daoClass)
				throw new Exception(ts('Cannot determine the BAO/DAO class for entity type %1', array(1 => $this->entityType)));
		}
		
		// Prepare the lookups.
		foreach (array_merge(array('id'), $properties['lookups']) as $lookupKey) {
			$this->lookups[$lookupKey] = array();
			$this->lookupFields[$lookupKey] = array_fill_keys(mb_split(',', $lookupKey), NULL);
		}
		
		// Group fields by type.
		if (array_diff_key($properties['fieldsByType'], $this->fieldsByType)) {
			throw new Exception(ts('Invalid data type(s): %1', array(1 => implode(', ', array_keys(array_diff_key($properties['fieldsByType'], $this->fieldsByType))))));
		}
		$explicitlyTypedFields = array_reduce($properties['fieldsByType'], function($fields, $fieldsOfType) {return $fields + array_fill_keys($fieldsOfType, NULL);}, array());
		
		// Map MySQL data types to PHP data types.
		$dbTypeMap = array();
		foreach (array(
			'string' => array('char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'enum', 'set'),
			'int' => array('smallint', 'mediumint', 'int', 'bigint'),
			'bool' => array('tinyint'),
			'float' => array('decimal', 'float', 'double'),
			'DateTime' => array('date', 'datetime', 'timestamp', 'time')
		) as $phpType => $mysqlTypes) {
			foreach ($mysqlTypes as $mysqlType) {
				$dbTypeMap[$mysqlType] = $phpType;
			}
		}
		
		$intrinsicFields = array();
		$dao = CRM_Core_DAO::executeQuery("
			SELECT COLUMN_NAME, DATA_TYPE
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %1
		", array(
			1 => array($this->dbTable, 'String')
		));
		while ($dao->fetch()) {
			$column = $dao->COLUMN_NAME;
			$intrinsicFields[$column] = NULL;
			
			if (array_key_exists($column, $explicitlyTypedFields)) continue; // No need to guess data type if it's been explicitly specified.
			
			// Infer the field's PHP data type from the corresponding database column's type.
			if (!array_key_exists($dao->DATA_TYPE, $dbTypeMap) || !array_key_exists($dbTypeMap[$dao->DATA_TYPE], $this->fieldsByType))
				throw new Exception(ts('Database table %1, column %2 has data type %3, which is not recognised', array(1 => $this->dbTable, 2 => $column, 3 => $dao->DATA_TYPE)));
			$this->fieldsByType[$dbTypeMap[$dao->DATA_TYPE]][] = $column;
		}
		$dao->free();
		
		$this->fieldsByType = array_merge_recursive($this->fieldsByType, $properties['fieldsByType']);
		$this->validFields = $intrinsicFields + $explicitlyTypedFields;
		
		// Check that special fields are actually valid fields.
		foreach (array('displayFields', 'fieldsMayNotMatchApiParams', 'paramsRequiredByCreate', 'readOnlyFields') as $property) {
			if (array_diff_key(array_fill_keys($properties[$property], NULL), $this->validFields))
				throw new Exception(ts('Unknown fields: %1', array(1 => implode(', ', array_diff($properties[$property], array_keys($this->validFields))))));
		}
		
		// Copy some properties directly to the object.
		foreach (array(
			'defaultStringLookup',
			'displayFields',
			'paramsRequiredByCreate',
			'autoloadChildren',
			'createReturnsFields',
			'baoCreateUpdates',
			'canUndelete'
		) as $property) {
			$this->$property = $properties[$property];
		}
		
		$this->readOnlyFields = array_fill_keys($properties['readOnlyFields'], NULL);
		
		// Include extrinsic fields in the list of fields that might not match the parameters passed to the API.
		$this->fieldsMayNotMatchApiParams = array_fill_keys($properties['fieldsMayNotMatchApiParams'], NULL) + array_diff_key($explicitlyTypedFields, $intrinsicFields);
		
		$this->persistFields = array_diff_key($intrinsicFields, $this->readOnlyFields, $this->fieldsMayNotMatchApiParams);
	}
}

?>
