<?php

use Casawatt\LaravelAiAgentEvaluation\LaravelAiAgentEvaluationServiceProvider;
use Illuminate\Http\Client\RequestException;

/**
 * Re-run the package's boot-time truncation logic with the given config value.
 *
 * app.debug is forced off so re-invoking packageBooted() exercises only the
 * truncation branch and does not re-register the (debug-gated) report routes.
 *
 * @param  int|false|null  $value
 */
function rebootWithTruncateConfig($app, $value): void
{
    config()->set('app.debug', false);
    config()->set('ai-agent-evaluation.truncate_errors_at', $value);

    $app->getProvider(LaravelAiAgentEvaluationServiceProvider::class)->packageBooted();
}

afterEach(function () {
    RequestException::truncate(); // restore Laravel's default (120)
});

test('null leaves Laravel error truncation untouched', function () {
    RequestException::$truncateAt = 999;

    rebootWithTruncateConfig($this->app, null);

    expect(RequestException::$truncateAt)->toBe(999);
});

test('false disables error truncation entirely', function () {
    rebootWithTruncateConfig($this->app, false);

    expect(RequestException::$truncateAt)->toBeFalse();
});

test('an integer truncates errors at that length', function () {
    rebootWithTruncateConfig($this->app, 500);

    expect(RequestException::$truncateAt)->toBe(500);
});
