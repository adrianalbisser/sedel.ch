<?php

namespace Drupal\Tests\simplenews_scheduler\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Test edition time after DST changes for a monthly newsletter.
 *
 * @group simplenews_scheduler
 */
class SimplenewsSchedulerDaylightSavingSwitchTest extends SimplenewsSchedulerTestBase {

  use StringTranslationTrait;

  /**
   * Test edition time after DST changes for a monthly newsletter.
   *
   * @todo: generalize this for other intervals.
   */
  public function testDSTMonthly() {

    // Create a last run time before DST begins, and a now time after.
    // Use date_create() rather than strtotime so that we create a date at the
    // given time *in the current timezone* rather than UTC.
    $last_run_date = new \DateTime("01-Mar-12 12:00:00");
    $now_date = date_create("05-Apr-12 12:00:00");
    // Fake up newsletter data.
    $newsletter_parent_data = (object) [
      'last_run' => $last_run_date->getTimestamp(),
      'send_interval' => 'month',
      'interval_frequency' => 1,
    ];

    // Get the edition time.
    $edition_time = simplenews_scheduler_calculate_edition_time($newsletter_parent_data, $now_date->getTimestamp());

    // Format the edition time.
    $edition_time_formatted = \Drupal::service('date.formatter')->format($edition_time, 'custom', DATE_ATOM);
    $edition_hour_formatted = \Drupal::service('date.formatter')->format($edition_time, 'custom', 'H:i');

    $this->assertEquals($edition_hour_formatted, '12:00', $this->t('Edition time is at 12:00 in the local timezone; full edition time is %time.', [
      '%time' => $edition_time_formatted,
    ]));
  }

}
