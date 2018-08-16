<?php

namespace Drupal\spectrum\Rest;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Query\ConditionGroup;
use Drupal\spectrum\Query\Order;
use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Serializer\JsonApiBaseNode;
use Drupal\spectrum\Serializer\JsonApiLink;
use Drupal\spectrum\Analytics\ListView;

use Drupal\spectrum\Exceptions\InvalidTypeException;
use Drupal\spectrum\Exceptions\NotImplementedException;
use Drupal\spectrum\Exceptions\ModelNotFoundException;

/**
 * This class provides an implementation of an BaseApiHandler for a Model in a jsonapi.org compliant way
 */
class ModelApiHandler extends BaseApiHandler
{
  /**
   * Embedded API relationships for this ApiHandler, this provides an extension to the jsonapi.org spec, by giving the opportunity to
   * save or update a model record plus any related other records
   * The key of the array will be the key in the jsonapi.org hash that contains the embedded jsonapi.org document(s)
   * The value of the array must be a string that matches a relationshipname on the model
   *
   * @var array
   */
  protected static $embeddedApiRelationships = [];

  /**
   * The fully qualified classname of the model you want to use in this apihandler
   *
   * @var string
   */
  private $modelClassName;

  /**
   * The default maxlimit for a result, to make sure we dont return everything in the database, but paginate results
   *
   * @var integer
   */
  protected $maxLimit = 200;


  /**
   * Base conditions that will be added to all queries done in the api handler
   *
   * @var array
   */
  protected $baseConditions = [];

  /**
   * condition groups that will be added to all queries done in the api handler
   *
   * @var array
   */
  protected $conditionGroups = [];

  /**
   * @param string $modelClassName The fully qualified classname of the model you want to use in this apihandler
   * @param string|int|null $slug
   */
  public function __construct(string $modelClassName, $slug = null)
  {
    parent::__construct($slug);
    $this->modelClassName = Model::getModelClassForEntityAndBundle($modelClassName::$entityType, $modelClassName::$bundle);
    $this->defaultHeaders['Content-Type'] = 'application/vnd.api+json';
  }

  /**
   * Add a ConditionGroup that will be applied to all queries
   *
   * @param ConditionGroup $conditionGroup
   * @return ModelApiHandler
   */
  protected final function addConditionGroup(ConditionGroup $conditionGroup) : ModelApiHandler
  {
    $this->conditionGroups[] = $conditionGroup;
    return $this;
  }

  /**
   * Add a base condition that will be applied to all queries in this apihandler
   *
   * @param Condition $condition
   * @return ModelApiHandler
   */
  protected final function addBaseCondition(Condition $condition) : ModelApiHandler
  {
    $this->baseConditions[] = $condition;
    return $this;
  }

  /**
   * This function can be used to change the JsonApi.org result before serializing it and returned in the response.
   *
   * @param Collection|Model $object
   * @return JsonApiBaseNode
   */
  protected function getJsonApiNodeForModelOrCollection($object) : JsonApiBaseNode
  {
    return $object->getJsonApiNode();
  }

