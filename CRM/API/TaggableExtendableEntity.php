<?php

abstract class CRM_API_TaggableExtendableEntity extends CRM_API_ExtendableEntity {
	public function tag($tag) {
		static::tagId($this->id, $tag);
	}
	
	public function untag($tag) {
		static::untagId($this->id, $tag);
	}
	
	public function hasTag($tag) {
		$this->cacheTags();
		return array_key_exists(CRM_API_Tag::getId($tag), static::$properties->tagIdCache[$this->id]);
	}
	
	public function getTagIds() {
		$this->cacheTags();
		return array_keys(static::$properties->tagIdCache[$this->id]);
	}
	
	public function getTags() {
		$tags = array();
		foreach (array_keys($this->getTagIds()) as $tagId)
			$tags[$tagId] = CRM_API_Tag::getSingle($tagId);
		return $tags;
	}
	
	protected function cacheTags() {
		static::$properties->tagIdCache[$this->id] = array_fill_keys(static::getIdTagIds($this->id), NULL);
	}
	
	protected function uncacheTags() {
		unset(static::$properties->tagIdCache[$this->id]);
	}
	
	protected function uncacheObject($deleted = FALSE) {
		$this->uncacheTags();
		parent::uncacheObject($deleted);
	}
	
	public static function tagId($entityId, $tag) {
		$tagId = CRM_API_Tag::getId($tag);
		
		$apiResult = civicrm_api('EntityTag', 'create', array(
			'version' => '3',
			'entity_table' => static::$properties->dbTable,
			'entity_id' => $entityId,
			'tag_id' => $tagId
		));
		if (civicrm_error($apiResult))
			throw new CRM_API_Exception(ts('Failed to add tag %1 to %2 %3', array(1 => $tagId, 2 => static::$properties->entityType, 3 => $entityId)), $apiResult);
		
		if (array_key_exists($entityId, static::$properties->tagIdCache))
			static::$properties->tagIdCache[$entityId][$tagId] = NULL;
	}
	
	public static function untagId($entityId, $tag) {
		$tagId = CRM_API_Tag::getId($tag);
		
		$apiResult = civicrm_api('EntityTag', 'delete', array(
			'version' => '3',
			'entity_table' => static::$properties->dbTable,
			'entity_id' => $entityId,
			'tag_id' => $tagId
		));
		if (civicrm_error($apiResult))
			throw new CRM_API_Exception(ts('Failed to remove tag %1 from %2 %3', array(1 => $tagId, 2 => static::$properties->entityType, 3 => $entityId)), $apiResult);
		
		if (array_key_exists($entityId, static::$properties->tagIdCache))
			unset(static::$properties->tagIdCache[$entityId][$tagId]);
	}
	
	public static function idHasTag($contactId, $tag) {
		return in_array(CRM_API_Tag::getId($tag), static::getIdTagIds($contactId));
	}
	
	public static function getIdTagIds($contactId) {
		$apiResult = civicrm_api('EntityTag', 'get', array(
			'version' => '3',
			'entity_table' => static::$properties->dbTable,
			'contact_id' => $contactId
		));
		if (civicrm_error($apiResult))
			throw new CRM_API_Exception(ts('Failed to retrieve tags for contact %1', array(1 => $contactId)), $apiResult);
		
		$tagIds = array();
		foreach ($apiResult['values'] as $fields) $tagIds[] = $fields['tag_id'];
		return $tagIds;
	}
}

?>
