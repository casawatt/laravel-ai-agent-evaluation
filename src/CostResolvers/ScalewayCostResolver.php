<?php

/**
 * @see https://www.scaleway.com/en/generative-apis/
 */

namespace Casawatt\LaravelAiAgentEvaluation\CostResolvers;

use Casawatt\LaravelAiAgentEvaluation\CostResolverInterface;
use Casawatt\LaravelAiAgentEvaluation\Price;
use Laravel\Ai\Enums\Lab;

class ScalewayCostResolver implements CostResolverInterface
{
    /** @var array<string, array> */
    private array $models = [
        'qwen3.5-397b-a17b' => [0.6, 3.6],
        'qwen3-235b-a22b-instruct-2507' => [0.75, 2.25],
        'gpt-oss-120b' => [0.15, 0.6],
        'gemma-3-27b-it' => [0.25, 0.5],
        'holo2-30b-a3b' => [0.3, 0.7],
        'voxtral-small-24b-2507' => [0.15, 0.35],
        'mistral-small-3.2-24b-instruct-2506' => [0.15, 0.35],
        'llama-3.3-70b-instruct' => [0.9, 0.9],
        'deepseek-r1-distill-llama-70b' => [0.9, 0.9],
        'qwen3-embedding-8b' => [0.1, 0.0],
        'qwen3-coder-30b-a3b-instruct' => [0.2, 0.8],
        'pixtral-12b-2409' => [0.2, 0.2],
        'mistral-nemo-instruct-2407' => [0.2, 0.2],
        'bge-multilingual-gemma2' => [0.1, 0.0],
        'llama-3.1-8b-instruct' => [0.2, 0.2],
    ];

    public function resolve(Lab|string $provider, string $model): ?Price
    {
        $providerKey = $provider instanceof Lab ? $provider->value : $provider;

        if ($providerKey !== 'scaleway') {
            return null;
        }

        if (isset($this->models[$model])) {
            return new Price($this->models[$model][0], $this->models[$model][1]);
        }

        return null;
    }
}
