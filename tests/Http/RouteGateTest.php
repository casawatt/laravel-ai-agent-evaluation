<?php

use Illuminate\Support\Facades\Route;

test('the report route is not registered in production when debug is off', function () {
    expect(Route::has('ai-agent-evaluation.show'))->toBeFalse();

    $this->get('/ai-agent-evaluation/anything')->assertNotFound();
});
