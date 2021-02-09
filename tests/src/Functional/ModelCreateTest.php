<?php

namespace Drupal\Tests\spectrum\Functional;

use Drupal\spectrum\Models\User;
use weitzman\DrupalTestTraits\ExistingSiteBase;

class ModelCreateTest extends ExistingSiteBase
{

  public function testUserFetch()
  {
    $this->markTestSkipped('WIP');
    $this->assertInstanceOf(User::class, $this->user);
  }
}
