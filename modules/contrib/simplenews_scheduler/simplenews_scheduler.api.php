<?php

/**
 * @file
 * Hooks provided by the Simplenews scheduler module.
 */

use Drupal\node\Entity\Node;

/**
 * Alter node object that was cloned from template node before it gets saved.
 *
 * The node is passed as node object and therefore passed by reference. This
 * hook is for example usefull if you have fields in the template node that
 * contain information about data that should get rendered dynamically into the
 * edition node depenedent on the current schedule date.
 *
 * @param \Drupal\node\Entity\Node $edition_node
 *   The cloned node object based on the scheduler node.
 * @param \Drupal\node\Entity\Node $scheduler_node
 *   The original scheduler node object.
 */
function hook_simplenews_scheduler_edition_node_alter(Node $edition_node, Node $scheduler_node) {
  $edition_node->setTitle('Your newsletter from ' . \Drupal::time()->getRequestTime());
}