  /**
   * This method executes get functionality for the Rest call. If a slug is provided, 1 result will be fetched from the database
   * If no slug was provided a list of results will be returend.
   * Permissions to the API will be checked, and fields will be filtered to only include the fields where the user has access to
   *
   * @param Request $request
   * @return Response
   */
  public function get(Request $request) : Response
  {
    $modelClassName = $this->modelClassName;
    $query = $modelClassName::getModelQuery();
    $limit = 0;
    $page = 0;
    $sort = '';
    $jsonapi = new JsonApiRootNode();

    // Before anything, we check if the user has permission to access this content
    if(!$modelClassName::userHasReadPermission())
    {
      // No access, return a 405 response
      return new Response(null, 405, array());
    }

    // We start by adding the link to this request
    $baseUrl = $request->getSchemeAndHttpHost() . $request->getPathInfo(); // this might not work with a different port than 80, check later

    // Get requests can either be a list of models, or an individual model, so we must check the slug
    $responseCode = 200;
    if(empty($this->slug))
    {
      // when we don't have a slug, we are expected to always return an array response,
      // even when the result is a single object
      $jsonapi->asArray(true);

      // when the slug is empty, we must check for extra variables
      if($request->query->has('limit') && is_numeric($request->query->get('limit')))
      {
        $limit = $request->query->get('limit');
      }

      // Additional check for the page variable, we potentially need to adjust the query start
      if($request->query->has('page') && is_numeric($request->query->get('page')))
      {
        $page = $request->query->get('page');

        if(!empty($limit))
        {
          $start = ($page-1) * $limit;
          $query->setRange($start, $limit);
        }
        else
        {
          $start = ($page-1) * $this->maxLimit;
          $query->setRange($start, $this->maxLimit);
        }
      }
      else
      {
        // no page, we can just set a limit
        $query->setLimit(empty($limit) ? $this->maxLimit : $limit);
      }

      // Lets also check for a sort order
      if($request->query->has('sort'))
      {
        // sort params are split by ',' so lets evaluate them individually
        $sort = $request->query->get('sort');
        $sortQueryFields = explode(',', $sort);
        $sortOrders = static::getSortOrderListForSortArray($modelClassName, $sortQueryFields);

        foreach($sortOrders as $sortOrder)
        {
          $query->addSortOrder($sortOrder);
        }
      }

      // We check for base conditions (these are conditions that always need to be applied, regardless of the api)
      // This can be used to limit the results based on the logged in user, when the user only has access to certain records
      if(sizeof($this->baseConditions) > 0)
      {
        foreach($this->baseConditions as $condition)
        {
          $query->addBaseCondition($condition);
        }
      }

      // Next we also do the same for the condition groups
      if(sizeof($this->conditionGroups) > 0)
      {
        foreach($this->conditionGroups as $conditionGroup)
        {
          $query->addConditionGroup($conditionGroup);
        }
      }

      // Before we fetch the collection, lets check for filters
      if($request->query->has('filter'))
      {
        $filter = $request->query->get('filter');
        if(is_array($filter))
        {
          // We first get the filters provided in the filter array
          $conditions = static::getConditionListForFilterArray($modelClassName, $filter);

          // Next we check if the filters require filterlogic
          if(array_key_exists('_logic', $filter))
          {
            $logic = $filter['_logic'];
            $query->setConditionLogic($logic);
          }

          // And apply the conditions
          foreach($conditions as $condition)
          {
            $query->addCondition($condition);
          }

          // Lets see if a listView was passed and found (done in the conditionListforfliterarray function)
          $listview = static::getListViewForFilterArray($modelClassName, $filter);
          if(!empty($listview))
          {
            $listview->fetch('conditions');
            $listview->fetch('sort_orders');
            // a matching listview was found
            $listviewQuery = $listview->buildQuery();

            foreach($listviewQuery->conditions as $condition)
            {
              $query->addCondition($condition);
            }

            foreach($listviewQuery->sortOrders as $sortOrder)
            {
              if(!$query->hasSortOrderForField($sortOrder->fieldName))
              {
                $query->addSortOrder($sortOrder);
              }
            }
          }
        }
      }

      // Here we build the links of the request
      $this->addSingleLink($jsonapi, 'self', $baseUrl, $limit, $page, $sort); // here we add the self link
      $result = $query->fetchCollection();

      if(!$result->isEmpty)
      {
        // we must include pagination links when there are more than the maximum amount of results
        if($result->size === $this->maxLimit)
        {
          $previousPage = empty($page) ? 0 : $page-1;

          // the first link is easy, it is the first page
          $this->addSingleLink($jsonapi, 'first', $baseUrl, 0, 1, $sort);

          // the previous link, checks if !empty, so pages with value 0 will not be displayed
          if(!empty($previousPage))
          {
            $this->addSingleLink($jsonapi, 'previous', $baseUrl, 0, $previousPage, $sort);
          }

          // next we check the total count, to see if we can display the last & next link
          $totalCount = $query->fetchTotalCount();
          if(!empty($totalCount))
          {
            $lastPage = ceil($totalCount / $this->maxLimit);
            $currentPage = empty($page) ? 1 : $page;

            $this->addSingleLink($jsonapi, 'last', $baseUrl, 0, $lastPage, $sort);

            // let's include some meta data
            $jsonapi->addMeta('count', (int)$result->size);
            $jsonapi->addMeta('total-count', (int)$totalCount);
            $jsonapi->addMeta('page-count', (int)$lastPage);
            $jsonapi->addMeta('page-size', (int)$this->maxLimit);
            $jsonapi->addMeta('page-current', (int)$currentPage);
            if(!empty($previousPage))
            {
              $jsonapi->addMeta('page-prev', (int)$previousPage);
            }

            // and finally, we also check if the next page isn't larger than the last page
            $nextPage = empty($page) ? 2 : $page+1;
            if($nextPage <= $lastPage)
            {
              $this->addSingleLink($jsonapi, 'next', $baseUrl, 0, $nextPage, $sort);
              $jsonapi->addMeta('page-next', (int)$nextPage);
            }

            $jsonapi->addMeta('result-row-first', (int)(($currentPage-1) * $this->maxLimit) +1 );
            $jsonapi->addMeta('result-row-last', (int)$result->size < $this->maxLimit ? ((($currentPage-1) * $this->maxLimit) + $result->size) : ($currentPage * $this->maxLimit));
          }
        }
        else if(!empty($limit))
        {
          // we must also include pagination links when we have a limit defined
          $previousPage = empty($page) ? 0 : $page-1;

          // the first link is easy, it is the first page
          $this->addSingleLink($jsonapi, 'first', $baseUrl, $limit, 1, $sort);

          // the previous link, checks if !empty, so pages with value 0 will not be displayed
          if(!empty($previousPage))
          {
            $this->addSingleLink($jsonapi, 'prev', $baseUrl, $limit, $previousPage, $sort);
          }

          // next we check the total count, to see if we can display the last & next link
          $totalCount = $query->fetchTotalCount();
          if(!empty($totalCount))
          {
            $lastPage = ceil($totalCount / $limit);
            $currentPage = empty($page) ? 1 : $page;

            $this->addSingleLink($jsonapi, 'last', $baseUrl, $limit, $lastPage, $sort);

            // let's include some meta data
            $jsonapi->addMeta('count', (int)$result->size);
            $jsonapi->addMeta('total-count', (int)$totalCount);
            $jsonapi->addMeta('page-count', (int)$lastPage);
            $jsonapi->addMeta('page-size', (int)$limit);
            $jsonapi->addMeta('page-current', (int)$currentPage);
            if(!empty($previousPage))
            {
              $jsonapi->addMeta('page-prev', (int)$previousPage);
            }

            // and finally, we also check if the next page isn't larger than the last page
            $nextPage = empty($page) ? 2 : $page+1;
            if($nextPage <= $lastPage)
            {
              $this->addSingleLink($jsonapi, 'next', $baseUrl, $limit, $nextPage, $sort);
              $jsonapi->addMeta('page-next', (int)$nextPage);
            }

            $jsonapi->addMeta('result-row-first', (int)(($currentPage-1) * $limit) +1 );
            $jsonapi->addMeta('result-row-last', (int)$result->size < $limit ? ((($currentPage-1) * $limit) + $result->size) : ($currentPage * $limit));
          }
        }
        else
        {
          $jsonapi->addMeta('count', (int)$result->size);
          $jsonapi->addMeta('total-count', (int)$result->size);
          $jsonapi->addMeta('page-count', (int)1);
          $jsonapi->addMeta('page-size', (int)$this->maxLimit);
          $jsonapi->addMeta('page-current', (int)1);
          $jsonapi->addMeta('result-row-first', (int)1);
          $jsonapi->addMeta('result-row-last', (int)$result->size);
        }

        // Lets check for our includes
        $includes = array();
        // The url might define includes
        if($request->query->has('include'))
        {
          // includes in the url are comma seperated
          $includes = array_merge($includes, explode(',', $request->query->get('include')));
        }

        // But some api handlers provide default includes as well
        if(property_exists($this, 'defaultGetIncludes'))
        {
          $includes = array_merge($includes, $this->defaultGetIncludes);
        }

        // And finally include them
        if(!empty($includes))
        {
          $this->checkForIncludes($result, $jsonapi, $includes);
        }

        // Finally we can set the jsonapi with the data of our result
        $node = $this->getJsonApiNodeForModelOrCollection($result);
        $jsonapi->setData($node);
      }
    }
    else
    {
      // We check for base conditions (these are conditions that always need to be applied, regardless of the api)
      // This can be used to limit the results based on the logged in user, when the user only has access to certain records
      if(sizeof($this->baseConditions) > 0)
      {
        foreach($this->baseConditions as $condition)
        {
          $query->addBaseCondition($condition);
        }
      }

      // Next we also do the same for the condition groups
      if(sizeof($this->conditionGroups) > 0)
      {
        foreach($this->conditionGroups as $conditionGroup)
        {
          $query->addConditionGroup($conditionGroup);
        }
      }

      // Next we add the specific condition for the slug
      $query->addCondition(new Condition($modelClassName::$idField, '=', $this->slug));
      $result = $query->fetchSingleModel();

      $this->addSingleLink($jsonapi, 'self', $baseUrl);
      if(!empty($result))
      {
        // Lets check for our includes
        $includes = array();
        // The url might define includes
        if($request->query->has('include'))
        {
          // includes in the url are comma seperated
          $includes = array_merge($includes, explode(',', $request->query->get('include')));
        }

        // But some api handlers provide default includes as well
        if(property_exists($this, 'defaultGetIncludes'))
        {
          $includes = array_merge($includes, $this->defaultGetIncludes);
        }

        // And finally include them
        if(!empty($includes))
        {
          $this->checkForIncludes($result, $jsonapi, $includes);
        }

        // Finally we add the result
        $jsonapi->addNode($this->getJsonApiNodeForModelOrCollection($result));
      }
      else
      {
        // We dont have to set the content, jsonapi responds with an empty result out of the box
        $responseCode = 404;
      }
    }

    return new Response(json_encode($this->serialize($jsonapi)), $responseCode, array());
  }

