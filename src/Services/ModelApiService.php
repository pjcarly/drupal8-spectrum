<?php

namespace Drupal\spectrum\Services;

use Drupal\spectrum\Analytics\AnalyticsServiceInterface;
use Drupal\spectrum\Analytics\ListViewInterface;
use Drupal\spectrum\Query\Order;
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
  public function getSortOrderListForSortArray(string $modelClassName, array $sortQueryFields): array
  {
    $prettyToFieldsMap = $modelClassName::getPrettyFieldsToFieldsMapping();
    $sortOrders = [];
    foreach ($sortQueryFields as $sortQueryField) {
      // the json-api spec tells us, that all fields are sorted ascending, unless the field is prepended by a '-'
      // http://jsonapi.org/format/#fetching-sorting
      $direction = (!empty($sortQueryField) && $sortQueryField[0] === '-') ? 'DESC' : 'ASC';
      $prettyField = ltrim($sortQueryField, '-'); // lets remove the '-' from the start of the field if it exists

      $prettyFieldParts = explode('.', $prettyField);

      // if the pretty field exists, and if it is not type (which cannot be sorted on in drupal)
      // Lets add it as a sort order
      if (array_key_exists($prettyFieldParts[0], $prettyToFieldsMap) && $prettyFieldParts[0] !== 'type') {
        $field = $prettyToFieldsMap[$prettyFieldParts[0]];
        $fieldDefinition = $modelClassName::getFieldDefinition($field);
        $fieldType = $fieldDefinition->getType();

        if (sizeof($prettyFieldParts) > 1) // meaning we have a extra column present
        {
          // Only certain types are allowed to sort on a different column
          $typePrettyToFieldsMap = $modelClassName::getTypePrettyFieldToFieldsMapping();

          if (array_key_exists($fieldType, $typePrettyToFieldsMap) && array_key_exists($prettyFieldParts[1], $typePrettyToFieldsMap[$fieldType])) {
            $column = $typePrettyToFieldsMap[$fieldType][$prettyFieldParts[1]];
            $sortOrders[] = new Order($field . '.' . $column, $direction);
          }
        } else {
          if ($fieldType === 'entity_reference' || $fieldType === 'entity_reference_revisions') {
            // In case the field type is entity reference, we want to sort by the title, not the ID
            // Because the user entity works differently than any other, we must also check for the target_type
            $settings = $fieldDefinition->getSettings();
            if ($settings['target_type'] === 'user') {
              $sortOrders[] = new Order($field . '.entity.name', $direction);
            } else {
              $sortOrders[] = new Order($field . '.entity.title', $direction);
            }
          } else {
            // Any other field, can be sorted like normal
            $sortOrders[] = new Order($field, $direction);
          }
        }
      }
    }

    return $sortOrders;
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
