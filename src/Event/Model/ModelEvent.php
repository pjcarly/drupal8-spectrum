<?php

namespace Drupal\spectrum\Event\Model;

use Drupal\Component\EventDispatcher\Event;
use Drupal\spectrum\Model\Model;

abstract class ModelEvent extends Event {

  private Model $model;

  public function __construct(Model $model) {
    $this->model = $model;
  }

  public function getModel(): Model {
    return $this->model;
  }

}