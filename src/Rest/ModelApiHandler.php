<?php

namespace Drupal\spectrum\Rest;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Serializer\JsonApiLink;
use Drupal\spectrum\Exceptions\NotImplementedException;

class ModelApiHandler extends BaseApiHandler
{
  private $modelClassName;
  protected $maxLimit = 200;

  public function __construct($modelClassName, $slug = null)
  {
    parent::__construct($slug);
    $this->modelClassName = $modelClassName;
    $this->defaultHeaders['Content-Type'] = 'application/vnd.api+json';
  }

  public function get(Request $request)
  {
    $modelClassName = $this->modelClassName;
    $query = $modelClassName::getModelQuery();
    $limit = null; $page = null; $sort = null; // variables to build our links later on
    $jsonapi = new JsonApiRootNode();

    // We start by adding the link to this request
    $baseUrl = $request->getSchemeAndHttpHost() . $request->getPathInfo(); // this might not work with a different port than 80, check later

    // Get requests can either be a list of models, or an individual model, so we must check the slug
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
        if(empty($limit))
        {
          // no limit, lets set the default one
          $query->setLimit($this->maxLimit);
        }
        else
        {
          $query->setLimit($limit);
        }
      }

      // Lets get the pretty to regular field mapping for use in either sort or filter
      $prettyToFieldsMap = $modelClassName::getPrettyFieldsToFieldsMapping();

