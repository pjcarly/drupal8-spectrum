<?php

namespace Drupal\spectrum\Rest;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Query\ConditionGroup;
use Drupal\spectrum\Query\Order;
use Drupal\spectrum\Query\EntityQuery;
use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\Relationship;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Serializer\JsonApiBaseNode;
use Drupal\spectrum\Serializer\JsonApiLink;
use Drupal\spectrum\Analytics\AnalyticsServiceInterface;
use Drupal\spectrum\Analytics\ListViewInterface;

use Drupal\spectrum\Exceptions\InvalidTypeException;
use Drupal\spectrum\Model\PolymorphicCollection;
use Drupal\spectrum\Query\MultiModelQuery;

/**
 * This class provides an implementation of a BaseApiHandler for multiple Models (of the same entity) in a jsonapi.org compliant way
 * - Only Get without slug supported for now
 */
class MultiModelApiHandler extends BaseApiHandler
{
  /**
   * The fully qualified classname of the model you want to use in this apihandler
   *
   * @var string[]
   */
  private $modelClassNames = [];

  /**
   * The shared entity type of all the model classes
   *
   * @var string
   */
  private $entityType;

  /**
   * The default maxlimit for a result, to make sure we dont return everything in the database, but paginate results
   *
   * @var integer
   */
  protected $maxLimit = 200;


  /**
   * Base conditions that will be added to all queries done in the api handler
   *
   * @var Condition[]
   */
  protected $baseConditions = [];

  /**
   * condition groups that will be added to all queries done in the api handler
   *
   * @var ConditionGroup[]
   */
  protected $conditionGroups = [];

  /**
   * @param string $entityType the shared entityType of all modelclasses
   */
  public function __construct(string $entityType)
  {
    parent::__construct();

    $this->entityType = $entityType;
    $this->defaultHeaders['Content-Type'] = JsonApiRootNode::HEADER_CONTENT_TYPE;
  }

  /**
   * Adds a modelClassName to the list of allowed modelclasses.
   * Make sure all the modelclasses are from the same entity
   *
   * @param string $modelClassName
   * @return MultiModelApiHandler
   */
  public function addModelClassName(string $modelClassName)
  {
    if ($this->entityType !== $modelClassName::entityType()) {
      throw new InvalidTypeException('All modelclasses in a multimodelapihandler must be of the same entity');
    }

    $modelClass = Model::getModelClassForEntityAndBundle($modelClassName::entityType(), $modelClassName::bundle());
    $this->modelClassNames[] = $modelClass;
    return $this;
  }

  /**
   * Add a ConditionGroup that will be applied to all queries
   *
   * @param ConditionGroup $conditionGroup
   * @return MultiModelApiHandler
   */
  protected final function addConditionGroup(ConditionGroup $conditionGroup): MultiModelApiHandler
  {
    $this->conditionGroups[] = $conditionGroup;
    return $this;
  }

  /**
   * Add a base condition that will be applied to all queries in this apihandler
   *
   * @param Condition $condition
   * @return MultiModelApiHandler
   */
  protected final function addBaseCondition(Condition $condition): MultiModelApiHandler
  {
    $this->baseConditions[] = $condition;
    return $this;
  }


  /**
   * Clears all the Base Conditions and Conditions that were added to the model api handler
   *
   * @return MultiModelApiHandler
   */
  protected final function clearAllConditions(): MultiModelApiHandler
  {
    $this->conditionGroups = [];
    $this->baseConditions = [];
    return $this;
  }

  /**
   * This function will add all the Conditions in this ApiHandler to the provided Query
   *
   * @param MultiModelQuery $query The query you want to add the conditions to
   * @return MultiModelQuery Returns the same query as the one provided in the parameters
   */
  protected final function applyAllConditionsToQuery(MultiModelQuery $query): MultiModelQuery
  {
    // We check for base conditions (these are conditions that always need to be applied, regardless of the api)
    // This can be used to limit the results based on the logged in user, when the user only has access to certain records
    if (sizeof($this->baseConditions) > 0) {
      foreach ($this->baseConditions as $condition) {
        $query->addBaseCondition($condition);
      }
    }

    // Next we also do the same for the condition groups
    if (sizeof($this->conditionGroups) > 0) {
      foreach ($this->conditionGroups as $conditionGroup) {
        $query->addConditionGroup($conditionGroup);
      }
    }

    return $query;
  }

