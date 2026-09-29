<?php

use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds only the real sources in every environment and remains idempotent', function () {
    $this->seed();
    $this->seed();

    expect(Source::count())->toBe(6)
        ->and(Source::pluck('slug')->all())->toEqualCanonicalizing(['agencia-brasil', 'agencia-camara', 'agencia-cnj', 'agencia-ibge', 'agencia-senado', 'radioagencia-nacional']);
});
