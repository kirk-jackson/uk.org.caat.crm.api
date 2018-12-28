# Object-Oriented API

Object-Oriented API (OOAPI) is an extension for CiviCRM that provides an object-oriented wrapper for the native APIv3.

Its features include:

* Object-oriented syntax
* More concise than the native APIv3
* Automatic caching to minimise database calls
* An interface that encourages name-based look-ups
* Strict error-checking
* Modelling of parent-child relationships between entities
* Hides some of the native APIv3's bugs and inconsistencies
* Additional features, e.g. the ability to record a contact being added to a group at a specific date
* Provides API for BAOs & DAOs not available in the native APIv3
* Easily extensible to new BAOs & DAOs

#### Example
```php
// If the contact has a London address, add them to the newsletter.
$contact = CRM_API_Contact::getSingle($contactId);
foreach ($contact->getAddresses() as $address) {
    if ($address->city === 'London')
        $contact->updateGroupStatus('Newsletter', 'Added', '2014-02-02');
}
```

Note that it is helpful to have some familiarity with CiviCRM's native API in order to understand how this extension works.

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v7.0+
* CiviCRM v5.7

## Installation (Web UI)

This extension has not yet been published for installation via the web UI.

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl uk.org.caat.crm.api@https://bitbucket.org/caatuk/uk.org.caat.crm.api/get/3df7e898b4c5.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://bitbucket.org/caatuk/uk.org.caat.crm.api.git
cv en api
```

## Usage

### Class names
Each type of entity has its own class.
The name of each class is the native API's entity name prefixed by 'CRM_API_',
e.g. CRM_API_Contact, CRM_API_Activity, CRM_API_ContributionSoft, etc.

Note: The EntityTag, GroupContact and CustomValue entity types from the native APIv3
do not have corresponding classes in OOAPI.
Instead, their functionality is provided by methods of classes that can have tags, groups and custom fields (see below).

### Create an entity
```php
public static function create($params, $cache = NULL)
```
#### Parameters
* **params**: An array of fields and values for the new entity
* **cache**: TRUE to cache the entity in memory for quicker retrieval; FALSE to not cache;
  omit for the class's default cache setting

#### Returns
An OOAPI object representing the newly created entity.

#### Example
```php
$contact = CRM_API_Contact::create([
	contact_type => 'Individual',
	first_name => 'Mikhail',
	last_name => 'Bakunin'
]);
```

### Update an entity
```php
public function update($params, $always = TRUE)
```

#### Parameters
* **params**: An array of fields and their new values
* **always**: FALSE to only write to the database if the cached fields have changed

#### Example
```php
$contact->update([
	'birth_date' => '1814-05-30',
	'is_deceased' => TRUE
]);
```

### Delete an entity
```php
public function delete($permanent = TRUE)
```

#### Parameters
* **permanent**: FALSE if the entity is to be moved to the trash (available for Contacts only)

#### Example
```php
$contact->delete(FALSE);
```

### Look up entities
```php
public static function get($params = [], $cache = NULL, $readFromCache = TRUE)
```

#### Parameters
* **params**: An array of fields and values to look up matching entities.
  May include an **options** array in order to specify sort order or limit the number of entities returned.
* **cache**: TRUE to cache retrieved entities in memory for quicker retrieval; FALSE to not cache;
  NULL for the class's default cache setting
* **readFromCache**: FALSE to ignore the cache and look up entities in the database

#### Returns
An array of OOAPI objects representing any matching entities.

Whereas the native API limits the number of records returned by default, OOAPI does not.
A limit may be specified by including an **options** array in the **params**, as for the native APIv3.

#### Example
```php
$contacts = CRM_API_Contact::get(['last_name' => 'Bakunin']);
```

### Look up a single entity
```php
public static function getSingle($params = [], $required = TRUE, $cache = NULL, $readFromCache = TRUE)
```

#### Parameters
* **params**: An array of fields and values **or** an integer ID **or**
  a value of the class's default string look-up field (if there is one) with which to uniquely identify an entity
* **required**: TRUE to throw an exception if there isn't exactly one matching entity;
  FALSE to return NULL if no matching entity is found
* **cache**: TRUE to cache retrieved entities in memory for quicker retrieval; FALSE to not cache;
  NULL for the class's default cache setting
* **readFromCache**: FALSE to ignore the cache and look up entities in the database

#### Returns
An OOAPI object representing the matching entity, or NULL if there is no matching entity

#### Example
```php
$contact = CRM_API_Contact::getSingle($contactId);
```

### Reload an entity from the database
```php
public function refresh()
```
If an entity has been updated in the database without using OOAPI then
any OOAPI objects referring to the entity may be out of sync.
This function reloads an object's fields from the database.

#### Example
```php
$contact->refresh();
```

### Check if an entity has been deleted
```php
public function isDeleted()
```

#### Returns
TRUE if the object represents an entity that no longer exists; FALSE otherwise

#### Example
```php
if ($address->isDeleted()) ...
```

### Convert a BAO/DAO
```php
public static function getObject($fields, $cache = NULL)
```
This method constructs an OOAPI object from a BAO, a DAO or an array of fields.
It is particularly useful in the **post** hook.

#### Parameters
* **fields**: A BAO, DAO or array of fields with which to initialise the object
* **cache**: TRUE to cache the entity in memory for quicker retrieval; FALSE to not cache;
  omit for the class's default cache setting

#### Returns
An OOAPI object with the specified field values

#### Example
```php
function myextension_civicrm_post($op, $objectName, $objectId, &$objectRef) {
	if ($objectName === 'Individual' && $op === 'edit') {
		$contact = CRM_API_Contact::getObject($objectRef);
        ...
```

### Get objects from an SQL query
```php
public static function getFromQuery($query, $params = [], $cache = NULL)
```

#### Parameters
* **query**: An SQL query, which must return all the entity's fields including **id**
* **params**: The values for any variables in the query (in the same format as for CRM_Core_DAO::executeQuery)
* **cache**: TRUE to cache the entity in memory for quicker retrieval; FALSE to not cache;
  omit for the class's default cache setting

#### Returns
An array of OOAPI objects representing all entities selected by the query.

#### Example
```php
$emails = CRM_API_Email::getFromQuery("
    SELECT * FROM civicrm_email
    WHERE email NOT REGEXP '[[:alnum:]\\._-]+@[[:alnum:]\\._-]+'
");
```

## Field values
An entity's fields can be accessed as PHP properties. For example:
```php
if (isset($contact->first_name))
    $firstName = $contact->first_name;
```

Field values are returned using appropriate PHP types.
String fields are returned as PHP strings, integer fields are returned as PHP integers,
floating point fields are returned as PHP floats, boolean fields are returned as PHP booleans,
array fields are returned as arrays, and date/time fields are returned as DateTime objects.
They may also be set using the same types.

This allows the fields to be operated on intuitively withour having to convert them first.
It also allows for function overloading,
e.g. where a function does one thing if passed an integer ID value or another thing if passed a string.

(By comparison, the native APIv3 returns many fields as strings when they are not actually strings.)

### Get all of an entity's fields
```php
public function fields()
```

#### Returns
an array containing all of the entity's field names and values

#### Example
```php
foreach ($contact->fields() as $fieldName => $fieldValue) ...
```

## Custom Fields
Custom field values can be accessed using the custom field name.

```php
$contact = CRM_API_Contact::create([
	'contact_type' => 'Individual',
	'first_name' => 'Mikhail',
	'last_name' => 'Bakunin',
	'Favourite_Colour' => 'Black'
]);
```

Custom fields can also be set individually (unlike ordinary fields, which are read-only and can only be set using the update() method):
```php
$contact->Favourite_Colour = 'red';
```

Custom field sets that allow multiple records are slightly different. The values are returned as arrays:
```php
foreach ($contact->Job_Titles as $recordId => $jobTitle) ...
```
But they cannot be set using the same notation.
Instead, each field has its own **add** and **update** methods.
In the methods below, replace CUSTOMFIELD with the name of the custom field.

### Add custom values
```php
public function addCUSTOMFIELD($values)
```

#### Parameters
* **values**: An array of values to be added to the custom field.

#### Example
```php
$contact->addQualifications(['Computer Science BSc', 'Fine Art MA']);
```

### Update custom values
```php
public function updateCUSTOMFIELD($values)
```

#### Parameters
* **values**: An array mapping record IDs to new field values

#### Example
```php
$contact->updateQualifications([$recordId => 'Computer Science BEng']);
```

### Get a custom field's option group
Custom fields whose values are selected from a pre-defined list using a Select or Radio control
have an associated option group that stores the possible values.
This function returns the option group used by such a custom field.
```php
public function getOptionGroup()
```

#### Returns
A CRM_API_OptionGroup object representing the custom field's option group

#### Example
```php
$colourOptionGroup = $colourCustomField->getOptionGroup();
```

## Tags
### Add a tag
```php
public function tag($tag)
```

#### Parameters
* **tag**: A tag object, ID or name

#### Example
```php
$contact->tag('Major Donor');
```

### Remove a tag
```php
public function untag($tag)
```

#### Parameters
* **tag**: A tag object, ID or name

#### Example
```php
$contact->untag('Major Donor');
```

### Check if an entity has a tag
```php
public function hasTag($tag)
```

#### Parameters
* **tag**: A tag object, ID or name

#### Example
```php
if ($contact->hasTag('Major Donor')) ...
```

## Groups
### Add to or remove from a group
```php
public function updateGroupStatus($group, $status, $dateTime = NULL)
```
#### Parameters
* **group**: A group object, ID or name
* **status**: One of 'Added', 'Removed' or 'Pending'
* **dateTime**: A DateTime object or string specifying the date of the status update (defaults to the current date)

#### Example
```php
$contact->updateGroupStatus('Newsletter', 'Added', '2010-11-18');
```

## Parent-child relationships
OOAPI models parent-child relationships between entities.
For each relationship there are methods for parent entities to access their children
and for child entities to access their parents.
The names of these methods depend on the type of the parent/child entities being accessed,
as shown in the table below.

Parent entity | Child entity | Child->parent methods | Parent->child methods
-|-|-|-
Contact | Address | Contact | Address(es)
Contact | Email | Contact | Email(s)
Contact | Phone | Contact | Phone(s)
Contact | Note | Contact | Note(s)
Contact | Contribution | Contact | Contribution(s)
Contact | ContributionSoft | Contact | SoftCredit(s)
Contact | ContributionRecur | Contact | RecurringContribution(s)
Contact | Participant | Contact | Participant(s)
Contribution | ContributionSoft | Contribution | SoftCredit(s)
Mailing | MailingGroup | Mailing | Group(s)
Mailing | MailingRecipient | Mailing | Recipient(s)
Mailing | MailingJob | Mailing | Job(s)
MailingJob | MailingEventQueue | Job | EventQueue(s)
MailingEventQueue | MailingEventDelivered | Queue | Delivery / Deliveries
CustomGroup | CustomField | Group | Field(s)
OptionGroup | OptionValue | Group | Value(s)
Country | StateProvince | Country | StateProvince(s)

In the methods listed below, replace the words PARENT and CHILD(REN)
with the names from columns three and four of the table above,
e.g. getCHILDREN() becomes getAddresses() or getEmails(), and so on.

### Look up a single child
```php
public function getCHILD($field = NULL, $value, $required = TRUE)
```

#### Parameters
* **field**: The field used to look up the child entity
  (May be omitted to use the relationship's default string look-up field or default integer look-up field,
  depending on **value**.)
* **value**: Uniquely identifies the child entity
* **required**: TRUE to throw an exception if there isn't exactly one matching child entity;
  FALSE to return NULL if no matching child entity is found

#### Returns
An OOAPI object representing the matching child entity, or NULL if there is no matching child entity

#### Example
```php
$webTypeOptionGroup = CRM_API_OptionGroup::getSingle('website_type');
$webTypeOptionValue = $webTypeOptionGroup->getValue('value', $websiteTypeId);
```

### Look up all children
```php
public function getCHILDREN()
```

#### Returns
An array of OOAPI objects representing all the entity's children.

#### Example
```php
foreach ($optionGroup->getValues() as $optionValue) ...
```

### Look up the parent
```php
public function getPARENT()
```

#### Returns
An OOAPI object representing the parent entity

#### Example
```php
$optionGroup = $optionValue->getGroup();
```

### Add a child
```php
public function createCHILD($params, $cache = NULL)
```

#### Parameters
* **params**: An array of fields and values for the new child entity
* **cache**: TRUE to cache the entity in memory for quicker retrieval; FALSE to not cache;
  omit for the class's default cache setting

#### Returns
An OOAPI object representing the newly created child entity.

#### Example
```php
$email = $contact->createEmail(['email' => 'b.durruti@riseup.net']);
```

### Update a child
```php
public function updateCHILD($field = NULL, $value, $params)
```

#### Parameters
* **field**: The field used to look up the child entity
  (May be omitted to use the relationship's default string look-up field or default integer look-up field,
  depending on **value**.)
* **value**: Uniquely Identifies the child to be updated
* **params**: An array of fields and their new values

#### Example
```php
$prefixOptionGroup = CRM_API_OptionGroup::getSingle('individual_prefix');
$prefixOptionGroup->updateValue('Dr.', ['label' => 'Dr']);
```

### Delete a child
```php
public function deleteCHILD($field = NULL, $value)
```

#### Parameters
* **field**: The field used to look up the child entity
  (May be omitted to use the relationship's default string look-up field or default integer look-up field,
  depending on **value**.)
* **value**: Uniquely Identifies the child to be deleted

#### Example
```php
$prefixOptionGroup = CRM_API_OptionGroup::getSingle('individual_prefix');
$prefixOptionGroup->deleteValue('Lord');
```

### Delete all children
```php
public function deleteCHILDREN()
```

#### Example
```php
$contact->deleteAddresses();
```

## Name-based look-ups
OOAPI facilitates the use of names to identify entities.
For example:
```php
$activityTypeOptionGroup = CRM_API_OptionGroup::getSingle('activity_type');
$activityTypeId = $activityTypeOptionGroup->getValue('Meeting')->value;
```
This makes the code easier to read than using hard-coded ID numbers.
(It is bad practice to use literal ID numbers in code.)

## Error handling
When any error is encountered, an exception is thrown.
These can be caught and handled in the calling code.
Converting the exception to a string will reveal the stack trace.

When CiviCRM is in debug mode,
OOAPI will check that fields returned by the native API match the parameters passed in,
and it will throw an exception if they do not.

## Caching
By default, entities are cached in memory when they are encountered.
This makes repeated look-ups more efficient.
However, if a large amount of data is being processed, caching may need to be managed
so that it doesn't take up too much memory.

Some methods accept a $cache argument that prevents the method from caching the entity.
There are some methods specifically for managing caching:

### Cache an entity
```php
public function cache()
```
Puts an entity in the cache (if it isn't there already).

#### Example
```php
$contact->cache();
```

### Uncache an entity
```php
public function uncache()
```
Removes an entity and its child entities from the cache.
(The object's field values are still accessible.)

#### Example
```php
$contact->uncache();
```

### Cache all entities
```php
public static function cacheAll()
```
Gets and caches all entities of a particular type

#### Example
```php
CRM_API_Country::cacheAll();
```

### Uncache all entities
```php
public static function uncacheAll()
```
Removes all entities of a particular type and their children from the cache

#### Example
```php
CRM_API_Activity::uncacheAll();
```

### Change default caching behaviour
```php
public static function setCacheByDefault($cacheByDefault)
```

#### Parameters
* **cacheByDefault**: TRUE to cache by default; FALSE to not cache by default

#### Example
```php
CRM_API_Activity::setCacheByDefault(FALSE);
```

### Get cache status
```php
public static function diagnostics()
```

#### Returns
The number of cached entities of each type

#### Example
```php
foreach (CRM_API_Entity::diagnostics() as $class => $numCached) ...
```

## Implementation
### Classes
All entity classes (e.g. CRM_API_Contact) are derived from the abstract base class CRM_API_Entity.
It is this class that does most of the work.

Entity classes that can have custom data are derived from CRM_API_ExtendableEntity.
Entity classes that can be tagged are derived from CRM_API_TaggableExtendableEntity.

### Adding new classes
It is fairly simple to add new OOAPI classes.
There must be a database table and a DAO or BAO for the entity type, both named according to CiviCRM convention.
An OOAPI class needs to be created to wrap the DAO/BAO.

For example, if the database table is called **my_extension_my_entity**
and the DAO class is **CRM_MyExtension_DAO_MyEntity**
then the OOAPI class should be defined as follows:
```php
class CRM_MyExtension_API_MyEntity extends CRM_API_Entity {
	protected static $properties;
	
	protected static function initProperties() {
		static::$properties = new CRM_API_EntityType([
			'displayFields' => ['my_label_field'],
			'dbTablePrefix' => 'my_extension'
		]);
	}
}

CRM_MyExtension_API_MyEntity::init();
```
The CRM_API_EntityType constructor takes a single argument - an array of optional properties:
* **defaultStringLookup**: The name of a field that the getSingle function will use if passed a string value
* **displayFields**: An array of names of fields that will be included in an entity's string representation
  (for diagnostic purposes)
* **dbTablePrefix**: The prefix of the underlying database table. (Defaults to 'civicrm'.)
* **fieldsByType**: Field names grouped by PHP data type.
  (Only necessary for fields whose type cannot be inferred from the underlying database table.)
* **lookups**: An array of names of fields that can be used to uniquely identify entities of this type.
  (Corresponds to unique keys in the underlying database table. Used for caching.)

### Dependencies
This extension is built to use the native APIv3's core actions as much as possible,
but it does also directly access a few core functions and database tables.
This means that if CiviCRM's underlying implementation changes, the extension may need to be rewritten.

## Known Issues