  /**
   * This function can be used to change the JsonApi.org result before serializing the results
   *
   * @param Collection|Model $object
   * @return JsonApiBaseNode
   */
  protected function getJsonApiNodeForPolymorphicCollection(PolymorphicCollection $object): JsonApiBaseNode
  {
    return $object->getJsonApiNode();
  }

  /**
   * This method adds a hook for the Get Request, where an implenentation can choose to alter the query just before the fetch is executed
   * The query returned will be executed
   *
   * @param MultiModelQuery $query
   * @return MultiModelQuery
   */
  protected function beforeGetFetch(MultiModelQuery $query): MultiModelQuery
  {
    return $query;
  }

  /**
   * This method executes get functionality for the Rest call. If a slug is provided, 1 result will be fetched from the database
   * If no slug was provided a list of results will be returend.
   * Permissions to the API will be checked, and fields will be filtered to only include the fields where the user has access to
   *
   * @param Request $request
   * @return Response
   */
  public function get(Request $request): Response
  {
    $limit = 0;
    $page = 0;
    $sort = '';
    $jsonapi = new JsonApiRootNode();

    // Before anything, we check if the user has permission to access this content
    if (sizeof($this->modelClassNames) === 0) {
      return new Response(null, 500, []);
    }


    $modelClassConditionGroup = new ConditionGroup();
    $count = [];
    foreach ($this->getModelClassNames() as $modelClassName) {
      if (!$modelClassName::userHasReadPermission()) {
        // No access, return a 405 response
        return new Response(null, 405, []);
      } else {
        $modelClassConditionGroup->addCondition(new Condition('type', '=', $modelClassName::bundle()));
        $count[] = sizeof($count) + 1;
      }
    }

    $modelClassConditionGroup->setLogic(strtr('OR(@logic)', ['@logic' => implode(',', $count)]));

    $query = new MultiModelQuery($this->entityType);
    $query->addConditionGroup($modelClassConditionGroup);

    // We start by adding the link to this request
    $baseUrl = $request->getSchemeAndHttpHost() . $request->getPathInfo(); // this might not work with a different port than 80, check later

    // Get requests can either be a list of models, or an individual model, so we must check the slug
    $responseCode = 200;
    if (empty($this->slug)) {
      // when we don't have a slug, we are expected to always return an array response,
      // even when the result is a single object
      $jsonapi->asArray(true);

      // when the slug is empty, we must check for extra variables
      if ($request->query->has('limit') && is_numeric($request->query->get('limit'))) {
        $limit = $request->query->get('limit');
      }

      // Additional check for the page variable, we potentially need to adjust the query start
      if ($request->query->has('page') && is_numeric($request->query->get('page'))) {
        $page = $request->query->get('page');

        if (!empty($limit)) {
          $start = ($page - 1) * $limit;
          $query->setRange($start, $limit);
        } else {
          $start = ($page - 1) * $this->maxLimit;
          $query->setRange($start, $this->maxLimit);
        }
      } else {
        // no page, we can just set a limit
        $query->setLimit(empty($limit) ? $this->maxLimit : $limit);
      }

      // Lets also check for a sort order
      if ($request->query->has('sort')) {
        // sort params are split by ',' so lets evaluate them individually
        $sort = $request->query->get('sort');
        $sortQueryFields = explode(',', $sort);

        // We cannot simply sort on a field. We must check every modelclass for the sort orders
        $sortOrders = static::getSortOrderListForSortArray($this->getModelClassNames(), $sortQueryFields);

        foreach ($sortOrders as $sortOrder) {
          $query->addSortOrder($sortOrder);
        }
      }

      // We musn't forget to add all the conditions that were potentially added to this ApiHandler
      $this->applyAllConditionsToQuery($query);

      // Before we fetch the collection, lets check for filters
      if ($request->query->has('filter')) {
        $filter = $request->query->get('filter');
        if (is_array($filter)) {
          // We first get the filters provided in the filter array
          $conditions = static::getConditionListForFilterArray($this->getModelClassNames(), $filter);

          // Next we check if the filters require filterlogic
          if (array_key_exists('_logic', $filter)) {
            $logic = $filter['_logic'];
            $query->setConditionLogic($logic);
          }

          // And apply the conditions
          foreach ($conditions as $condition) {
            $query->addCondition($condition);
          }

          // Lets see if a listView was passed and found (done in the conditionListforfliterarray function)
          $listview = static::getListViewForFilterArray($this->entityType, $filter);
          if (!empty($listview)) {
            // a matching listview was found, we now apply the query to be adjusted by the list view
            $listview->applyListViewOnQuery($query);
          }
        }
      }

      // Here we build the links of the request
      $this->addSingleLink($jsonapi, 'self', $baseUrl, $limit, $page, $sort); // here we add the self link

      // We call the GetFetch Hook, where an implementation can potentially alter the query
      $query = $this->beforeGetFetch($query);

      // And finally fetch the model
      $result = $query->fetchCollection();

      if (!$result->isEmpty) {
        // We load the translations on the response
        $this->loadLanguages($request, $result);

        // we must include pagination links when there are more than the maximum amount of results
        if ($result->size === $this->maxLimit) {
          $previousPage = empty($page) ? 0 : $page - 1;

          // the first link is easy, it is the first page
          $this->addSingleLink($jsonapi, 'first', $baseUrl, 0, 1, $sort);

          // the previous link, checks if !empty, so pages with value 0 will not be displayed
          if (!empty($previousPage)) {
            $this->addSingleLink($jsonapi, 'previous', $baseUrl, 0, $previousPage, $sort);
          }

          // next we check the total count, to see if we can display the last & next link
          $totalCount = $query->fetchTotalCount();
          if (!empty($totalCount)) {
            $lastPage = ceil($totalCount / $this->maxLimit);
            $currentPage = empty($page) ? 1 : $page;

            $this->addSingleLink($jsonapi, 'last', $baseUrl, 0, $lastPage, $sort);

            // let's include some meta data
            $jsonapi->addMeta('count', (int) $result->size);
            $jsonapi->addMeta('total-count', (int) $totalCount);
            $jsonapi->addMeta('page-count', (int) $lastPage);
            $jsonapi->addMeta('page-size', (int) $this->maxLimit);
            $jsonapi->addMeta('page-current', (int) $currentPage);
            if (!empty($previousPage)) {
              $jsonapi->addMeta('page-prev', (int) $previousPage);
            }

            // and finally, we also check if the next page isn't larger than the last page
            $nextPage = empty($page) ? 2 : $page + 1;
            if ($nextPage <= $lastPage) {
              $this->addSingleLink($jsonapi, 'next', $baseUrl, 0, $nextPage, $sort);
              $jsonapi->addMeta('page-next', (int) $nextPage);
            }

            $jsonapi->addMeta('result-row-first', (int) (($currentPage - 1) * $this->maxLimit) + 1);
            $jsonapi->addMeta('result-row-last', (int) $result->size < $this->maxLimit ? ((($currentPage - 1) * $this->maxLimit) + $result->size) : ($currentPage * $this->maxLimit));
          }
        } else if (!empty($limit)) {
          // we must also include pagination links when we have a limit defined
          $previousPage = empty($page) ? 0 : $page - 1;

          // the first link is easy, it is the first page
          $this->addSingleLink($jsonapi, 'first', $baseUrl, $limit, 1, $sort);

          // the previous link, checks if !empty, so pages with value 0 will not be displayed
          if (!empty($previousPage)) {
            $this->addSingleLink($jsonapi, 'prev', $baseUrl, $limit, $previousPage, $sort);
          }

          // next we check the total count, to see if we can display the last & next link
          $totalCount = $query->fetchTotalCount();
          if (!empty($totalCount)) {
            $lastPage = ceil($totalCount / $limit);
            $currentPage = empty($page) ? 1 : $page;

            $this->addSingleLink($jsonapi, 'last', $baseUrl, $limit, $lastPage, $sort);

            // let's include some meta data
            $jsonapi->addMeta('count', (int) $result->size);
            $jsonapi->addMeta('total-count', (int) $totalCount);
            $jsonapi->addMeta('page-count', (int) $lastPage);
            $jsonapi->addMeta('page-size', (int) $limit);
            $jsonapi->addMeta('page-current', (int) $currentPage);
            if (!empty($previousPage)) {
              $jsonapi->addMeta('page-prev', (int) $previousPage);
            }

            // and finally, we also check if the next page isn't larger than the last page
            $nextPage = empty($page) ? 2 : $page + 1;
            if ($nextPage <= $lastPage) {
              $this->addSingleLink($jsonapi, 'next', $baseUrl, $limit, $nextPage, $sort);
              $jsonapi->addMeta('page-next', (int) $nextPage);
            }

            $jsonapi->addMeta('result-row-first', (int) (($currentPage - 1) * $limit) + 1);
            $jsonapi->addMeta('result-row-last', (int) $result->size < $limit ? ((($currentPage - 1) * $limit) + $result->size) : ($currentPage * $limit));
          }
        } else {
          $jsonapi->addMeta('count', (int) $result->size);
          $jsonapi->addMeta('total-count', (int) $result->size);
          $jsonapi->addMeta('page-count', (int) 1);
          $jsonapi->addMeta('page-size', (int) $this->maxLimit);
          $jsonapi->addMeta('page-current', (int) 1);
          $jsonapi->addMeta('result-row-first', (int) 1);
          $jsonapi->addMeta('result-row-last', (int) $result->size);
        }

        // Lets check for our includes
        $includes = [];
        // The url might define includes
        if ($request->query->has('include')) {
          // includes in the url are comma seperated
          $includes = array_merge($includes, explode(',', $request->query->get('include')));
        }

        // But some api handlers provide default includes as well
        if (property_exists($this, 'defaultGetIncludes')) {
          $includes = array_merge($includes, $this->defaultGetIncludes);
        }

        // And finally include them
        if (!empty($includes)) {
          $this->checkForIncludes($result, $jsonapi, $includes);
        }

        // Finally we can set the jsonapi with the data of our result
        $node = $this->getJsonApiNodeForPolymorphicCollection($result);
        $jsonapi->setData($node);
      }
    } else {
      return new Response(null, 405, []);
    }

    return new Response(json_encode($this->serialize($jsonapi)), $responseCode, []);
  }

