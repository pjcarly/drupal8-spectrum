<?php

namespace Drupal\spectrum\Controller;

class RouteController
{
  public function debug()
  {
    // $contact = ContactModel::forge(null, 4);
    // $contact->fetch('account');
    // dpm($contact);

    return [
      '#title' => 'Hi',
      '#markup' => 'content'
    ];
  }
}
