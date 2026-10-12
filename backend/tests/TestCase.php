<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    /**
     * Simulate a fresh request boundary for the JWT guard.
     *
     * Within one test the guard and the JWT singletons keep the previous
     * request's user, token and refresh-flow state, which a real request
     * never would. The cache-backed blacklist is kept so revoked tokens
     * stay revoked.
     */
    protected function forgetAuthenticatedRequest(): void
    {
        foreach ([
            'tymon.jwt',
            'tymon.jwt.auth',
            'tymon.jwt.manager',
            'tymon.jwt.payload.factory',
            'tymon.jwt.validators.payload',
            'tymon.jwt.claim.factory',
        ] as $abstract) {
            $this->app->forgetInstance($abstract);
        }

        JWTAuth::clearResolvedInstance('tymon.jwt.auth');
        $this->app['auth']->forgetGuards();
    }
}
