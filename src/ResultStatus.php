<?php

namespace Casawatt\LaravelAiAgentEvaluation;

enum ResultStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Error = 'error';
}
