<?php

namespace Drupal\Tests\simplenews_scheduler\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\node\Entity\Node;

/**
 * Testing generation of newsletters.
 *
 * @group simplenews_scheduler
 */
class SimplenewsSchedulerNodeCreationTest extends SimplenewsSchedulerTestBase {

  use AssertMailTrait, StringTranslationTrait;

  /**
   * Modules.
   *
   * @var string[]
   *   Modules.
   */
  protected static $modules = ['block'];

  /**
   * Set up.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');
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

    // Subscribe a user to simplenews.
    $this->mail = 'test@example.org';
    \Drupal::service('simplenews.subscription_manager')->subscribe($this->mail, 'default', FALSE, 'test');
  }

  /**
   * Basic simplenews newsletter generation test.
   *
   * Create a simplenews node,
   */
  public function testNewsletterGeneration() {
    $title = "newsletter " . $this->randomMachineName(8);

    $edit = [
      'title[0][value]' => $title,
      'body[0][value]' => $this->randomMachineName(16),
      'simplenews_issue[target_id]' => 'default',
      'status[value]' => TRUE,
    ];
    $this->drupalGet('node/add/simplenews_issue');
    $this->submitForm($edit, $this->t('Save'));
    $this->assertSession()->pageTextContains($title);

    $node = $this->drupalGetNodeByTitle($title);

    // Make sure that the editions tab is not visible as long as it's not a
    // scheduled newsletter.
    $this->drupalGet("node/{$node->id()}/editions");
    $this->assertSession()->statusCodeEquals(403);

    // Now create the simplenews schedule configuration.
    $this->drupalGet("node/" . $node->id() . "/simplenews");
    $this->assertSession()->pageTextContains($this->t('Enable scheduled newsletter'));

    $edit = [];
    $edit['enable_scheduler'] = TRUE;
    $edit["interval"] = "hour";

    // Specify a start time 30 minutes in the past to be able to have a known
    // edition creation time that can be checked.
    $date = new \DateTime();
    $date->sub(new \DateInterval('PT30M'));

    $edit["start_date[date]"] = $date->format('Y-m-d');
    $edit["start_date[time]"] = $date->format('H:i:s');
    $edit["title"] = "Custom title [node:nid]";

    $this->drupalGet('node/' . $node->id() . '/simplenews');
    $this->submitForm($edit, $this->t('Save scheduler settings'));

    // Check if the saving was successful.
    $this->assertSession()->pageTextContains('Newsletter schedule preferences have been saved.');
    $this->assertSession()->fieldValueEquals('start_date[date]', $date->format('Y-m-d'));
    $this->assertSession()->fieldValueEquals('start_date[time]', $date->format('H:i:s'));
    $this->assertSession()->fieldValueEquals('title', "Custom title [node:nid]");

    // Make sure it knows no editions created yet.
    $this->drupalGet("node/{$node->id()}/editions");
    $this->assertSession()->pageTextContains($this->t("No scheduled newsletter editions have been sent."));

    // Execute cron.
    \Drupal::service('cron')->run();

    $edition_nids = \Drupal::entityQuery('node')
      ->sort('nid', 'DESC')
      ->execute();
    $edition_node = Node::load(reset($edition_nids));

    // Check that the newsletter has been saved and sent.
    $this->assertTrue($edition_node->simplenews_issue->subscribers > 0);

    // See if it was created.
    $this->drupalGet("node/{$node->id()}/editions");
    $this->assertSession()->pageTextContains("Custom title " . $edition_node->id());
    $this->assertSession()->pageTextNotContains($this->t("No scheduled newsletter editions have been sent."));

    // Go to node and verify creation time and token for custom title.
    $this->clickLink("Custom title " . $edition_node->id());

    $this->assertEquals($edition_node->getCreatedTime(), $date->getTimestamp());

    // Check sent mails.
    $mails = $this->getMails();
    $this->assertEquals(1, count($mails), $this->t('Newsletter mail has been sent.'));

    $this->clickLink($this->t('Newsletter'));
    $this->assertSession()->pageTextContains($this->t('This node is part of a scheduled newsletter configuration.'));
    $this->clickLink($this->t('here'));
    $url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();

    $this->assertEquals($url, $this->getUrl());

    // Test the tab on a sent newsletter, schedule details should not be shown.
    $title = "newsletter " . $this->randomMachineName(8);

    $edit = [
      'title[0][value]' => $title,
      'body[0][value]' => $this->randomMachineName(16),
      'simplenews_issue[target_id]' => 'default',
      'status[value]' => TRUE,
    ];
    $this->drupalGet('node/add/simplenews_issue');
    $this->submitForm($edit, $this->t('Save'));
    $this->assertSession()->pageTextContains($title);

    $node = $this->drupalGetNodeByTitle($title);

    $edit = [];
    $this->drupalGet('node/' . $node->id() . '/simplenews');
    $this->submitForm($edit, $this->t('Send now'));
    $this->assertSession()->pageTextNotContains($this->t('Scheduled Newsletter'));

    // Check sent mails.
    $mails = $this->getMails();
    $this->assertEquals(1, count($mails), $this->t('Newsletter mail has been sent.'));
  }

}
