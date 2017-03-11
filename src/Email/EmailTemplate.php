<?php

namespace Drupal\spectrum\Email;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Query\ModelQuery;
use Drupal\spectrum\Query\Condition;

class EmailTemplate extends Model
{
	public static $entityType = 'email';
	public static $bundle = 'template';
	public static $idField = 'id';
  public static $plural = 'Email Templates';

	/* TRIGGERS */


	/* TRIGGER METHODS */


	/* BUSINESS LOGIC */
  public static function getByName($name)
  {
    $query = new ModelQuery('Drupal\spectrum\Email\EmailTemplate');
    $query->addCondition(new Condition('title', '=', $name));
    return $query->fetchSingleModel();
  }
}
