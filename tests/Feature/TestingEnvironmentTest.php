<?php

it('uses an isolated in-memory database for the test suite', function () {
    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:')
        ->and(app()->environment())->toBe('testing');
});
