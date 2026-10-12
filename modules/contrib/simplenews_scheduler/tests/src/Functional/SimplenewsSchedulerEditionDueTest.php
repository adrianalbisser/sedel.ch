<?php

namespace Drupal\Tests\simplenews_scheduler\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;

/**
 * Functional tests for simplenews_scheduler_get_newsletters_due().
 *
 * @group simplenews_scheduler
 */
class SimplenewsSchedulerEditionDueTest extends SimplenewsSchedulerTestBase {

  use StringTranslationTrait;

  /**
   * Set up.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp() {
    parent::setUp();

    $admin_user = $this->drupalCreateUser([
      'access content',
      'administer nodes',
      'create simplenews_issue content',
      'edit own simplenews_issue content',
      'send newsletter',
      'send scheduled newsletters',
      'overview scheduled newsletters',
    ]);
    $this->drupalLogin($admin_user);

    // The start date of the edition. This is on 5 January so that we get some
    // days in either month, and at noon to keep things simple.
    $this->edition_day = '05';
    $start_date = new \DateTime("2012-01-$this->edition_day 12:00:00");

    // Create a parent newsletter node.
    $node = Node::create([
      'type' => 'simplenews_issue',
      'title' => 'Parent',
      'uid' => 1,
      'status' => 1,
    ]);
    $node->simplenews_issue->target_id = 'default';
    $node->simplenews_issue->handler = 'simplenews_all';
    $node->save();

    // Grumble grumble there's no node saving API in our module!
    // @see http://drupal.org/node/1480328 to clean this up.
    $node->simplenews_scheduler = (object) [
      'nid' => $node->id(),
      'last_run' => 0,
      'activated' => '1',
      'send_interval' => 'month',
      'interval_frequency' => '1',
      'start_date' => $start_date->getTimestamp(),
      // Needs to be set manually when creating new records programmatically.
      'next_run' => $start_date->getTimestamp(),
      'stop_type' => '0',
      'stop_date' => '0',
      'stop_edition' => '0',
      'title' => '[node:title] for [current-date:long]',
    ];
    $record = (array) $node->simplenews_scheduler;
    $query = \Drupal::database()->merge('simplenews_scheduler');
    $query->key('nid', $record['nid'])
      ->fields($record)
      ->execute();

    // Store the node ID for the test to use.
    $this->parent_nid = $node->id();
  }

  /**
   * Test simplenews_scheduler_get_newsletters_due().
   */
  public function testEditionsDue() {
    // Get the node id of the parent newsletter node.
    $parent_nid = $this->parent_nid;

    // But just check it exists for sanity.
    $this->drupalGet("node/$parent_nid");

    // Simulate cron running daily at half past 12 so that an edition due at
    // 12 noon should be picked up.
    $start_date = new \DateTime("2012-01-01 12:00:00");
    $time_offsets = [
      'before' => "-1 hour",
      'after'  => "+1 hour",
    ];

    // Number of days to run cron for.
    $days = 150;
    // Index of the days we've done so far.
    $added_days = 0;
    // Iterate over days.
    while ($added_days <= $days) {
      // Create today's date at noon and get the timestamp.
      $date = clone($start_date);
      $date->add(new \DateInterval("P{$added_days}D"));
      $timestamp_noon = $date->getTimestamp();

      // We simulate running cron one hour before and one hour after noon.
      foreach ($time_offsets as $offset_key => $offset) {
        // Create a timestamp based on noon + the offset.
        // This gives us either 11:00 or 13:00 on the current day.
        $timestamp = strtotime($offset, $timestamp_noon);

        // Get the list of newsletters due.
        $due = simplenews_scheduler_get_newsletters_due($timestamp);

        // An edition is due if it's 13:00 on the edition day.
        $formatted = date(DATE_RFC850, $timestamp);
        if ($offset_key === 'after' && date('d', $timestamp) === $this->edition_day) {
          $this->assertTrue(isset($due[$parent_nid]), "Edition due at day $added_days, $formatted, $timestamp");
        }
        else {
          $this->assertFalse(isset($due[$parent_nid]), "Edition not due at day $added_days, $formatted, $timestamp");
        }

        // Get some debug output to figure out what is going on in
        // simplenews_scheduler_get_newsletters_due().
        $intervals['hour'] = 3600;
        $intervals['day'] = 86400;
        $intervals['week'] = $intervals['day'] * 7;
        $intervals['month'] = $intervals['day'] * date('t', $timestamp);

        if (isset($due[$parent_nid])) {
          // Output what we got back from the function.
          $newsletter_parent_data = $due[$parent_nid];
          $edition_time = simplenews_scheduler_calculate_edition_time($newsletter_parent_data, $timestamp);
          $eid = _simplenews_scheduler_new_edition($newsletter_parent_data->nid, $timestamp);

          // Check the edition time is 12:00.
          $this->assertEquals(date('H:i', $edition_time), '12:00', $this->t('Edition time is at 12:00.'));

          // Fake sending it: update the 'last_run' for subsequent iterations.
          \Drupal::database()->update('simplenews_scheduler')
            ->fields(['last_run' => $timestamp])
            ->condition('nid', $parent_nid)
            ->execute();

          // Update the edition record.
          simplenews_scheduler_scheduler_update($newsletter_parent_data, $timestamp);

          // Check the node exists.
          $this->drupalGet("node/$eid");
        } // Handling the request for a new edition.
      } // Foreach offset timestamp.
      // Increment our counter.
      $added_days++;
    } // Foreach day.
  }

}
