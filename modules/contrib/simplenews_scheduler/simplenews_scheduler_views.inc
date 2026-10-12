<?php

/**
 * @file
 * Views support for simplenews.
 */

/**
 * Implements hook_views_tables().
 */
function simplenews_scheduler_views_tables() {
  $tables['simplenews_scheduler'] = [
    'name' => 'simplenews_scheduler',
    'filters' => [
      'node.created' => [
        'name' => t('Simplenews schedule: node created'),
        'list' => 'simplenews_scheduler_handler_newsletters',
        'list-type' => 'list',
        'handler' => 'simplenews_scheduler_filter_value',
        'operator' => 'simplenews_scheduler_handler_operator_ca',
        'value-type' => 'array',
        'help' => t('Select the newsletter to filter against, this will filter nodes that have been created AFTER the latest <i>edition</i> of this newsletter'),
      ],
    ],
  ];
  return $tables;
}

/**
 * Simplenews scheduler newsletter handler.
 *
 * @return array
 *   List of newsletters.
 */
function simplenews_scheduler_handler_newsletters() {
  $list = [];

  $result = \Drupal::database()->query("SELECT ss.*,n.title FROM {simplenews_scheduler} ss JOIN {node} n ON n.nid = ss.snid GROUP BY snid");
  foreach ($result as $row) {
    $list[$row['snid']] = "Newsletter: " . $row['title'];
  }

  if (count($list) === 0) {
    \Drupal::messenger()->addMessage(t('No current newsletter found to filter against, you should create a simplenews newsletter first.'));
  }

  return $list;
}

/**
 * Filter value.
 *
 * @param $op
 *   Op.
 * @param $filter
 *   Filter.
 * @param $filterinfo
 *   Filter info.
 * @param $query
 *   Query.
 */
function simplenews_scheduler_filter_value($op, $filter, $filterinfo, &$query) {

  // Get the oldest last_run time of the selected newsletter.
  $last_run = \Drupal::database()->query("SELECT ss.*,n.created FROM {simplenews_scheduler} ss
                      LEFT JOIN {node} n on n.nid = ss.snid
                      WHERE ss.snid IN (%s)
                      ORDER BY ss.last_run
                      LIMIT 0,1", ['%s' => implode(',', $filter['value'])])->fetchAssoc();

  // Then it hasn't run yet, so we use the creation date of the newsletter
  // parent instead.
  $trigger_time = $last_run['last_run'] > 0 ? $last_run['last_run'] : $last_run['created'];
  $query->add_field('created', 'node');
  $query->add_where("node.created %s %d", $filter['operator'], $trigger_time);
}

/**
 * Handler operator.
 *
 * @return array
 *   Text.
 */
function simplenews_scheduler_handler_operator_ca() {
  return [
    '>' => t("After Most Recent Edition Of"),
    '<' => t("Before Most Recent Edition Of"),
  ];
}

/**
 * Any view that has this as a filter should be invalidate cache.
 *
 * @param \Drupal\views\Entity\View $view
 *   View.
 */
function simplenews_scheduler_views_pre_query(&$view) {
  foreach ($view->filter as $filter) {
    if ($filter['field'] === 'simplenews_scheduler.node.created') {
      $view->is_cacheable = FALSE;
    }
  }
}
