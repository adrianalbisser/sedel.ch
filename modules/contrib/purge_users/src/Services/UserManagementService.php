<?php

namespace Drupal\purge_users\Services;

use Drupal\user\UserInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AnonymousUserSession;

/**
 * Class UserManagementService.
 *
 * @package Drupal\kankernl_user\Services
 */
class UserManagementService implements UserManagementServiceInterface {

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * UserManagementService constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(AccountProxyInterface $currentUser, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, MessengerInterface $messenger) {
    $this->currentUser = $currentUser;
    $this->config = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function purgeUser(UserInterface $user, string $method) {
    $logger = \Drupal::logger('purge_users');
    $edit = [];
    // When the 'user_cancel_delete' method is used, user_delete() is called,
    // which invokes hook_ENTITY_TYPE_predelete() and hook_ENTITY_TYPE_delete()
    // for the user entity. Modules should use those hooks to respond to the
    // account deletion.
    if ($method != 'user_cancel_delete') {
      // Allow modules to add further sets to this batch.
      $this->moduleHandler->invokeAll('user_cancel', [$edit, $user, $method]);
    }

    switch ($method) {
      case 'user_cancel_block':
      case 'user_cancel_block_unpublish':
      default:
        // Send account blocked notification if option was checked.
        if (!empty($edit['user_cancel_notify'])) {
          _user_mail_notify('status_blocked', $user);
        }
        $user->block();
        $user->save();
        \Drupal::messenger()->addStatus(t('%name has been disabled.', ['%name' => $user->getDisplayName()]));
        $logger->notice('Blocked user: %name %email.', ['%name' => $user->getAccountName(), '%email' => '<' . $user->getEmail() . '>']);
        break;

      case 'user_cancel_reassign':
      case 'user_cancel_delete':
        // Send account canceled notification if option was checked.
        if (!empty($edit['user_cancel_notify'])) {
          _user_mail_notify('status_canceled', $user);
        }
        $user->delete();
        \Drupal::messenger()->addStatus(t('%name has been deleted.', ['%name' => $user->getDisplayName()]));
        $logger->notice('Deleted user: %name %email.', ['%name' => $user->getAccountName(), '%email' => '<' . $user->getEmail() . '>']);
        break;
    }

    // Send a notification email.
    $config = $this->config->get('purge_users.settings');
    $send_notification = $config->get('send_email_notification');
    if ($send_notification == 1) {
      purge_users_send_notification_email($user);
    }

    // After cancelling account, ensure that user is logged out. We can't
    // destroy their session though, as we might have information in it, and we
    // can't regenerate it because batch API uses the session ID, we will
    // regenerate it in _user_cancel_session_regenerate().
    if ($user->id() == $this->currentUser->id()) {
      $this->currentUser->setAccount(new AnonymousUserSession());
    }
  }

}
