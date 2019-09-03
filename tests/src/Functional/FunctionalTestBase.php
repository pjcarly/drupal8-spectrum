<?php

namespace Drupal\Tests\spectrum\Functional;

use Drupal\spectrum\Models\User;
use Drupal\Tests\BrowserTestBase;

abstract class FunctionalTestBase extends BrowserTestBase
{

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'spectrum'];

  /**
   * @var User;
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  public function setUp()
  {
    parent::setUp();

    $permissions = [];

    $drupalUser = $this->createUser($permissions);
    $this->user = User::forgeByEntity($drupalUser);

    $this->drupalLogin($drupalUser);
  }
}
