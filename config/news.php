<?php

use App\News\Sources\AgenciaBrasilAdapter;
use App\News\Sources\AgenciaCamaraAdapter;
use App\News\Sources\AgenciaCnjAdapter;
use App\News\Sources\AgenciaIbgeAdapter;
use App\News\Sources\AgenciaSenadoAdapter;
use App\News\Sources\CartaCapitalAdapter;
use App\News\Sources\EstadaoAdapter;
use App\News\Sources\FolhaAdapter;
use App\News\Sources\G1Adapter;
use App\News\Sources\MeioAdapter;
use App\News\Sources\RadioagenciaNacionalAdapter;

return [
    'ai' => [
        'story_writer_provider' => env('NEWS_AI_STORY_WRITER_PROVIDER', 'ollama'),
        'story_writer_model' => env('NEWS_AI_STORY_WRITER_MODEL', 'qwen3:4b'),
        'auto_queue' => env('NEWS_AI_AUTO_QUEUE', false),
    ],
    'source_adapters' => [
        'agencia-brasil' => AgenciaBrasilAdapter::class,
        'agencia-camara' => AgenciaCamaraAdapter::class,
        'agencia-cnj' => AgenciaCnjAdapter::class,
        'agencia-ibge' => AgenciaIbgeAdapter::class,
        'agencia-senado' => AgenciaSenadoAdapter::class,
        'radioagencia-nacional' => RadioagenciaNacionalAdapter::class,
        'folha' => FolhaAdapter::class,
        'g1' => G1Adapter::class,
        'meio' => MeioAdapter::class,
        'estadao' => EstadaoAdapter::class,
        'cartacapital' => CartaCapitalAdapter::class,
    ],
];
