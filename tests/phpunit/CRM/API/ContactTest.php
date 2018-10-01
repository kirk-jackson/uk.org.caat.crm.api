<?php

use PHPUnit\Framework\TestCase;
eval(`cv php:boot`);

class CRM_API_ContactTest extends TestCase {
	public function testDatedSubscription(): array {
		// Set up the fixture for testing dated subscriptions/unsubscriptions.
		$contactParams = ['contact_type' => 'Individual', 'first_name' => __CLASS__];
		$groupParams = ['title' => __CLASS__];
		foreach (CRM_API_Contact::get($contactParams) as $contact) $contact->delete();
		foreach (CRM_API_Group::get($groupParams) as $group) $group->delete();
		$contact = CRM_API_Contact::create($contactParams);
		$group = CRM_API_Group::create($groupParams);
		
		// Record a dated subscription.
		$dateTime = new DateTime('2018-02-01 12:00:00');
		$contact->updateGroupStatus($group, 'Added', $dateTime);
		static::assertSame($contact->inGroup($group, 'Added'), TRUE);
		static::assertGroupContactStatus($contact, $group, 'Added');
		static::assertSubscriptionUpdate($contact, $group, $dateTime, 'Added');
		
		return [$contact, $group]; // Return the fixture.
	}
	
	/**
	* @depends testDatedSubscription
	*/
	public function testDatedUnsubscription(array $fixture): array {
		list($contact, $group) = $fixture; // Unpack the fixture.
		
		// Record a dated unsubscription.
		$dateTime = new DateTime('2018-05-06 14:23:00');
		$contact->updateGroupStatus($group, 'Removed', $dateTime);
		static::assertSame($contact->inGroup($group, 'Removed'), TRUE);
		static::assertGroupContactStatus($contact, $group, 'Removed');
		static::assertSubscriptionUpdate($contact, $group, $dateTime, 'Removed');
		
		return $fixture;
	}
	
	/**
	* @depends testDatedUnsubscription
	*/
	public function testPriorSubscription(array $fixture): array {
		list($contact, $group) = $fixture; // Unpack the fixture.
		
		// Record a prior subscription.
		$dateTime = new DateTime('2017-09-13 18:04:56');
		$contact->updateGroupStatus($group, 'Added', $dateTime);
		static::assertSame($contact->inGroup($group, 'Removed'), TRUE);
		static::assertGroupContactStatus($contact, $group, 'Removed');
		static::assertSubscriptionUpdate($contact, $group, $dateTime, 'Added');
		
		return $fixture;
	}
	
	/**
	* @depends testPriorSubscription
	*/
	public function testPriorSubscriptionDuplicate(array $fixture): array {
		list($contact, $group) = $fixture; // Unpack the fixture.
		
		// Rerecord a prior subscription.
		$dateTime = new DateTime('2017-09-13 18:04:56');
		$contact->updateGroupStatus($group, 'Added', $dateTime);
		static::assertSame($contact->inGroup($group, 'Removed'), TRUE);
		static::assertGroupContactStatus($contact, $group, 'Removed');
		static::assertSubscriptionUpdate($contact, $group, $dateTime, 'Added');
		
		return $fixture;
	}
	
	/**
	* @depends testPriorSubscriptionDuplicate
	*/
	public function testResubscription(array $fixture): array {
		list($contact, $group) = $fixture; // Unpack the fixture.
		
		// Record a recent resubscription.
		$dateTime = new DateTime('2018-07-08 01:53:00');
		$contact->updateGroupStatus($group, 'Added', $dateTime);
		static::assertSame($contact->inGroup($group, 'Added'), TRUE);
		static::assertGroupContactStatus($contact, $group, 'Added');
		static::assertSubscriptionUpdate($contact, $group, $dateTime, 'Added');
		
		return $fixture;
	}
	
	/**
	* @depends testResubscription
	*/
	public function testPriorUnsubscription(array $fixture) {
		list($contact, $group) = $fixture; // Unpack the fixture.
		
		// Record a prior unsubscription.
		$dateTime = new DateTime('2017-12-23 19:18:02');
		$contact->updateGroupStatus($group, 'Removed', $dateTime);
		static::assertSame($contact->inGroup($group, 'Added'), TRUE);
		static::assertGroupContactStatus($contact, $group, 'Added');
		static::assertSubscriptionUpdate($contact, $group, $dateTime, 'Removed');
		
		// Tear down the fixture.
		$contact->delete();
		$group->delete();
	}
	
	// Check that a contact has the expected status in a group.
	protected static function assertGroupContactStatus(CRM_API_Contact $contact, CRM_API_Group $group, string $status) {
		$groupContact = new CRM_Contact_BAO_GroupContact;
		$groupContact->contact_id = $contact->id;
		$groupContact->group_id = $group->id;
		$groupContact->find(TRUE);
		static::assertSame($groupContact->status, $status);
	}
	
	// Check that the expected subscription history record exists.
	protected static function assertSubscriptionUpdate(CRM_API_Contact $contact, CRM_API_Group $group, DateTime $dateTime, string $status) {
		$subscriptionHistory = new CRM_Contact_BAO_SubscriptionHistory;
		$subscriptionHistory->contact_id = $contact->id;
		$subscriptionHistory->group_id = $group->id;
		$subscriptionHistory->date = $dateTime->format('YmdHis');
		$subscriptionHistoryCount = $subscriptionHistory->find(TRUE);
		static::assertSame($subscriptionHistoryCount, 1);
		static::assertSame($subscriptionHistory->status, $status);
	}
}

?>