  /**
   * This method is called to serialize the jsonapi node. you can override it to provide your own implementation
   *
   * @param JsonApiRootNode $jsonapi
   * @return \stdClass
   */
  protected function serialize(JsonApiRootNode $jsonapi) : \stdClass
  {
    return $jsonapi->serialize();
  }

  /**
   * This method is called before the model will be validated in a post, giving you the opportunity to do override functionality per ApiHandler
   *
   * @param Model $model
   * @return Model
   */
  protected function beforePostValidate(Model $model) : Model
  {
    return $model;
  }

  /**
   * This method is called before the model will be saved in a post, giving you the opportunity to do override functionality per ApiHandler
   *
   * @param Model $model
   * @return Model
   */
  protected function beforePostSave(Model $model) : Model
  {
    return $model;
  }

  /**
   * This method is called after the model is saved in a post, giving you the opportunity to do override functionality per ApiHandler
   *
   * @param Model $model
   * @return Model
   */
  protected function afterPostSave(Model $model) : Model
  {
    return $model;
  }

  /**
   * This method executes post functionality for the Rest call. Slugs cannot be provided
   * A call to this method will insert a new model in the database.
   * Permissions to the API will be checked, and fields will be filtered to only allow to fill in the fields where the user has access to
   *
   * @param Request $request
   * @return Response
   */
  public function post(Request $request) : Response
  {
    $modelClassName = $this->modelClassName;
    $response;
    $responseCode;

    // Before anything, we check if the user has permission to access this content
    if(!$modelClassName::userHasCreatePermission())
    {
      // No access, return a 405 response
      return new Response(null, 405, array());
    }

    $jsonapidocument = json_decode($request->getContent());
    if(!empty($jsonapidocument->data->type))
    {
      // First we'll build the root model from the json api document
      // since we're talking about a post here, it's always a create, a new model
      $model = $modelClassName::forgeNew();
      // here we fill in the attributes on the new model from the json api document
      $model->applyChangesFromJsonAPIDocument($jsonapidocument);
      // we trigger the beforeValidate as we might need to trigger some functionalitity
      // before doing the validation and potentially sending back incorrect errors
      $model->beforeValidate();
      $model = $this->beforePostValidate($model);
      // next we do the validation, in the return object we get a potential error document
      $model->constraints();
      $validation = $model->validate();

      // Next we'll check for included relationships
      // We start of by getting the none default keys from the data attribute in the jsonapi document
      // this isn't part of the jsonapi spec unfortunatly, but for now this is the only way to embed records in a POST or a PATCH
      // the functionality is currently based on the DS.EmbeddedRecordsMixin of Ember
      // But will be rewritten once JsonAPI includes embedding in the spec
      $referencedRelationshipsToSave = [];
      $fieldRelationshipsToSave = [];

      $noneDefaultKeys = JsonApiRootNode::getNoneDefaultDataKeys($jsonapidocument);
      foreach($noneDefaultKeys as $noneDefaultKey)
      {
        // It's not because there is a key in the json api document that isn't default,
        // that we can just assume it's in included relationship
        // The relationship must also be defined in the embeddedApiRelationships array on the model
        if(array_key_exists($noneDefaultKey, static::$embeddedApiRelationships))
        {
          // Lets get the relationship from the model
          $includedRelationshipName = static::$embeddedApiRelationships[$noneDefaultKey];
          $includedRelationship = $modelClassName::getRelationship($includedRelationshipName);

          // We get the inline data from the json document
          $inlineData = $jsonapidocument->data->$noneDefaultKey;
          if($includedRelationship instanceof FieldRelationship)
          {
            // Lets do some unsupported checks first
            if($includedRelationship->fieldCardinality === 1 && !$includedRelationship->isPolymorphic)
            {
              // And the json spec says there should always be a type included
              if(!empty($inlineData->data->type))
              {
                $fieldRelationshipsToSave[] = $includedRelationshipName;
                // we get the modeltype for the relationship, because we will need to forge a model later
                $includedModelType = $includedRelationship->modelType;
                $includedModel;

                // Either this is a new record, when no ID was provided => insert
                // or the included record exists in the db already, and it's id was provided => update
                if(empty($inlineData->data->id))
                {
                  // new record, we create a new model
                  $includedModel = $includedModelType::forgeNew();
                }
                else
                {
                  // existing record, lets get it from the database
                  $includedModel = $includedModelType::forgeById($inlineData->data->id);

                  if(empty($includedModel))
                  {
                    throw new ModelNotFoundException('The model with ID '.$inlineData->data->id.' does not exist in the database while trying to perform an inline save with a id passed (inline type '.$noneDefaultKey.')');
                  }
                }

                // We apply the changes from the json document to the model (both new and existing, the fields will be updated the same way)
                $includedModel->applyChangesFromJsonAPIDocument($inlineData);

                // now we validate the model
                $includedModel->beforeValidate();
                $includedModel->constraints();
                $fieldRelationshipValidation = $includedModel->validate();

                // we musn't forget, that both the parens and the children aren't persisted in the database yet
                // because of models and collections, the structure is kept for saving, but it is impossible to validate a potential required parent for a child
                // that is why we will add an ignore on the relationship field for the current model for a NotNullConstraint
                $validation->addIgnore($includedRelationship->getField(), 'Drupal\Core\Validation\Plugin\Validation\Constraint\NotNullConstraint');

                // the first argument sets the path in the validation
                $validation->addIncludedValidation('/'.$noneDefaultKey, $fieldRelationshipValidation);

                // And finally put the included model on the model
                $model->put($includedRelationship, $includedModel);
              }
              else
              {
                throw new InvalidTypeException('Tried to save an inline Field relationship that did not contain a type in the jsonapi document type hash');
              }
            }
            else
            {
              // Either the cardinality is greater than 1 (multiple values for a field)
              // Or the relationship is polymorphic (more than 1 type)
              throw new NotImplementedException('Including polymorhpic parent relationships or relationships with the field cardinality greater than 1 is currently not supported');
            }
          }
          else if($includedRelationship instanceof ReferencedRelationship)
          {
            // Next we will loop every included model in the included relationship in the jsonapidocument
            // in order to create a new child model, apply the attributes from the json api document, and validate each one
            foreach ($inlineData as $inlineCount => $inlineJsonApiDocument)
            {
              $referencedRelationshipsToSave[] = $includedRelationshipName;
              // we put a new child model on the just created model
              $childModel = $model->putNew($includedRelationship);

              // and apply all the attributes from the json document
              $childModel->applyChangesFromJsonAPIDocument($inlineJsonApiDocument);

              // and lets validate it as well
              $childModel->beforeValidate();
              $childModel->constraints();
              $childValidation = $childModel->validate();
              // we musn't forget, that both the parents and the children aren't persisted in the database yet
              // because of models and collections, the structure is kept for saving, but it is impossible to validate a potential required parent for a child
              // that is why we will add an ignore on the relationship field for the child for a NotNullConstraint
              $childValidation->addIgnore($includedRelationship->fieldRelationship->getField(), 'Drupal\Core\Validation\Plugin\Validation\Constraint\NotNullConstraint');
              // the first argument sets the path in the validation
              // we must keep track of the position in the array, this must reflect in the path
              $validation->addIncludedValidation('/'.$noneDefaultKey.'/'.$inlineCount, $childValidation);
            }
          }
        }
      }

      // Depending on the result of the validation, let's send the the proper result
      if($validation->hasSucceeded())
      {
        // We call our beforeSave hook, so we can potentially fetch some relationships for the before hooks of the model
        $model = $this->beforePostSave($model);

        // No errors, we can save, and return the newly created model serialized
        $jsonapi = new JsonApiRootNode();

        // Before saving the model, and the referenced relationships,
        // we must save the field relationships, as they might need to be put on the model
        $uniqueFieldRelationshipsToSave = array_unique($fieldRelationshipsToSave);
        foreach($uniqueFieldRelationshipsToSave as $fieldRelationshipToSave)
        {
          $model->save($fieldRelationshipToSave);
        }

        // Here we save the model itself
        $model->save();

        // We must also save potential included referenced relationships
        $uniqueReferencedRelationshipsToSave = array_unique($referencedRelationshipsToSave);
        foreach($uniqueReferencedRelationshipsToSave as $referencedRelationshipToSave)
        {
          $model->save($referencedRelationshipToSave);
        }

        // We call our afterSave hook, so we can potentially do some actions based on the newly created model
        $model = $this->afterPostSave($model);

        // we serialize the response
        $jsonapi->addNode($this->getJsonApiNodeForModelOrCollection($model));

        // Lets check for our includes
        $includes = array_merge($uniqueReferencedRelationshipsToSave, $uniqueFieldRelationshipsToSave);

        // The url might define includes
        if($request->query->has('include'))
        {
          // includes in the url are comma seperated
          $includes = array_merge($includes, explode(',', $request->query->get('include')));
        }

        // But some api handlers provide default includes as well
        if(property_exists($this, 'defaultPostIncludes'))
        {
          $includes = array_merge($includes, $this->defaultPostIncludes);
        }

        // And finally include them
        if(!empty($includes))
        {
          $this->checkForIncludes($model, $jsonapi, $includes);
        }

        // and finally we can serialize and set the code
        $response = $this->serialize($jsonapi);
        $responseCode = 200;
      }
      else
      {
        // Unfortunatly we have some erros, let's serialize the error object, and set the proper response code
        $response = $validation->serialize();
        $responseCode = 422;
      }
    }
    else
    {
      // No type, cannot be parsed
      unset($response);
      $responseCode = 404;
    }

    return new Response(isset($response) ? json_encode($response) : null, $responseCode, array());
  }

