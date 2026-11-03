# Changelog

## v1.4.0 - 2026-11-03

- Add per-variant `providerOptions()` (e.g. Anthropic `output_config.effort`), merged over the agent's own provider options
- Forward the agent's `providerOptions()` when a variant wraps it (previously dropped as soon as a variant set an instruction or a generation option)

## v1.3.0 - 2026-11-02

- Laravel AI 1.3 (`EvaluationResult::$usage` is now a `TextUsage`; use `inputTokens` / `outputTokens`)
- Require PHP 8.4+ and Laravel 12+ (drop PHP 8.3 and Laravel 11)
- Results stored by previous versions remain readable for `--resume` / `--retry-errors`

## v0.8.0 - 2026-06-12

- Laravel AI 0.8

## v0.7.0 - 2026-06-01

- Add `--retry-failed` option to re-run only failed cases from the latest run
- Add per-variant generation options (`temperature`, `topP`, `maxTokens`, `maxSteps`)
- Add HTML reporter
- Laravel AI 0.7

## v0.6.1 - 2026-05-12

- Decouple assertions from PHPUnit 
- Unify path-based assertions on structured responses

## v0.6.0 - 2026-05-11

- First public release
