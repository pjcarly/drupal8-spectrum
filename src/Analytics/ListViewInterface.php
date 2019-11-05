<?php

namespace Drupal\spectrum\Analytics;

use Drupal\spectrum\Query\Query;

interface ListViewInterface
{
  /**
   * Returns the Entity Name what this list view is for
   *
   * @return string
   */
  public function getEntityName(): string;

  /**
   * Returns the Bundle what this list view is for
   *
   * @return string
   */
  public function getBundleName(): string;

  /**
   * Applies the conditions, and orders from this ListView to the Query
   *
   * @param Query $query
   * @return void
   */
  public function applyListViewOnQuery(Query $query): void;
}