      // Lets also check for an order
      if($request->query->has('sort'))
      {
        // sort params are split by ',' so lets evaluate them individually
        $sort = $request->query->get('sort');
        $sortQueryFields = explode(',', $sort);

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

            if(sizeof($prettyFieldParts) > 1)
            {
              $typePrettyToFieldsMap = $modelClassName::getTypePrettyFieldToFieldsMapping();
              // meaning we have a extra column present
              $fieldDefinition = $modelClassName::getFieldDefinition($field);
              $fieldType = $fieldDefinition->getType();

              if(array_key_exists($fieldType, $typePrettyToFieldsMap) && array_key_exists($prettyFieldParts[1], $typePrettyToFieldsMap[$fieldType]))
              {
                $column = $typePrettyToFieldsMap[$fieldType][$prettyFieldParts[1]];
                $query->addSortOrder($field.'.'.$column, $direction);
              }
            }
            else
            {
              $query->addSortOrder($field, $direction);
            }
          }
        }
      }

      // Before we fetch the collection, lets check for filters
      if($request->query->has('filter'))
      {
        $filter = $request->query->get('filter');
        if(is_array($filter))
        {
          foreach(array_keys($filter) as $prettyField)
          {
            // lets start by making sure the field exists
            // we explode, because we have a potential field with a column (like address.city) as opposed to just a field (like name)
            $prettyFieldParts = explode('.', $prettyField);

            if(array_key_exists($prettyFieldParts[0], $prettyToFieldsMap))
            {
              $field = $prettyToFieldsMap[$prettyFieldParts[0]];
              $operator = null;
              $value = null;

              $filterValue = $filter[$prettyField];

              // the filter value can either be the specific value, or an array with extra attributes
              if(is_array($filterValue))
              {
                // we found an array, meaning we must check for 'operator' as well
                $operator = (array_key_exists('operator', $filterValue) && Condition::isValidSingleModelOperator($filterValue['operator'])) ? $filterValue['operator'] : null;
                $value = array_key_exists('value', $filterValue) ? $filterValue['value'] : null;
              }
              else
              {
                // no array, so it will just be the value
                $operator = '=';
                $value = $filterValue;
              }

              if(!empty($operator) && !empty($value) && !empty($field))
              {
                if(sizeof($prettyFieldParts) > 1)
                {
                  // this means we have a field with a column (like address.city)
                  $typePrettyToFieldsMap = $modelClassName::getTypePrettyFieldToFieldsMapping();
                  // meaning we have a extra column present
                  $fieldDefinition = $modelClassName::getFieldDefinition($field);
                  $fieldType = $fieldDefinition->getType();

                  if(array_key_exists($fieldType, $typePrettyToFieldsMap) && array_key_exists($prettyFieldParts[1], $typePrettyToFieldsMap[$fieldType]))
                  {
                    $column = $typePrettyToFieldsMap[$fieldType][$prettyFieldParts[1]];
                    $condition = new Condition($field.'.'.$column, $operator, $value);
                    $query->addCondition($condition);
                  }
                }
                else
                {
                  // just a field, no column (like name)
                  $condition = new Condition($field, $operator, $value);
                  $query->addCondition($condition);
                }
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

        $node = $result->getJsonApiNode();
        $jsonapi->setData($node);

        if($request->query->has('include'))
        {
          // includes are comma seperated
          $includes = explode(',', $request->query->get('include'));
          if(!empty($includes))
          {
            $this->checkForIncludes($result, $jsonapi, $includes);
          }
        }
      }
    }
    else
    {
      $query->addCondition(new Condition($modelClassName::$idField, '=', $this->slug));
      $result = $query->fetchSingleModel();

      $this->addSingleLink($jsonapi, 'self', $baseUrl);
      if(!empty($result))
      {
        $jsonapi->addNode($result->getJsonApiNode());

        // let's check for includes as well
        if($request->query->has('include'))
        {
          // includes are comma seperated
          $includes = explode(',', $request->query->get('include'));
          if(!empty($includes))
          {
            $this->checkForIncludes($result, $jsonapi, $includes);
          }
        }
      }
      else
      {
        // we musn't do anything, json api provides an empty node out of the box
      }
    }

    return new Response(json_encode($jsonapi->serialize()), 200, array());
  }

  public function post(Request $request)
  {
    $response;
    $responseCode;

    $jsonapidocument = json_decode($request->getContent());
    if(!empty($jsonapidocument->data->type))
    {
      // First we'll build the root model from the json api document
      $modelClassName = $this->modelClassName;
      // since we're talking about a post here, it's always a create, a new model
      $model = $modelClassName::createNew();
      // here we fill in the attributes on the new model from the json api document
      $model->applyChangesFromJsonAPIDocument($jsonapidocument);
      // we trigger the beforeValidate as we might need to trigger some functionalitity before doing the validation
      // and potentially sending back incorrect errors
      $model->beforeValidate();
      // next we do the validation, in the return object we get a potential error document
      $validation = $model->validate();

      // Next we'll check for included relationships
      // We start of by getting the none default keys from the data attribute in the jsonapi document
      // this isn't part of the jsonapi spec unfortunatly, but for now this is the only way to embed records in a POST or a PATCH
      // the functionality is currently based on the DS.EmbeddedRecordsMixin of Ember
      // But will be rewritten once JsonAPI includes embedding in the spec
      $includedRelationshipsToSave = [];
      $noneDefaultKeys = JsonApiRootNode::getNoneDefaultDataKeys($jsonapidocument);
      foreach($noneDefaultKeys as $noneDefaultKey)
      {
        if(array_key_exists($noneDefaultKey, $modelClassName::$embeddedApiRelationships))
        {
          // Lets get the relationship from the model
          $includedRelationshipName = $modelClassName::$embeddedApiRelationships[$noneDefaultKey];
          $includedRelationship = $modelClassName::getRelationship($includedRelationshipName);

          if($includedRelationship instanceof FieldRelationship)
          {
            // currently not supported
            throw new NotImplementedException('Including parent relationships while saving is not supported yet');
          }
          else if($includedRelationship instanceof ReferencedRelationship)
          {
            // Next we will loop every included model in the included relationship in the jsonapidocument
            // in order to create a new child model, apply the attributes from the json api document, and validate each one
            $inlineData = $jsonapidocument->data->$noneDefaultKey;
            foreach ($inlineData as $inlineCount => $inlineJsonApiDocument)
            {
              $includedRelationshipsToSave[] = $includedRelationshipName;
              // we put a new child model on the just created model
              $childModel = $model->putNew($includedRelationship);

              // and apply all the attributes from the json document
              $childModel->applyChangesFromJsonAPIDocument($inlineJsonApiDocument);

              // and lets validate it as well
              $childModel->beforeValidate();
              $childValidation = $childModel->validate();
              // we musn't forget, that both the parents and the children aren't persisted in the database yet
              // because of models and collections, the structure is kept for saving, but it is impossible to validate a potential required parent for a child
              // that is why we will add an ignore on the relationship field for the child for a NotNullConstraint
              $childValidation->addIgnore($includedRelationship->fieldRelationship->getField(), 'Drupal\Core\Validation\Plugin\Validation\Constraint\NotNullConstraint');
              // the first argument sets the path in the validation
              // we must keep track of the position in the array, this must reflect in the path
              $validation->addChildValidation('/'.$noneDefaultKey.'/'.$inlineCount, $childValidation);
            }
          }
        }
      }

      // Depending on the result of the validation, let's send the the proper result
      if($validation->hasSucceeded())
      {
        $jsonapi = new JsonApiRootNode();
        // No errors, we can save, and return the newly created model serialized
        $model->save();
        // We must also save potential included relationships
        $uniqueIncludedRelationships = array_unique($includedRelationshipsToSave);
        foreach($uniqueIncludedRelationships as $includedRelationshipToSave)
        {
          $model->save($includedRelationshipToSave);
        }
        // we serialize the response
        $jsonapi->addNode($model->getJsonApiNode());

        // also check if we need to include any included relationships in the response
        $this->checkForIncludes($model, $jsonapi, $uniqueIncludedRelationships);

        // and finally we can serialize and set the code
        $response = $jsonapi->serialize();
        $responseCode = 200;
      }
      else
      {
        // Unfortunatly we have some errors, let's serialize the error object, and set the proper response code
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

  public function patch(Request $request)
  {
    $response;
    $responseCode;

    $jsonapidocument = json_decode($request->getContent());
    if(!empty($jsonapidocument->data->id) && !empty($jsonapidocument->data->type))
    {
      $modelClassName = $this->modelClassName;
      $model = $modelClassName::forge(null, $jsonapidocument->data->id);

      if(!empty($model)) // model found
      {
        $model->applyChangesFromJsonAPIDocument($jsonapidocument);
        $model->beforeValidate();
        $validation = $model->validate();

        if($validation->hasSucceeded())
        {
          $model->save();
          $response = $model->serialize();
          $responseCode = 200;
        }
        else
        {
          $response = $validation->serialize();
          $responseCode = 422;
        }
      }
      else
      {
        // model with Id not found
        unset($response);
        $responseCode = 404;
      }
    }
    else
    {
      unset($response);
      $responseCode = 404;
    }

    return new Response(isset($response) ? json_encode($response) : null, $responseCode, array());
  }

  public function delete(Request $request)
  {
    $response;
    $responseCode;

    $modelClassName = $this->modelClassName;
    $modelClassName::deleteById($this->slug);

    $response = new \stdClass();
    $response->meta = new \stdClass();
    $responseCode = 200;

    return new Response(isset($response) ? json_encode($response) : null, $responseCode, array());
  }

  protected function addSingleLink(JsonApiRootNode $jsonapi, $name, $baseUrl, $limit = 0, $page = 0, $sort = null)
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
  }

  protected function checkForIncludes($source, JsonApiRootNode $jsonApiRootNode, $relationshipNamesToInclude)
  {
    if(!$source->isEmpty)
    {
      $modelClassName = $this->modelClassName;
      $fetchedCollections = array(); // we will cache collections here, so we don't get duplicate data to include when multiple relationships point to the same object
      foreach($relationshipNamesToInclude as $relationshipNameToInclude)
      {
        if($modelClassName::hasRelationship($relationshipNameToInclude))
        {
          // first of all, we fetch the data
          $source->fetch($relationshipNameToInclude);
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
                $fetchedCollections[$relationshipType] = Collection::forge($relationshipType);
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
  }
}
