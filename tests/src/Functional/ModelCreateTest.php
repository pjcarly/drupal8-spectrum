<?php

namespace Drupal\Tests\spectrum\Functional;

use Drupal\spectrum\Models\User;

class ModelCreateTest extends FunctionalTestBase
{

  public function testUserFetch()
  {
    $this->assertInstanceOf(User::class, $this->user);
  }
}
