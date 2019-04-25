<?php

namespace Drupal\spectrum\Email;

use Drupal\spectrum\Model\Model;

class BaseTemplate extends Model
{
  /**
   * The Entitytype of this model
   *
   * @return string
   */
  public static function entityType() : string
  {
    return 'template';
  }

  /**
   * The Bundle of this model
   *
   * @return string
   */
  public static function bundle() : string
  {
    return 'base';
  }

  /**
   * @return string
   */
  public function getHtml() : ?string
  {
    return $this->entity->field_html_body->value;
  }

  /**
   * @param string $html
   */
  public function setHtml(string $html) : BaseTemplate
  {
    $this->entity->field_html_body->value = $html;

    return $this;
  }
}