  /**
   * This method is called before the model will be validated in a patch, giving you the opportunity to do override functionality per ApiHandler
   *
   * @param Model $model
   * @return Model
   */
  protected function beforePatchValidate(Model $model) : Model
  {
    return $model;
  }

  /**
   * This method is called before the model will be updated in a patch, giving you the opportunity to do override functionality per ApiHandler
   *
   * @param Model $model
   * @return Model
   */
  protected function beforePatchSave(Model $model, Model $originalModel) : Model
  {
    return $model;
  }

  /**
   * This method is called after the model is saved in a patch, giving you the opportunity to do override functionality per ApiHandler
   *
   * @param Model $model
   * @return Model
   */
  protected function afterPatchSave(Model $model, Model $originalModel) : Model
  {
    return $model;
  }

  /**
   * This method executes patch functionality for the Rest call. A slug must be provided
   * A call to this method will update a new model in the database, if the fields arent included in the response, they will be ignored in the udpate.
   * If you want to make fields empty, you must provide them with value NULL
   * Permissions to the API will be checked, and fields will be filtered to only update and return the fields where the user has access to
   *
   * @param Request $request
   * @return Response
   */
  public function patch(Request $request) : Response
  {
    $modelClassName = $this->modelClassName;

    $response;
    $responseCode;

    // Before anything, we check if the user has permission to access this content
    if(!$modelClassName::userHasEditPermission())
    {
      // No access, return a 405 response
      return new Response(null, 405, array());
    }

    $jsonapidocument = json_decode($request->getContent());
    // Since we're talking about a patch here, the ID must be set, as it is an update to an existing record
    if(!empty($jsonapidocument->data->id) && !empty($jsonapidocument->data->type))
    {
      // First we'll build the root model from the json api document
      // since we're talking about a patch here, the model must already exist in the database
      $query = $modelClassName::getModelQuery();
      $query->addCondition(new Condition($modelClassName::$idField, '=', $jsonapidocument->data->id));

      // We check for base conditions (these are conditions that always need to be applied, regardless of the api)
      // This can be used to limit the results based on the logged in user, when the user only has access to certain records
      if(sizeof($this->baseConditions) > 0)
      {
        foreach($this->baseConditions as $condition)
        {
          $query->addBaseCondition($condition);
        }
      }

      // Next we also do the same for the condition groups
      if(sizeof($this->conditionGroups) > 0)
      {
        foreach($this->conditionGroups as $conditionGroup)
        {
          $query->addConditionGroup($conditionGroup);
        }
      }

      $model = $query->fetchSingleModel();

      // Only if the model was found in the database can we continue
      if(!empty($model))
      {
        // We make a copy before applying changes, because we might need the original values in the hooks later on
        $originalModel = $model->getClonedModel();
        // here we fill in the attributes on the new model from the json api document
        $model->applyChangesFromJsonAPIDocument($jsonapidocument);
        // we trigger the beforeValidate as we might need to trigger some functionalitity
        // before doing the validation and potentially sending back incorrect errors
        $model->beforeValidate();
        $model = $this->beforePatchValidate($model);
        // next we do the validation, in the return object we get a potential error document
        $model->constraints();
        $validation = $model->validate();

        // Next we'll check for included relationships
        // We start of by getting the none default keys from the data attribute in the jsonapi document
        // this isn't part of the jsonapi spec unfortunatly, but for now this is the only way to embed records in a POST or a PATCH
        // the functionality is currently based on the DS.EmbeddedRecordsMixin of Ember
        // But will be rewritten once JsonAPI includes embedding in the spec
        //
        // This is a PATCH, since there isn't a spec, we'll handle this as follows
        // - If the relationship is passed in the document, all related models should be included
        // - If a related model is missing from the json api document, we'll consider it deleted, and remove it from the db
        // - If a related model is included with an ID, it should be updated in the database
        // - If a related model is included without an ID, it is a new model, and should be added
        $referencedRelationshipsToSave = [];
        $fieldRelationshipsToSave = [];

        $noneDefaultKeys = JsonApiRootNode::getNoneDefaultDataKeys($jsonapidocument);
        foreach($noneDefaultKeys as $noneDefaultKey)
        {
          // It's not because there is a key in the json api document that isn't default,
          // that we can just assume it's in included relationship
          // The relationship must also be defined in the embeddedApiRelationships array on the model
          if(array_key_exists($noneDefaultKey, static::$embeddedApiRelationships))
          {
            // Lets get the relationship from the model
            $includedRelationshipName = static::$embeddedApiRelationships[$noneDefaultKey];
            $includedRelationship = $modelClassName::getRelationship($includedRelationshipName);

            // We get the inline data from the json document
            $inlineData = $jsonapidocument->data->$noneDefaultKey;
            if($includedRelationship instanceof FieldRelationship)
            {
              // Lets do some unsupported checks first
              if($includedRelationship->fieldCardinality === 1 && !$includedRelationship->isPolymorphic)
              {
                // And the json spec says there should always be a type included
                if(!empty($inlineData->data->type))
                {
                  $fieldRelationshipsToSave[] = $includedRelationshipName;
                  // we get the modeltype for the relationship, because we will need to forge a model later
                  $includedModelType = $includedRelationship->modelType;
                  $includedModel;

                  // Either this is a new record, when no ID was provided => insert
                  // or the included record exists in the db already, and it's id was provided => update
                  if(empty($inlineData->data->id))
                  {
                    // new record, we create a new model
                    $includedModel = $includedModelType::forgeNew();
                  }
                  else
                  {
                    // existing record, lets get it from the database
                    $includedModel = $includedModelType::forgeById($inlineData->data->id);

                    if(empty($includedModel))
                    {
                      throw new ModelNotFoundException('The model with ID '.$inlineData->data->id.' does not exist in the database while trying to perform an inline save with a id passed (inline type '.$noneDefaultKey.')');
                    }
                  }

                  // We apply the changes from the json document to the model (both new and existing, the fields will be updated the same way)
                  $includedModel->applyChangesFromJsonAPIDocument($inlineData);

                  // now we validate the model
                  $includedModel->beforeValidate();
                  $includedModel->constraints();
                  $fieldRelationshipValidation = $includedModel->validate();

                  // we musn't forget, that both the parens and the children aren't persisted in the database yet
                  // because of models and collections, the structure is kept for saving, but it is impossible to validate a potential required parent for a child
                  // that is why we will add an ignore on the relationship field for the current model for a NotNullConstraint
                  $validation->addIgnore($includedRelationship->getField(), 'Drupal\Core\Validation\Plugin\Validation\Constraint\NotNullConstraint');

                  // the first argument sets the path in the validation
                  $validation->addIncludedValidation('/'.$noneDefaultKey, $fieldRelationshipValidation);

                  // And finally put the included model on the model
                  $model->put($includedRelationship, $includedModel);
                }
                else
                {
                  throw new InvalidTypeException('Tried to save an inline Field relationship that did not contain a type in the jsonapi document type hash');
                }
              }
              else
              {
                // Either the cardinality is greater than 1 (multiple values for a field)
                // Or the relationship is polymorphic (more than 1 type)
                throw new NotImplementedException('Including polymorhpic parent relationships or relationships with the field cardinality greater than 1 is currently not supported');
              }
            }
            else if($includedRelationship instanceof ReferencedRelationship)
            {
              // Since this is a patch, we need to fetch the already related models we have in our DB
              $referencedCollection = $model->fetch($includedRelationshipName);
              $relationshipModelType = $includedRelationship->modelType;

              // We will loop over the original collection before changing anything, so we can copy the original models, in the originalModel for the hooks
              foreach($referencedCollection as $originalRelationshipModel)
              {
                $copiedOriginalRelationshipModel = $originalRelationshipModel->getClonedModel();
                $originalModel->put($includedRelationshipName, $copiedOriginalRelationshipModel);
              }

              // Next we will loop every included model in the included relationship in the jsonapidocument
              // in order to create or update a child model, apply the attributes from the json api document, and validate each one

              $referencedRelationshipsToSave[] = $includedRelationshipName;
              $childModelsInUse = [];
              foreach ($inlineData as $inlineCount => $inlineJsonApiDocument)
              {
                // we put a new child model on the just created model
                $childModel;

                if(empty($inlineJsonApiDocument->data->id))
                {
                  // new child model
                  $childModel = $model->putNew($includedRelationship);
                }
                else
                {
                  if(array_key_exists($inlineJsonApiDocument->data->id, $referencedCollection->models))
                  {
                    $id = $inlineJsonApiDocument->data->id;
                    $childModel = $referencedCollection->models[$id];
                  }
                  else
                  {
                    // if the child model isn't found, we ignore it, perhaps it was deleted seperatly?
                    // TODO: is this a good idea?
                    continue;
                  }
                }

                // apply all the attributes from the json document as changes or new values to the child model
                $childModel->applyChangesFromJsonAPIDocument($inlineJsonApiDocument);

                // we mustn't forget to flag the child model as found, so that it doesn't get deleted later
                $childModelsInUse[$childModel->key] = $childModel;

                // and lets validate it as well
                $childModel->beforeValidate();
                $childModel->constraints();
                $childValidation = $childModel->validate();
                // we musn't forget, that both the parents and the children aren't persisted in the database yet
                // because of models and collections, the structure is kept for saving, but it is impossible to validate a potential required parent for a child
                // that is why we will add an ignore on the relationship field for the child for a NotNullConstraint
                $childValidation->addIgnore($includedRelationship->fieldRelationship->getField(), 'Drupal\Core\Validation\Plugin\Validation\Constraint\NotNullConstraint');
                // the first argument sets the path in the validation
                // we must keep track of the position in the array, this must reflect in the path
                $validation->addIncludedValidation('/'.$noneDefaultKey.'/'.$inlineCount, $childValidation);
              }
            }

            // Finally we must also remove the models that weren't included
            foreach($referencedCollection as $childModelKey => $childModel)
            {
              if(!array_key_exists($childModelKey, $childModelsInUse))
              {
                $referencedCollection->remove($childModelKey);
              }
            }
          }
        }

        // Depending on the result of the validation, let's send the the proper result
        if($validation->hasSucceeded())
        {
          // We call our beforeSave hook, so we can potentially fetch some relationships for the before hooks of the model
          $model = $this->beforePatchSave($model, $originalModel);

          // No errors, we can save, and return the newly created model serialized
          $jsonapi = new JsonApiRootNode();

          // Before saving the model, and the referenced relationships,
          // we must save the field relationships, as they might need to be put on the model
          $uniqueFieldRelationshipsToSave = array_unique($fieldRelationshipsToSave);
          foreach($uniqueFieldRelationshipsToSave as $fieldRelationshipToSave)
          {
            $model->save($fieldRelationshipToSave);
          }

          // now we save the model
          $model->save();

          // And also check for potential included relationships
          $uniqueReferencedRelationshipsToSave = array_unique($referencedRelationshipsToSave);
          foreach($uniqueReferencedRelationshipsToSave as $referencedRelationshipToSave)
          {
            $model->save($referencedRelationshipToSave);
            // Let's refetch from the database
            $model->clear($referencedRelationshipToSave);
            $model->fetch($referencedRelationshipToSave);
          }

          // We call our afterSave hook, so we can potentially do some actions based on the newly created model
          $model = $this->afterPatchSave($model, $originalModel);

          // we serialize the response
          $jsonapi->addNode($this->getJsonApiNodeForModelOrCollection($model));

          // Lets check for our includes
          $includes = array_merge($uniqueReferencedRelationshipsToSave, $uniqueFieldRelationshipsToSave);

          // The url might define includes
          if($request->query->has('include'))
          {
            // includes in the url are comma seperated
            $includes = array_merge($includes, explode(',', $request->query->get('include')));
          }

          // But some api handlers provide default includes as well
          if(property_exists($this, 'defaultPatchIncludes'))
          {
            $includes = array_merge($includes, $this->defaultPatchIncludes);
          }

          // And finally include them
          if(!empty($includes))
          {
            $this->checkForIncludes($model, $jsonapi, $includes);
          }

          // and finally we can serialize and set the code
          $response = $this->serialize($jsonapi);
          $responseCode = 200;
        }
        else
        {
          // Unfortunatly we have some erros, let's serialize the error object, and set the proper response code
          $response = $validation->serialize();
          $responseCode = 422;
        }
      }
      else
      {
        // No correspending model with ID found in the database
        unset($response);
        $responseCode = 404;
      }
    }
    else
    {
      // No type, cannot be parsed
      unset($response);
      $responseCode = 404;
    }

    return new Response(isset($response) ? json_encode($response) : null, $responseCode, array());
  }

