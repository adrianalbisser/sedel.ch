<?php

namespace Drupal\purge_users\Plugin\BatchWorker;

use Drupal\user\Entity\User;

/**
 * Class BatchWorker.
 *
 * @package Drupal\purge_users\Plugin\BatchWorker
 */
class BatchWorker {

  /**
   * Process items in a batch.
   */
  public static function batchWorkerPurgeUsers($id, &$context) {
    $account = User::load($id);
    if (!isset($context['results']['purged'])) {
      $context['results']['purged'] = 0;
    }
    $config = \Drupal::config('purge_users.settings');
    $method = $config->get('purge_user_cancel_method');
    $name = $account->get('name')->value;
    $userManagement = \Drupal::service('purge_users.user_management');
    $userManagement->purgeUser($account, $method);
    $context['message'] = "Now processing $name ...";

    // Update our progress information.
    $context['results']['purged']++;
  }

}
