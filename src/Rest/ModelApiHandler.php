<?php

namespace Drupal\spectrum\Rest;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Serializer\JsonApiLink;

class ModelApiHandler extends BaseApiHandler
{
  private $modelClassName;
  protected $maxLimit = 2000;

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

          // if the pretty field exists, lets add it to the sort order
          if(array_key_exists($prettyField, $prettyToFieldsMap))
          {
            $field = $prettyToFieldsMap[$prettyField];
            $query->addSortOrder($field, $direction);
          }
        }
      }

      // Before we fetch the collection, lets check for filters
      if($request->query->has('filter'))
      {
        $filter = $request->query->get('filter');
        if(array_key_exists('fields', $filter) && is_array($filter['fields']))
        {
          foreach(array_keys($filter['fields']) as $prettyField)
          {
            // lets start by making sure the field exists
            if(array_key_exists($prettyField, $prettyToFieldsMap))
            {
              $field = $prettyToFieldsMap[$prettyField];
              $operator = null;
              $value = null;

              $filterValue = $filter['fields'][$prettyField];

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
                $condition = new Condition($field, $operator, $value);
                $query->addCondition($condition);
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
            $this->addSingleLink($jsonapi, 'last', $baseUrl, 0, $lastPage, $sort);

            // and finally, we also check if the next page isn't larger than the last page
            $nextPage = empty($page) ? 2 : $page+1;
            if($nextPage <= $lastPage)
            {
              $this->addSingleLink($jsonapi, 'next', $baseUrl, 0, $nextPage, $sort);
            }
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
            $this->addSingleLink($jsonapi, 'last', $baseUrl, $limit, $lastPage, $sort);

            // and finally, we also check if the next page isn't larger than the last page
            $nextPage = empty($page) ? 2 : $page+1;
            if($nextPage <= $lastPage)
            {
              $this->addSingleLink($jsonapi, 'next', $baseUrl, $limit, $nextPage, $sort);
            }
          }
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
      $modelClassName = $this->modelClassName;
      $model = $modelClassName::createNew();
      $model->applyChangesFromJsonAPIDocument($jsonapidocument);

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
              // next we get the type of the data we fetched
              $relationship = $modelClassName::getRelationship($relationshipNameToInclude);
              $relationshipType = $relationship->modelType;

              // Here we check if we already fetched data of the same type
              if(array_key_exists($relationshipType, $fetchedCollections))
              {
                // we already fetched data of the same type before, lets merge it with the data we have, so we don't create duplicates in the response
                // luckally for us, collection->put() handles duplicates by checking for id
                $previouslyFetchedCollection = $fetchedCollections[$relationshipType];
                foreach($fetchedCollection as $model)
                {
                  $previouslyFetchedCollection->put($model);
                }
              }
              else
              {
                // we haven't fetched this type yet, lets cache it in case we do later
                $fetchedCollections[$relationshipType] = $fetchedCollection;
              }
            }
          }
          else if($fetchedObject instanceof Model)
          {
            $fetchedModel = $fetchedObject;
            if(!empty($fetchedModel))
            {
              // next we get the type of the data we fetched
              $relationship = $modelClassName::getRelationship($relationshipNameToInclude);
              $relationshipType = $relationship->modelType;

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
