<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Signed-in routes use `auth:sanctum` (P1-C3), whose guard keeps the first user it resolved for the life of the application.
     * A test that signs in a second user would still be answered as the first, so the guards are forgotten first.
     *
     * @param  string|null  $guard
     */
    public function be(Authenticatable $user, $guard = null): static
    {
        $this->app['auth']->forgetGuards();

        return parent::be($user, $guard);
    }
}
