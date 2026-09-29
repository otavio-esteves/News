<?php

use App\Enums\StoryCategory;
use App\News\Categorization\ArticleCategoryClassifier;

it('uses the publisher section to classify original articles', function () {
    $classifier = new ArticleCategoryClassifier;

    expect($classifier->classify('agencia-brasil', 'https://agenciabrasil.ebc.com.br/cultura/noticia/2026-09/exemplo'))
        ->toBe(StoryCategory::Cultura)
        ->and($classifier->classify('agencia-brasil', 'https://agenciabrasil.ebc.com.br/entretenimento/noticia/2026-09/exemplo'))
        ->toBe(StoryCategory::Entretenimento)
        ->and($classifier->classify('agencia-senado', 'https://www12.senado.leg.br/noticias/materias/2026/09/27/exemplo'))
        ->toBe(StoryCategory::Politica)
        ->and($classifier->classify('agencia-brasil', 'https://agenciabrasil.ebc.com.br/secao-desconhecida/noticia/2026-09/exemplo'))
        ->toBeNull();
});
