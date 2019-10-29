<?php

use Drupal\spectrum\Model\Model;


/**
 * Implements hook_drush_command().
 */
function spectrum_drush_command()
{
  $commands['spectrum-view-sql'] = [
    'description' => 'Generate views for easy querying',
    'aliases' => ['sp-vsql']
  ];

  return $commands;
}

function drush_spectrum_view_sql($entityType = null)
{
  if (empty($entityType)) {
    drush_print('you must provide an entity type as an argument to this query');
  }

  $modelClasses = Model::getModelClasses();
  foreach ($modelClasses as $modelClass) {
    if ($modelClass::entityType() === $entityType) {
      $query = $modelClass::getViewSelectQuery();

      if (!empty($query)) {
        $viewName = empty($modelClass::bundle()) ? $modelClass::entityType() : $modelClass::bundle();
        db_query('DROP VIEW IF EXISTS `view_' . $viewName . '`');

        $query = "CREATE VIEW `view_" . $viewName . "` AS " . $query;
        db_query($query);
      }
    }
  }
}
