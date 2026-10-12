<?php

namespace Drupal\purge_users\Services;

use Drupal\user\UserInterface;

/**
 * Interface UserManagementServiceInterface.
 */
interface UserManagementServiceInterface {

  /**
   * Cancel a user and anonymize it.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to block.
   * @param string $method
   *   The cancel method to use.
   */
  public function purgeUser(UserInterface $user, String $method);

}