  /**
   * This method is called to serialize the jsonapi node. you can override it to provide your own implementation
   *
   * @param JsonApiRootNode $jsonapi
   * @return \stdClass
   */
  protected function serialize(JsonApiRootNode $jsonapi): \stdClass
  {
    return $jsonapi->serialize();
  }


  /**
   * Loads the Languages from the Request Accept-Language Headers on the Collection or Model that should be returned in the response
   *
   * @param Request $request
   * @param Collection|Model $object
   * @return void
   */
  protected function loadLanguages(Request $request, $object)
  {
    $object->loadTranslation($request->getLanguages());
  }

  public function post(Request $request): Response
  {
    return new Response(null, 405, []);
  }

  public function patch(Request $request): Response
  {

    return new Response(null, 405, []);
  }

  public function delete(Request $request): Response
  {
    return new Response(null, 405, []);
  }

  /**
   * Adds a link to JsonApiRoot node, this adds meta information. needed to do pagination for example
   *
   * @param JsonApiRootNode $jsonapi
   * @param string $name
   * @param string $baseUrl
   * @param integer $limit
   * @param integer $page
   * @param string $sort
   * @return MultiModelApiHandler
   */
  protected function addSingleLink(JsonApiRootNode $jsonapi, string $name, string $baseUrl, ?int $limit = 0, ?int $page = 0, ?string $sort = null): MultiModelApiHandler
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
   * Add includes to the JsonApiRootNode based on the indlues in the query or in the api handler.
   * Includes are other related Models that are also serialized to a jsonapi.org document
   *
   * @param Collection|Model $source
   * @param JsonApiRootNode $jsonApiRootNode
   * @param array $relationshipNamesToInclude
   * @return MultiModelApiHandler
   */
  protected function checkForIncludes($source, JsonApiRootNode $jsonApiRootNode, array $relationshipNamesToInclude): MultiModelApiHandler
  {
    if (!empty($source) && !$source->isEmpty) {
      $modelClassName = $this->modelClassName;
      $fetchedCollections = []; // we will cache collections here, so we don't get duplicate data to include when multiple relationships point to the same object
      foreach ($relationshipNamesToInclude as $relationshipNameToInclude) {
        $hasRelationship = $modelClassName::hasDeepRelationship($relationshipNameToInclude);
        if ($hasRelationship) {
          // Before anything else, we check if the user has access to the data
          $deepRelationship = $modelClassName::getDeepRelationship($relationshipNameToInclude);
          $entityQuery = $this->getEntityQueryForIncludedRelationship($deepRelationship, $relationshipNameToInclude);

          // Now we check permissions
          if ($deepRelationship instanceof FieldRelationship) {
            // A field relationship can be polymorphic, only if the user has access to all types, can we allow the include
            if ($deepRelationship->isPolymorphic) {
              // We need to check which types we have access to
              // Because the relationship is polymorphic, we have to test each type
              $allowedBundles = [];
              foreach ($deepRelationship->polymorphicModelTypes as $deepRelationshipModelClassName) {
                // Lets preemtively create an entity query, in order to copy the conditions from later, when it turns out we dont have access to all types
                if ($deepRelationshipModelClassName::userHasReadPermission() && !empty($deepRelationshipModelClassName::bundle())) {
                  $allowedBundles[] = $deepRelationshipModelClassName::bundle();
                }
              }

              // Lets check if we have access to everything
              if (sizeof($allowedBundles) !== sizeof($deepRelationship->polymorphicModelTypes)) {
                // Unfortunately the user cant access every type, so lets see which ones do work
                if (sizeof($allowedBundles) === 0) {
                  // Nothing works, skip this relationship entirely
                  continue;
                } else {
                  // This is the tricky part, we must now filter on which types we have access on, and which we dont
                  // We add a condition to the entityquery created above, this will be passed in the fetch
                  // to make sure the results are only those of the types the user has access to
                  $entityQuery->addCondition(new Condition('type', 'IN', $allowedBundles));
                }
              }
            } else {
              $deepRelationshipModelClassName = $deepRelationship->modelType;
              if (!$deepRelationshipModelClassName::userHasReadPermission()) {
                // No access to model class, skip the include
                continue;
              }
            }
          } else if ($deepRelationship instanceof ReferencedRelationship) {
            $deepRelationshipModelClassName = $deepRelationship->modelType;
            if (!$deepRelationshipModelClassName::userHasReadPermission()) {
              // No access to model class, skip the include
              continue;
            }
          } else {
            continue;
          }

          // first of all, we fetch the data
          // We also pass in a possible entity query, in case we get conditions on the relationships we want to query
          // in case the query = null, everything will be fetched
          $source->fetch($relationshipNameToInclude, $entityQuery);
          $fetchedObject = $source->get($relationshipNameToInclude);
          // We don't know yet if this is a Collection or a Model we just fetched,
          // as the source we fetched it from can be both as well
          if ($fetchedObject instanceof Collection) {
            $fetchedCollection = $fetchedObject;
            if (!$fetchedCollection->isEmpty) {
              foreach ($fetchedCollection as $model) {
                // watch out, we can't use $relationship->modelType, because that doesn't work for polymorphic relationships
                $relationshipType = $model->getModelName();
                // Here we check if we already fetched data of the same type
                if (!array_key_exists($relationshipType, $fetchedCollections)) {
                  // we haven't fetched this type yet, lets cache it in case we do later
                  $fetchedCollections[$relationshipType] = $fetchedCollection;
                }

                // we do it this way, because we might have fetched records of the same type before
                // this way we cache collections based on type in the $fetchedCollections, and put new records on there
                $previouslyFetchedCollection = $fetchedCollections[$relationshipType];
                // luckally for us, collection->put() handles duplicates by checking for id
                $previouslyFetchedCollection->put($model);
              }
            }
          } else if ($fetchedObject instanceof Model) {
            $fetchedModel = $fetchedObject;
            if (!empty($fetchedModel)) {
              // watch out, we can't use $relationship->modelType, because that doesn't work for polymorphic relationships
              $relationshipType = $fetchedModel->getModelName();
              // now we check if we already included objects of the same type
              if (!array_key_exists($relationshipType, $fetchedCollections)) {
                // we haven't fetched this type yet, lets cache it in case we do later
                $fetchedCollections[$relationshipType] = Collection::forgeNew($relationshipType);
              }

              $previouslyFetchedCollection = $fetchedCollections[$relationshipType];
              $previouslyFetchedCollection->put($fetchedModel);
            }
          }
        }
      }

      // now that we cached the collections, it's just a matter of looping them, and including the data in our response
      foreach ($fetchedCollections as $fetchedCollection) {
        if (!$fetchedCollection->isEmpty) {
          $serializedCollection = $fetchedCollection->getJsonApiNode();
          $jsonApiRootNode->addInclude($serializedCollection);
        }
      }
    }

    return $this;
  }

