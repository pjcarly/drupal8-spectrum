<?php

namespace Drupal\spectrum\Email;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface;
use Drupal\spectrum\Permissions\AccessPolicy\NoAccessPolicy;

/**
 * Class BaseTemplate
 *
 * @package Drupal\spectrum\Email
 */
class BaseTemplate extends Model
{

  /**
   * @inheritDoc
   */
  public static function entityType(): string
  {
    return 'template';
  }

  /**
   * @inheritDoc
   */
  public static function bundle(): string
  {
    return 'base';
  }

  /**
   * @inheritDoc
   */
  public static function getAccessPolicy(): AccessPolicyInterface
  {
    return new NoAccessPolicy;
  }

  /**
   * @return string
   */
  public function getHtml(): ?string
  {
    return $this->entity->field_html_body->value;
  }

  /**
   * @param string $html
   */
  public function setHtml(string $html): BaseTemplate
  {
    $this->entity->field_html_body->value = $html;

    return $this;
  }
}
