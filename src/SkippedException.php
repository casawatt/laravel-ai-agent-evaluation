<?php

namespace Casawatt\LaravelAiAgentEvaluation;

class SkippedException extends \LogicException
{
    public function __construct(string $reason = 'Skipped')
    {
        parent::__construct($reason);
    }
}
