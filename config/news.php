<?php

use App\News\Sources\AgenciaBrasilAdapter;
use App\News\Sources\AgenciaCamaraAdapter;
use App\News\Sources\AgenciaCnjAdapter;
use App\News\Sources\AgenciaIbgeAdapter;
use App\News\Sources\AgenciaSenadoAdapter;
use App\News\Sources\RadioagenciaNacionalAdapter;

return [
    'ai' => [
        'story_writer_model' => env('NEWS_AI_STORY_WRITER_MODEL', 'gpt-4o-mini'),
    ],
    'source_adapters' => [
        'agencia-brasil' => AgenciaBrasilAdapter::class,
        'agencia-camara' => AgenciaCamaraAdapter::class,
        'agencia-cnj' => AgenciaCnjAdapter::class,
        'agencia-ibge' => AgenciaIbgeAdapter::class,
        'agencia-senado' => AgenciaSenadoAdapter::class,
        'radioagencia-nacional' => RadioagenciaNacionalAdapter::class,
    ],
];
