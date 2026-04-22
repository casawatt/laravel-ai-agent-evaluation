<?php

namespace Casawatt\LaravelAiAgentEvaluation;

class SkippedException extends \RuntimeException
{
    public function __construct(string $reason = 'Skipped')
    {
        parent::__construct($reason);
    }
}
