<?php

namespace Drupal\Tests\simplenews_scheduler\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Class SimplenewsSchedulerWebTestBase.
 */
abstract class SimplenewsSchedulerTestBase extends BrowserTestBase {

  /**
   * Modules.
   *
   * @var string[]
   */
  protected static $modules = ['simplenews_scheduler'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The Simplenews scheduler settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Overrides DrupalWebTestCase::setUp().
   */
  protected function setUp() {
    parent::setUp();
    // Set the site timezone to something visibly different from UTC, which
    // has daylight saving changes.
    $date_config = $this->config('system.date');
    $date_config->set('timezone.default', 'Europe/Kiev');
    $date_config->save();
  }

}