  /**
   * This function gives you the ability to overwrite the base relationship used to query certain included relationships with
   * This gives you the opportunity to limit the results of certain relationships.
   *
   * @param Relationship $relationship The relationship that is being queried
   * @param string $relationshipPath The full (deep) path of the relationship that was most likely added to the query param in the included array
   * @return EntityQuery The returned query where all the Conditions, Base Conditions and ConditionGroups will be copied from
   */
  protected function getEntityQueryForIncludedRelationship(Relationship $relationship, string $relationshipPath): EntityQuery
  {
    return $relationship->getRelationshipQuery();
  }

  /**
   * This method parses a filter array (which is generally found in the query parameter of the request)
   * To an array of Conditions that can be used to query the database for results
   *
   * @param string[] $modelClassNames
   * @param array $filter
   * @return Condition[]
   */
  public static function getConditionListForFilterArray(array $modelClassNames, array $filters): array
  {
    $othersPrettyFieldsToFieldsMap = [];

    // We get all the field mappings for every model class name, the filters must be applicable to every model class for it to work
    foreach ($modelClassNames as $modelClassName) {
      $othersPrettyFieldsToFieldsMap[$modelClassName] = $modelClassName::getPrettyFieldsToFieldsMapping();
    }

    // We take the first and use that to build the conditions array
    $prettyToFieldsMap = $othersPrettyFieldsToFieldsMap[$modelClassNames[0]];
    unset($othersPrettyFieldsToFieldsMap[$modelClassNames[0]]);

    $conditions = [];

    foreach ($filters as $filter) {
      if (is_array($filter) && array_key_exists('field', $filter)) {
        // lets start by making sure the field exists
        // we explode, because we have a potential field with a column (like address.city) as opposed to just a field (like name)
        $prettyFieldParts = explode('.', $filter['field']);
        if (array_key_exists($prettyFieldParts[0], $prettyToFieldsMap)) {
          // The field exists on the first modelClassName, now lets check the rest
          foreach ($othersPrettyFieldsToFieldsMap as $otherPrettyFieldsToFieldsMap) {
            if (!array_key_exists($prettyFieldParts[0], $otherPrettyFieldsToFieldsMap)) {
              // This filter does not apply to all modelclasses, lets skip it
              continue 2; // continue the outer foreach and process the next filter
            }
          }

          $field = $prettyToFieldsMap[$prettyFieldParts[0]];
          $operator = array_key_exists('operator', $filter) ? $filter['operator'] : '=';
          $value = array_key_exists('value', $filter) ? $filter['value'] : null;
          $id = array_key_exists('id', $filter) ? $filter['id'] : null;

          $isMultiValueOperator = Condition::isValidMultipleModelsOperator($operator);
          $operator = (Condition::isValidSingleModelOperator($operator) || $isMultiValueOperator) ? $operator : '=';

          if ($isMultiValueOperator) {
            $value = isset($value) ? explode(',', $value) : [];
            $id = isset($id) ? explode(',', $id) : [];
          }

          // Now that we filtered everything out the filter arrays, we can build our actual Condition
          if (!empty($operator) && !empty($field) && (!empty($value) || !empty($id) || $value === '0' || $id === '0')) {
            // Since either the value, or the ID can be passed, we first check what we found in the filter
            // This is only needed for Entity_reference fields, where you can filter on the title of the related object through "value"
            // Or on the ID of the object through the "ID"
            if (!empty($id) || $id === '0') {
              // ID is more important than value, so we check it first
              // An ID cant have a seperate column, so no need to check for that, we can just return the condition
              $condition = new Condition($field, $operator, $id);
              $conditions[] = $condition;
            } else {
              // No ID passed, we can check for value
              $fieldDefinition = $modelClassName::getFieldDefinition($field);
              $fieldType = $fieldDefinition->getType();

              // First We must check if it is a single value field. Or a column
              if (sizeof($prettyFieldParts) > 1) {
                // More than 1 value in the field parts, so we can assume an extra column is present
                $typePrettyToFieldsMap = Model::getTypePrettyFieldToFieldsMapping();

                // Only certain columns are allowed to filter on, we check if the type is present, and the column is allowed
                if (array_key_exists($fieldType, $typePrettyToFieldsMap) && array_key_exists($prettyFieldParts[1], $typePrettyToFieldsMap[$fieldType])) {
                  $column = $typePrettyToFieldsMap[$fieldType][$prettyFieldParts[1]];
                  $condition = new Condition($field . '.' . $column, $operator, $value);
                  $conditions[] = $condition;
                }
              } else {
                // just a field, no column (like name)

                // Lets check for the type, because if the type is an entity reference, we will filter on the title of the referenced entity
                if ($fieldType === 'entity_reference' || $fieldType === 'entity_reference_revisions') {
                  // Because the user entity works differently than any other, we must also check for the target_type, and use a different column
                  $settings = $fieldDefinition->getSettings();
                  if ($settings['target_type'] === 'user') {
                    $condition = new Condition($field . '.entity.name', $operator, $value);
                    $conditions[] = $condition;
                  } else if ($settings['target_type'] === 'user_role') {
                    // TODO fix this, currently getting exception "Getting the base fields is not supported for entity type user_role"
                    // works without entity.title appended
                    $condition = new Condition($field, $operator, $value);
                    $conditions[] = $condition;
                  } else {
                    $condition = new Condition($field . '.entity.title', $operator, $value);
                    $conditions[] = $condition;
                  }
                } else {
                  // Just any other field type
                  $condition = new Condition($field, $operator, $value);
                  $conditions[] = $condition;
                }
              }
            }
          }
        }
      }
    }

    return $conditions;
  }

