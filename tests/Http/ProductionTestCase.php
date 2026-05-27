<?php

namespace Casawatt\LaravelAiAgentEvaluation\Tests\Http;

use Casawatt\LaravelAiAgentEvaluation\Tests\TestCase;

class ProductionTestCase extends TestCase
{
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.debug', false);
        $app['config']->set('app.env', 'production');
    }
}
