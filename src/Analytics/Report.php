<?php

namespace Drupal\spectrum\Analytics;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;

class Report extends Model
{
	public static $entityType = 'query';
	public static $bundle = 'report';
	public static $idField = 'id';

  public static $plural = 'Reports';

  public static function relationships()
	{

	}
}
