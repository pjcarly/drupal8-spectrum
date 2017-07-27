<?php

namespace Drupal\spectrum\Models;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;

class File extends Model
{
	public static $entityType = 'file';
	public static $idField = 'fid';

  public static $plural = 'Files';

  public static function relationships()
	{
	}
}
