<?php

namespace Drupal\spectrum\Services;

use Drupal\spectrum\Analytics\AnalyticsServiceInterface;
use Drupal\spectrum\Analytics\ListViewInterface;
use Drupal\spectrum\Serializer\JsonApiLink;
use Drupal\spectrum\Serializer\JsonApiRootNode;
use Symfony\Component\HttpFoundation\Request;

/**
 * The ModelApiService abstracts functionality that is shared between ModelApiHandler and MultiModelApiHandler
 */
class ModelApiService implements ModelApiServiceInterface
{

  protected AnalyticsServiceInterface $analyticsService;

  public function __construct(AnalyticsServiceInterface $analyticsService)
  {
    $this->analyticsService = $analyticsService;
  }


  /**
   * {@inheritdoc}
   */
  public function shouldReturnOnlyIdsForType(Request $request, string $modelSerializationType): bool
  {
    $onlyIds = false;

    if ($request->query->has("fields")) {
      $fieldLimits = $request->query->get("fields");

      if (array_key_exists($modelSerializationType, $fieldLimits)) {
        $limitFields = array_map('trim', explode(',', $fieldLimits[$modelSerializationType]));

        if ($limitFields && sizeof($limitFields) === 1 && $limitFields[0] === 'id') {
          $onlyIds = true;
        }
      }
    }

    return $onlyIds;
  }

  /**
   * {@inheritdoc}
   */
  public function getIncludeLimits(Request $request): array
  {
    // Next check if there are includeLimits defined (to limit the amount of includes possible per relationshipName)
    $includeLimits = [];
    if ($request->query->has('includeLimits')) {
      $includeLimitsParam = $request->query->get("includeLimits");

      if (is_array($includeLimitsParam)) {
        foreach ($includeLimitsParam as $relationshipName => $limit) {
          if (is_numeric($limit) && is_string($relationshipName)) {
            $includeLimits[$relationshipName] = (int) $limit;
          }
        }
      }
    }

    return $includeLimits;
  }

  /**
   * {@inheritdoc}
   */
  public function addSingleLink(JsonApiRootNode $jsonapi, string $name, string $baseUrl, ?int $limit = 0, ?int $page = 0, ?string $sort = null): self
  {
    $link = new JsonApiLink($name, $baseUrl);
    if (!empty($limit)) {
      $link->addParam('limit', $limit);
    }
    if (!empty($sort)) {
      $link->addParam('sort', $sort);
    }
    if (!empty($page)) {
      $link->addParam('page', $page);
    }
    $jsonapi->addLink($name, $link);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getListViewForFilterArray(string $modelClassName, array $filter): ?ListViewInterface
  {
    if (array_key_exists('_listview', $filter)) {
      $listViewParameterValue = $filter['_listview'];
      if (!empty($listViewParameterValue) && is_numeric($listViewParameterValue)) {

        $listview = $this->analyticsService->getListViewById($listViewParameterValue);

        if (!empty($listview) && $listview->getEntityName() === $modelClassName::entityType() && $listview->getBundleName() === $modelClassName::bundle()) {
          return $listview;
        }
      }
    }

    return null;
  }
}
