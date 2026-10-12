<?php

namespace Drupal\simplenews_scheduler\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default controller for the simplenews_scheduler module.
 */
class EditionsController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Creates the EditionsController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('database')
    );
  }

  /**
   * Check whether to display the Scheduled Newsletter tab.
   */
  public function checkAccess(NodeInterface $node) {
    // Check if this is a simplenews node type and permission.
    if ($node->hasField('simplenews_issue') && $node->simplenews_issue->target_id !== NULL && $this->currentUser->hasPermission('overview scheduled newsletters')) {
      // Check if this is either a scheduler newsletter or an edition.
      return AccessResult::allowedIf(!empty($node->simplenews_scheduler) || !empty($node->is_edition));
    }
    return AccessResult::forbidden();
  }

  /**
   * Helper function to get the identifier of newsletter.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object for the newsletter.
   *
   * @return int|bool
   *   If the node is a newsletter edition, the node id of its parent template
   *   newsletter; if the node is a template newsletter, its own node id; and
   *   FALSE if the node is not part of a scheduled newsletter set.
   */
  public function getPid(NodeInterface $node) {
    // First assume this is a newsletter edition.
    if (isset($node->simplenews_scheduler_edition)) {
      return $node->simplenews_scheduler_edition->pid;
    }
    // Or this itself is the parent newsletter.
    if (isset($node->simplenews_scheduler)) {
      return $node->id();
    }

    return FALSE;
  }

  /**
   * Get a Node editions page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node.
   *
   * @return array
   *   Render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function nodeEditionsPage(NodeInterface $node) {
    $nid = $this->getPid($node);
    $output = [];
    $rows = [];

    // This is the template newsletter.
    if ($nid === $node->id()) {
      $output['prefix']['#markup'] = '<p>' . $this->t('This is a newsletter template node of which all corresponding editions nodes are based on.') . '</p>';

      // Load the corresponding editions from the database to further process.
      $result = $this->database->select('simplenews_scheduler_editions', 's')
        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
        ->limit(20)
        ->fields('s')
        ->condition('s.pid', $nid)
        ->execute()
        ->fetchAll();

      foreach ($result as $row) {
        $node = $this->entityTypeManager->getStorage('node')->load($row->eid);
        $rows[] = [
          $node->toLink()->toString(),
          \Drupal::service('date.formatter')->format($row->date_issued, 'custom', 'Y-m-d H:i'),
        ];
      }

      // Display a table with all editions.
      // @todo change to render array
      $output['table'] = [
        '#type' => 'table',
        '#header' => [$this->t('Edition Node'), $this->t('Date sent')],
        '#rows' => $rows,
        '#attributes' => ['class' => ['schedule-history']],
        '#empty' => $this->t('No scheduled newsletter editions have been sent.'),
      ];
      $output['pager'] = ['#type' => 'pager'];
    }
    // This is a newsletter edition.
    else {
      $master_node = $this->entityTypeManager->getStorage('node')->load($nid);
      $output['prefix']['#markup'] = '<p>' . $this->t('This node is part of a scheduled newsletter configuration. View the original newsletter <a href="@parent">here</a>.', ['@parent' => $master_node->toUrl()->toString()]) . '</p>';
    }

    return $output;
  }

}