  /**
   * This method checks if there is a listview parameter in the filter array and fetches it from the DB
   *
   * @param string $entityType
   * @param array $filter
   * @return ListViewInterface|null
   */
  public static function getListViewForFilterArray(string $entityType, array $filter): ?ListViewInterface
  {
    if (array_key_exists('_listview', $filter)) {
      $listViewParameterValue = $filter['_listview'];
      if (!empty($listViewParameterValue) && is_numeric($listViewParameterValue)) {

        /** @var AnalyticsServiceInterface $analyticsService */
        $analyticsService = \Drupal::service(AnalyticsServiceInterface::SERVICE_NAME);
        $listview = $analyticsService->getListViewById($listViewParameterValue);

        if (!empty($listview) && $listview->getEntityName() === $entityType) {
          return $listview;
        }
      }
    }

    return null;
  }

  /**
   * This method returns an array of sort orders found in the sort array (generally passed in the query parameters of the request)
   *
   * @param string[] $modelClassName
   * @param array $sortQueryFields
   * @return Order[]
   */
  public static function getSortOrderListForSortArray(array $modelClassNames, array $sortQueryFields): array
  {
    $sortOrders = [];

    // In this array we hold the column to field mapping. And for each modelclass we check it to make sure we can sort on the field/column combination or not.
    $sortOrderFieldColumns = [];

    foreach ($modelClassNames as $modelClassName) {
      $prettyToFieldsMap = $modelClassName::getPrettyFieldsToFieldsMapping();

      foreach ($sortQueryFields as $sortQueryField) {
        $sortOrder = null;
        $field = null;
        $column = null;

        // the json-api spec tells us, that all fields are sorted ascending, unless the field is prepended by a '-'
        // http://jsonapi.org/format/#fetching-sorting
        $direction = (!empty($sortQueryField) && $sortQueryField[0] === '-') ? 'DESC' : 'ASC';
        $prettyField = ltrim($sortQueryField, '-'); // lets remove the '-' from the start of the field if it exists

        $prettyFieldParts = explode('.', $prettyField);

        // if the pretty field exists, lets add it to the sort order
        if (array_key_exists($prettyFieldParts[0], $prettyToFieldsMap)) {
          $field = $prettyToFieldsMap[$prettyFieldParts[0]];
          $fieldDefinition = $modelClassName::getFieldDefinition($field);
          $fieldType = $fieldDefinition->getType();

          if (sizeof($prettyFieldParts) > 1) // meaning we have a extra column present
          {
            // Only certain types are allowed to sort on a different column
            $typePrettyToFieldsMap = $modelClassName::getTypePrettyFieldToFieldsMapping();

            if (array_key_exists($fieldType, $typePrettyToFieldsMap) && array_key_exists($prettyFieldParts[1], $typePrettyToFieldsMap[$fieldType])) {
              $column = $typePrettyToFieldsMap[$fieldType][$prettyFieldParts[1]];
              $sortOrder = new Order($field . '.' . $column, $direction);
            }
          } else {
            if ($fieldType === 'entity_reference' || $fieldType === 'entity_reference_revisions') {
              // In case the field type is entity reference, we want to sort by the title, not the ID
              // Because the user entity works differently than any other, we must also check for the target_type
              $settings = $fieldDefinition->getSettings();
              if ($settings['target_type'] === 'user') {
                $column = 'entity.name';
                $sortOrder = new Order($field . '.' . $column, $direction);
              } else {
                $column = 'entity.title';
                $sortOrder = new Order($field . '.' . $column, $direction);
              }
            } else {
              // Any other field, can be sorted like normal
              $column = null;
              $sortOrder = new Order($field, $direction);
            }
          }
        }

        if (!empty($sortOrder)) {
          if (!array_key_exists($field, $sortOrderFieldColumns) || $sortOrderFieldColumns[$field] === $column) {
            $sortOrders[] = $sortOrder;
            $sortOrderFieldColumns[$field] = $column;
          }
        }
      }
    }

    return $sortOrders;
  }

  /**
   * Get the fully qualified classname of the modelclass of this apihandler
   *
   * @return string[]
   */
  protected function getModelClassNames(): array
  {
    return $this->modelClassNames;
  }

  /**
   * Removes all the model class names from the api handler
   *
   * @return MultiModelApiHandler
   */
  protected function clearModelClassNames(): MultiModelApiHandler
  {
    $this->modelClassNames = [];
    return $this;
  }
}
