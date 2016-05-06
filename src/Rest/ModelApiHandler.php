<?php

namespace Drupal\spectrum\Rest;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Serializer\JsonApiEmptyDataNode;

class ModelApiHandler extends BaseApiHandler
{
  private $modelClassName;
  protected $getIncludes;
  protected $postIncludes;
  protected $putIncludes;

  public function __construct($modelClassName, $slug = null)
  {
    parent::__construct($slug);
    $this->modelClassName = $modelClassName;
    $this->getIncludes = array();
    $this->postIncludes = array();
    $this->putIncludes = array();

    $this->defaultHeaders['Content-Type'] = 'application/vnd.api+json';
    $this->defaultHeaders['Access-Control-Allow-Origin'] = 'http://localhost:4200';
  }

  public function get(Request $request)
  {
    $modelClassName = $this->modelClassName;
    $query = $modelClassName::getModelQuery();
    $jsonapi;

    if(empty($this->slug))
    {
      // when the slug is empty, we must check for extra variables
      if($request->query->has('limit') && is_numeric($request->query->get('limit')))
      {
        $length = $request->query->get('limit');

        // Also check for the page variable, we potentially need to adjust the query start
        if($request->query->has('page') && is_numeric($request->query->get('page')))
        {
          $page = $request->query->get('page');
          $start = ($page-1) * $length;
          $query->setRange($start, $length);
        }
        else
        {
          // no page, we can just set a limit
          $query->setLimit($length);
        }
      }

      // Lets also check for an order
      if($request->query->has('sort'))
      {
        // sort params are split by ',' so lets evaluate them individually
        $sortQueryFields = explode(',', $request->query->get('sort'));

        // Lets get the pretty to regular field mapping
        $prettyToFieldsMap = $modelClassName::getPrettyFieldsToFieldsMapping();

        foreach($sortQueryFields as $sortQueryField)
        {
          // the json-api spec tells us, that all fields are sorted ascending, unless the field is prepended by a '-'
          // http://jsonapi.org/format/#fetching-sorting
          $direction = $sortQueryField[0] === '-' ? 'DESC' : 'ASC';
          $prettyField = ltrim($sortQueryField, '-'); // lets remove the '-' from the start of the field if it exists

          // if the pretty field exists, lets add it to the sort order
          if(array_key_exists($prettyField, $prettyToFieldsMap))
          {
            $field = $prettyToFieldsMap[$prettyField];
            $query->addSortOrder($field, $direction);
          }
        }
      }

      $result = $query->fetchCollection();

      if(!$result->isEmpty)
      {
        $jsonapi = new JsonApiRootNode();
        $jsonapi->setData($result->getJsonApiNode());
        $this->checkForIncludes($result, $jsonapi, $this->getIncludes);

        $jsonapi = $jsonapi->serialize();
      }
      else
      {
        $node = new JsonApiEmptyDataNode();
        $node->asArray(true);
        $jsonapi = $node->serialize();
      }
    }
    else
    {
      $query->addCondition(new Condition($modelClassName::$idField, '=', $this->slug));
      $result = $query->fetchSingleModel();

      if(!empty($result))
      {
        $jsonapi = $result->serialize();
      }
      else
      {
        $node = new JsonApiEmptyDataNode();
        $jsonapi = $node->serialize();
      }
    }

    return new Response(json_encode($jsonapi), 200, array());
  }

  protected function checkForIncludes($source, JsonApiRootNode $jsonApiRootNode, $relationshipNamesToInclude)
  {
    if(!$source->isEmpty)
    {
      $modelClassName = $this->modelClassName;
      $fetchedCollections = array(); // we will cache collections here, so we don't get duplicate data to include when multiple relationships point to the same object

      foreach($relationshipNamesToInclude as $relationshipNameToInclude)
      {
        // first of all, we fetch the data
        $source->fetch($relationshipNameToInclude);
        $fetchedCollection = $source->get($relationshipNameToInclude);

        if(!$fetchedCollection->isEmpty)
        {
          // next we get the type of the data we fetched
          $relationship = $modelClassName::getRelationship($relationshipNameToInclude);
          $relationshipType = $relationship->modelType;

          // Here we check if we already fetched data of the same type
          if(array_key_exists($relationshipType, $fetchedCollections))
          {
            // we already fetched data of the same type before, lets merge it with the data we have, so we don't create duplicates in the response
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
