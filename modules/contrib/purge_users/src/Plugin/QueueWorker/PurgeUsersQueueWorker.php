<?php

namespace Drupal\purge_users\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\user\Entity\User;

/**
 * Processes cron queue.
 *
 * @QueueWorker(
 *   id = "purge_users",
 *   title = @Translation("Purge Users Tasks Worker: Purge Users"),
 *   cron = {"time" = 15}
 * )
 */
class PurgeUsersQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($user_id) {
    $account = User::load($user_id);
    $config = \Drupal::config('purge_users.settings');
    $method = $config->get('purge_user_cancel_method');
    $userManagement = \Drupal::service('purge_users.user_management');
    $userManagement->purgeUser($account, $method);
  }

}
