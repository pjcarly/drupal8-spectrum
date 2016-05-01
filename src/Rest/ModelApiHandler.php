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

  public function __construct($modelClassName, $slug = null)
  {
    parent::__construct($slug);
    $this->modelClassName = $modelClassName;
  }

  public function get(Request $request, $includes = array())
  {
    $modelClassName = $this->modelClassName;
    $query = $modelClassName::getModelQuery();
    $jsonapi;

    if(empty($this->slug))
    {
      $result = $query->fetchCollection();

      if(!$result->isEmpty)
      {
        $jsonapi = new JsonApiRootNode();
        $jsonapi->setData($result->getJsonApiNode());
        $this->checkForIncludes($result, $jsonapi, $includes);

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

    $headers = array();
    $headers['Content-Type'] = 'application/vnd.api+json';

    return new Response(json_encode($jsonapi), 200, $headers);
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
