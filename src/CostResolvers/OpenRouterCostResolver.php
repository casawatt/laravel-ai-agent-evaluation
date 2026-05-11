<?php

namespace Casawatt\LaravelAiAgentEvaluation\CostResolvers;

use Casawatt\LaravelAiAgentEvaluation\CostResolverInterface;
use Casawatt\LaravelAiAgentEvaluation\Price;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Enums\Lab;

class OpenRouterCostResolver implements CostResolverInterface
{
    /** @var array<string, array>|null */
    private ?array $models = null;

    public function resolve(Lab|string $provider, string $model): ?Price
    {
        $providerKey = $provider instanceof Lab ? $provider->value : $provider;

        if ($providerKey !== Lab::OpenRouter->value) {
            return null;
        }

        $models = $this->fetchModels();

        if (! isset($models[$model])) {
            return null;
        }

        return $this->buildPrice($models[$model]);
    }

    /**
     * @return array<string, array>
     */
    private function fetchModels(): array
    {
        if ($this->models !== null) {
            return $this->models;
        }

        $response = Http::get('https://openrouter.ai/api/v1/models');

        $this->models = collect($response->json('data', []))
            ->keyBy('id')
            ->all();

        return $this->models;
    }

    private function buildPrice(array $model): Price
    {
        $prompt = (float) ($model['pricing']['prompt'] ?? 0);
        $completion = (float) ($model['pricing']['completion'] ?? 0);

        return new Price(
            inputPerMillion: $prompt * 1_000_000,
            outputPerMillion: $completion * 1_000_000,
        );
    }
}
