<?php

namespace Drupal\purge_users\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Class ConfirmationForm.
 *
 * @package Drupal\purge_users\Form
 */
class ConfirmationForm extends ConfirmFormBase {
  /**
   * The number of users to show before resorting to "... and x more.".
   */
  const NUMBER_OF_USERS_TO_SHOW = 50;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'purge_users_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Purge user confirmation');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('purge_users.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Are you sure you want to cancel these user accounts?');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Confirm');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $accounts = NULL) {
    $form = parent::buildForm($form, $form_state);
    $uids = purge_users_get_user_ids();
    $form['accounts'] = [
      '#prefix' => '<ul>',
      '#suffix' => '</ul>',
      '#tree' => TRUE,
    ];

    $uidsToShow = array_slice($uids, 0, self::NUMBER_OF_USERS_TO_SHOW);
    $accounts = User::loadMultiple($uidsToShow);
    foreach ($accounts as $account) {
      // Prevent user 1 from being canceled.
      if ($account->get('uid')->value <= 1) {
        continue;
      }
      $form['accounts']['uid' . $account->get('uid')->value] = [
        '#type' => 'markup',
        '#value' => $account->get('uid')->value,
        '#prefix' => '<li>',
        '#suffix' => $account->get('name')->value . " &lt;" . $account->get('mail')->value . "&gt; </li>\n",
      ];
    }

    if (count($uids) > self::NUMBER_OF_USERS_TO_SHOW) {
      $form['accounts']['and_more'] = [
        '#type' => 'markup',
        '#markup' => $this->t('...and @more more.', ['@more' => count($uids) - self::NUMBER_OF_USERS_TO_SHOW]),
        '#prefix' => '<li>',
        '#suffix' => '</li>',
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $ids = purge_users_get_user_ids();
    // Initialize a batch operation.
    $batch = [
      'operations' => [],
      'finished' => 'purge_users_batch_completed',
      'title' => $this->t('Delete users'),
      'init_message' => $this->t('Delete users operation is starting...'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message' => $this->t('Delete users operation has encountered an error.'),
    ];
    // User load multiple to process through batch operation.
    foreach ($ids as $id) {
      $batch['operations'][] = ['\Drupal\purge_users\Plugin\BatchWorker\BatchWorker::batchWorkerPurgeUsers', [$id]];
    }
    // Batch set.
    batch_set($batch);
  }

}
