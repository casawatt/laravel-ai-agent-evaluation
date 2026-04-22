<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Laravel\Ai\Enums\Lab;

interface CostResolverInterface
{
    /**
     * Resolve pricing for a given provider and model.
     *
     * Return null if this resolver cannot determine the price.
     */
    public function resolve(Lab|string $provider, string $model): ?Price;
}
