<?php

namespace Drupal\spectrum\Analytics;

interface AnalyticsServiceInterface
{
  const SERVICE_NAME = 'spectrum.analytics_service';

  /**
   * Finds a ListView by its Id
   *
   * @param string $id
   * @return ListViewInterface|null
   */
  public function getListViewById(string $id): ?ListViewInterface;
}
