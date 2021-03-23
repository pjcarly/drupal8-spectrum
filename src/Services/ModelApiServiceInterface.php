<?php

namespace Drupal\spectrum\Services;

use Drupal\spectrum\Analytics\ListViewInterface;
use Drupal\spectrum\Serializer\JsonApiRootNode;
use Symfony\Component\HttpFoundation\Request;
use Drupal\spectrum\Query\Order;

/**
 * The ModelApiService abstracts functionality that is shared between ModelApiHandler and MultiModelApiHandler
 */
interface ModelApiServiceInterface
{
  /**
   * This method returns an array of sort orders found in the sort array (generally passed in the query parameters of the request)
   *
   * @param string $modelClassName
   * @param string[] $sortQueryFields
   * @return Order[]
   */
  public function getSortOrderListForSortArray(string $modelClassName, array $sortQueryFields): array;

  /**
   * This function checks if only Ids should be returned (instead of all attributes by default)
   *
   * @param Request $request
   * @param string $modelSerializationType
   * @return boolean
   */
  public function shouldReturnOnlyIdsForType(Request $request, string $modelSerializationType): bool;

  /**
   * Returns an array keyed by relationshipNames and values the limit that should be used when including relationships
   *
   * @param Request $request
   * @return array
   */
  public function getIncludeLimits(Request $request): array;

  /**
   * Adds a link to JsonApiRoot node, this adds meta information. needed to do pagination for example
   *
   * @param JsonApiRootNode $jsonapi
   * @param string $name
   * @param string $baseUrl
   * @param integer $limit
   * @param integer $page
   * @param string $sort
   * @return self
   */
  public function addSingleLink(JsonApiRootNode $jsonapi, string $name, string $baseUrl, ?int $limit = 0, ?int $page = 0, ?string $sort = null): self;

  /**
   * This method checks if there is a listview parameter in the filter array and fetches it from the DB
   *
   * @param string $modelClassName
   * @param array $filter
   * @param bool $ignoreBundle If the bundle should be ingored for the listview check
   * @return ListViewInterface|null
   */
  public function getListViewForFilterArray(string $modelClassName, array $filter, bool $ignoreBundle = false): ?ListViewInterface;
}