  /**
   * This method executes a delete on the model, where the ID was provided in the slug. Ofcourse the permission will be checked whether the user is allowed to delete the model
   *
   * @param Request $request
   * @return Response
   */
  public function delete(Request $request) : Response
  {
    $modelClassName = $this->modelClassName;
    $response;
    $responseCode;

    // Before anything, we check if the user has permission to access this content
    if(!$modelClassName::userHasDeletePermission())
    {
      // No access, return a 405 response
      return new Response(null, 405, array());
    }

    $query = $modelClassName::getModelQuery();
    $query->addCondition(new Condition($modelClassName::$idField, '=', $this->slug));

    // We check for base conditions (these are conditions that always need to be applied, regardless of the api)
    // This can be used to limit the results based on the logged in user, when the user only has access to certain records
    if(sizeof($this->baseConditions) > 0)
    {
      foreach($this->baseConditions as $condition)
      {
        $query->addBaseCondition($condition);
      }
    }

    // Next we also do the same for the condition groups
    if(sizeof($this->conditionGroups) > 0)
    {
      foreach($this->conditionGroups as $conditionGroup)
      {
        $query->addConditionGroup($conditionGroup);
      }
    }

    $model = $query->fetchSingleModel();

    // Only if the model was found in the database can we continue
    if(!empty($model))
    {
      $model->delete();
      $response = new \stdClass();
      $responseCode = 204;
    }
    else
    {
      $response = new \stdClass();
      $responseCode = 404;
    }

    return new Response(isset($response) ? json_encode($response) : null, $responseCode, array());
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
   * @return ModelApiHandler
   */
  protected function addSingleLink(JsonApiRootNode $jsonapi, string $name, string $baseUrl, ?int $limit = 0, ?int $page = 0, ?string $sort = null) : ModelApiHandler
  {
    $link = new JsonApiLink($name, $baseUrl);
    if(!empty($limit))
    {
      $link->addParam('limit', $limit);
    }
    if(!empty($sort))
    {
      $link->addParam('sort', $sort);
    }
    if(!empty($page))
    {
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
   * @return ModelApiHandler
   */
  protected function checkForIncludes($source, JsonApiRootNode $jsonApiRootNode, array $relationshipNamesToInclude) : ModelApiHandler
  {
    if(!empty($source) && !$source->isEmpty)
    {
      $modelClassName = $this->modelClassName;
      $fetchedCollections = array(); // we will cache collections here, so we don't get duplicate data to include when multiple relationships point to the same object
      foreach($relationshipNamesToInclude as $relationshipNameToInclude)
      {
        $hasRelationship = $modelClassName::hasDeepRelationship($relationshipNameToInclude);
        if($hasRelationship)
        {
          // Before anything else, we check if the user has access to the data
          $deepRelationship = $modelClassName::getDeepRelationship($relationshipNameToInclude);
          $entityQuery = null;

          // Now we check permissions
          if($deepRelationship instanceof FieldRelationship)
          {
            // A field relationship can be polymorphic, only if the user has access to all types, can we allow the include
            if($deepRelationship->isPolymorphic)
            {
              // We need to check which types we have access to
              // Because the relationship is polymorphic, we have to test each type
              $allowedBundles = [];
              foreach($deepRelationship->polymorphicModelTypes as $deepRelationshipModelClassName)
              {
                // Lets preemtively create an entity query, in order to copy the conditions from later, when it turns out we dont have access to all types
                if(empty($entityQuery))
                {
                  $entityQuery = $deepRelationshipModelClassName::getEntityQuery();
                }

                if($deepRelationshipModelClassName::userHasReadPermission() && !empty($deepRelationshipModelClassName::$bundle))
                {
                  $allowedBundles = $deepRelationshipModelClassName::$bundle;
                }
              }

              // Lets check if we have access to everything
              if(sizeof($allowedBundles) !== sizeof($deepRelationship->polymorphicModelTypes))
              {
                // Unfortunately the user cant access every type, so lets see which ones do work
                if(sizeof($allowedBundles) === 0)
                {
                  // Nothing works, skip this relationship entirely
                  continue;
                }
                else
                {
                  // This is the tricky part, we must now filter on which types we have access on, and which we dont
                  // We add a condition to the entityquery created above, this will be passed in the fetch
                  // to make sure the results are only those of the types the user has access to
                  $entityQuery->addCondition(new Condition('type', 'IN', $allowedBundles));
                }
              }
            }
            else
            {
              $deepRelationshipModelClassName = $deepRelationship->modelType;
              if(!$deepRelationshipModelClassName::userHasReadPermission())
              {
                // No access to model class, skip the include
                continue;
              }
            }
          }
          else if($deepRelationship instanceof ReferencedRelationship)
          {
            $deepRelationshipModelClassName = $deepRelationship->modelType;
            if(!$deepRelationshipModelClassName::userHasReadPermission())
            {
              // No access to model class, skip the include
              continue;
            }
          }
          else
          {
            continue;
          }

          // first of all, we fetch the data
          // We also pass in a possible entity query, in case we get conditions on the relationships we want to query
          // in case the query = null, everything will be fetched
          $source->fetch($relationshipNameToInclude, $entityQuery);
          $fetchedObject = $source->get($relationshipNameToInclude);
          // We don't know yet if this is a Collection or a Model we just fetched,
          // as the source we fetched it from can be both as well
          if($fetchedObject instanceof Collection)
          {
            $fetchedCollection = $fetchedObject;
            if(!$fetchedCollection->isEmpty)
            {
              foreach($fetchedCollection as $model)
              {
                // watch out, we can't use $relationship->modelType, because that doesn't work for polymorphic relationships
                $relationshipType = get_class($model);
                // Here we check if we already fetched data of the same type
                if(!array_key_exists($relationshipType, $fetchedCollections))
                {
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
          }
          else if($fetchedObject instanceof Model)
          {
            $fetchedModel = $fetchedObject;
            if(!empty($fetchedModel))
            {
              // watch out, we can't use $relationship->modelType, because that doesn't work for polymorphic relationships
              $relationshipType = get_class($fetchedModel);
              // now we check if we already included objects of the same type
              if(!array_key_exists($relationshipType, $fetchedCollections))
              {
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
      foreach($fetchedCollections as $fetchedCollection)
      {
        if(!$fetchedCollection->isEmpty)
        {
          $serializedCollection = $fetchedCollection->getJsonApiNode();
          $jsonApiRootNode->addInclude($serializedCollection);
        }
      }
    }

    return $this;
  }

  /**
   * This method parses a filter array (which is generally found in the query parameter of the request)
   * To an array of Conditions that can be used to query the database for results
   *
   * @param string $modelClassName
   * @param array $filter
   * @return array
   */
  public static function getConditionListForFilterArray(string $modelClassName, array $filters) : array
  {
    $prettyToFieldsMap = $modelClassName::getPrettyFieldsToFieldsMapping();
    $conditions = [];

    foreach($filters as $index => $filter)
    {
      if(is_array($filter) && array_key_exists('field', $filter))
      {
        // lets start by making sure the field exists
        // we explode, because we have a potential field with a column (like address.city) as opposed to just a field (like name)
        $prettyFieldParts = explode('.', $filter['field']);
        if(array_key_exists($prettyFieldParts[0], $prettyToFieldsMap))
        {
          $field = $prettyToFieldsMap[$prettyFieldParts[0]];
          $operator = (array_key_exists('operator', $filter) && Condition::isValidSingleModelOperator($filter['operator'])) ? $filter['operator'] : '=';
          $value = array_key_exists('value', $filter) ? $filter['value'] : null;
          $id = array_key_exists('id', $filter) ? $filter['id'] : null;

          // Now that we filtered everything out the filter arrays, we can build our actual Condition
          if(!empty($operator) && !empty($field) && (!empty($value) || !empty($id)))
          {
            // Since either the value, or the ID can be passed, we first check what we found in the filter
            // This is only needed for Entity_reference fields, where you can filter on the title of the related object through "value"
            // Or on the ID of the object through the "ID"
            if(!empty($id))
            {
              // ID is more important than value, so we check it first
              // An ID cant have a seperate column, so no need to check for that, we can just return the condition
              $condition = new Condition($field, $operator, $id);
              $conditions[] = $condition;
            }
            else
            {
              // No ID passed, we can check for value
              $fieldDefinition = $modelClassName::getFieldDefinition($field);
              $fieldType = $fieldDefinition->getType();

              // First We must check if it is a single value field. Or a column
              if(sizeof($prettyFieldParts) > 1)
              {
                // More than 1 value in the field parts, so we can assume an extra column is present
                $typePrettyToFieldsMap = Model::getTypePrettyFieldToFieldsMapping();

                // Only certain columns are allowed to filter on, we check if the type is present, and the column is allowed
                if(array_key_exists($fieldType, $typePrettyToFieldsMap) && array_key_exists($prettyFieldParts[1], $typePrettyToFieldsMap[$fieldType]))
                {
                  $column = $typePrettyToFieldsMap[$fieldType][$prettyFieldParts[1]];
                  $condition = new Condition($field.'.'.$column, $operator, $value);
                  $conditions[] = $condition;
                }
              }
              else
              {
                // just a field, no column (like name)

                // Lets check for the type, because if the type is an entity reference, we will filter on the title of the referenced entity
                if($fieldType === 'entity_reference')
                {
                  // Because the user entity works differently than any other, we must also check for the target_type, and use a different column
                  $settings = $fieldDefinition->getSettings();
                  if($settings['target_type'] === 'user')
                  {
                    $condition = new Condition($field.'.entity.name', $operator, $value);
                    $conditions[] = $condition;
                  }
                  else if($settings['target_type'] === 'user_role')
                  {
                    // TODO, fix this, currently getting exception "Getting the base fields is not supported for entity type user_role"
                    // works without entity.title appended
                    $condition = new Condition($field, $operator, $value);
                    $conditions[] = $condition;
                  }
                  else
                  {
                    $condition = new Condition($field.'.entity.title', $operator, $value);
                    $conditions[] = $condition;
                  }
                }
                else
                {
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
   * @param string $modelClassName
   * @param array $filter
   * @return ListView|null
   */
  public static function getListViewForFilterArray(string $modelClassName, array $filter) : ?ListView
  {
    if(array_key_exists('_listview', $filter))
    {
      $listViewParameterValue = $filter['_listview'];
      if(!empty($listViewParameterValue) && is_numeric($listViewParameterValue))
      {
        $listview = ListView::forgeById($listViewParameterValue);

        if(!empty($listview) && $listview->entity->field_entity->value === $modelClassName::$entityType && $listview->entity->field_bundle->value === $modelClassName::$bundle)
        {
          return $listview;
        }
      }
    }

    return null;
  }

  /**
   * This method returns an array of sort orders found in the sort array (generally passed in the query parameters of the request)
   *
   * @param string $modelClassName
   * @param array $sortQueryFields
   * @return array
   */
  public static function getSortOrderListForSortArray(string $modelClassName, array $sortQueryFields) : array
  {
    $prettyToFieldsMap = $modelClassName::getPrettyFieldsToFieldsMapping();
    $sortOrders = [];
    foreach($sortQueryFields as $sortQueryField)
    {
      // the json-api spec tells us, that all fields are sorted ascending, unless the field is prepended by a '-'
      // http://jsonapi.org/format/#fetching-sorting
      $direction = (!empty($sortQueryField) && $sortQueryField[0] === '-') ? 'DESC' : 'ASC';
      $prettyField = ltrim($sortQueryField, '-'); // lets remove the '-' from the start of the field if it exists

      $prettyFieldParts = explode('.', $prettyField);

      // if the pretty field exists, lets add it to the sort order
      if(array_key_exists($prettyFieldParts[0], $prettyToFieldsMap))
      {
        $field = $prettyToFieldsMap[$prettyFieldParts[0]];
        $fieldDefinition = $modelClassName::getFieldDefinition($field);
        $fieldType = $fieldDefinition->getType();

        if(sizeof($prettyFieldParts) > 1) // meaning we have a extra column present
        {
          // Only certain types are allowed to sort on a different column
          $typePrettyToFieldsMap = $modelClassName::getTypePrettyFieldToFieldsMapping();

          if(array_key_exists($fieldType, $typePrettyToFieldsMap) && array_key_exists($prettyFieldParts[1], $typePrettyToFieldsMap[$fieldType]))
          {
            $column = $typePrettyToFieldsMap[$fieldType][$prettyFieldParts[1]];
            $sortOrders[] = new Order($field.'.'.$column, $direction);
          }
        }
        else
        {
          if($fieldType === 'entity_reference')
          {
            // In case the field type is entity reference, we want to sort by the title, not the ID
            // Because the user entity works differently than any other, we must also check for the target_type
            $settings = $fieldDefinition->getSettings();
            if($settings['target_type'] === 'user')
            {
              $sortOrders[] = new Order($field.'.entity.name', $direction);
            }
            else
            {
              $sortOrders[] = new Order($field.'.entity.title', $direction);
            }
          }
          else
          {
            // Any other field, can be sorted like normal
            $sortOrders[] = new Order($field, $direction);
          }
        }
      }
    }

    return $sortOrders;
  }

  /**
   * Get the fully qualified classname of the modelclass of this apihandler
   *
   * @return string
   */
  protected function getModelClassName() : string
  {
    return $this->modelClassName;
  }

  /**
   * Set the fully qualified classname of the modelclass of this apihandler
   *
   * @param string $modelClassName
   * @return ModelApiHandler
   */
  protected function setModelClassName(string $modelClassName) : ModelApiHandler
  {
    $this->modelClassName = $modelClassName;
    return $this;
  }
}
