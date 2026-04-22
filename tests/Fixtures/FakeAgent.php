<?php

namespace Casawatt\LaravelAiAgentEvaluation\Tests\Fixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class FakeAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a test agent.';
    }
}
