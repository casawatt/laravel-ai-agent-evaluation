<?php

namespace Casawatt\LaravelAiAgentEvaluation\CostResolvers;

use Casawatt\LaravelAiAgentEvaluation\CostResolverInterface;
use Casawatt\LaravelAiAgentEvaluation\Price;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Enums\Lab;

class ModelsDevCostResolver implements CostResolverInterface
{
    private ?array $providers = null;

    public function resolve(Lab|string $provider, string $model): ?Price
    {
        $providerKey = $provider instanceof Lab ? $provider->value : $provider;
        $providers = $this->fetchProviders();

        if (! isset($providers[$providerKey]['models'][$model])) {
            return null;
        }

        return $this->buildPrice($providers[$providerKey]['models'][$model]);
    }

    /**
     * @return array<string, array>
     */
    private function fetchProviders(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $response = Http::get('https://models.dev/api.json');

        $this->providers = $response->json() ?? [];

        return $this->providers;
    }

    private function buildPrice(array $model): ?Price
    {
        $cost = $model['cost'] ?? null;

        if ($cost === null) {
            return null;
        }

        $input = (float) ($cost['input'] ?? 0);
        $output = (float) ($cost['output'] ?? 0);

        return new Price(
            inputPerMillion: $input,
            outputPerMillion: $output,
        );
    }
}
