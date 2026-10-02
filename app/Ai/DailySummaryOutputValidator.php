<?php

namespace App\Ai;

use DomainException;
use Illuminate\Database\Eloquent\Collection;

final class DailySummaryOutputValidator
{
    public function __construct(private readonly StoryDraftOutputValidator $storyValidator) {}

    /** @return list<array{text: string, article_ids: list<int>, slot_at: string}> */
    public function validate(array $output, Collection $articles, string $slotAt): array
    {
        if (array_keys($output) !== ['paragraphs'] || ! is_array($output['paragraphs'])
            || ! array_is_list($output['paragraphs']) || count($output['paragraphs']) < 1
            || count($output['paragraphs']) > 2) {
            throw new DomainException('A estrutura do resumo diário é inválida.');
        }

        $allowedIds = $articles->modelKeys();
        $sourceText = $articles->pluck('content')->filter()->implode(' ');
        $blocks = [];

        foreach ($output['paragraphs'] as $paragraph) {
            if (! is_array($paragraph) || array_keys($paragraph) !== ['text', 'article_ids']
                || ! is_string($paragraph['text']) || trim($paragraph['text']) === ''
                || mb_strlen($paragraph['text']) > 500 || strip_tags($paragraph['text']) !== $paragraph['text']
                || preg_match('/\b(?:id|article_ids)\s*[:#]\s*\d+\b/i', $paragraph['text'])
                || ! is_array($paragraph['article_ids']) || ! array_is_list($paragraph['article_ids'])
                || $paragraph['article_ids'] === []
                || count($paragraph['article_ids']) !== count(array_unique($paragraph['article_ids']))) {
                throw new DomainException('Um parágrafo do resumo diário é inválido.');
            }

            foreach ($paragraph['article_ids'] as $id) {
                if (! is_int($id) || ! in_array($id, $allowedIds, true)) {
                    throw new DomainException('O resumo diário cita um artigo fora do contexto.');
                }
            }

            if ($this->storyValidator->copiesLongPassage($paragraph['text'], $sourceText)) {
                throw new DomainException('O resumo diário copia um trecho longo de um artigo.');
            }

            $blocks[] = [
                'text' => trim($paragraph['text']),
                'article_ids' => $paragraph['article_ids'],
                'slot_at' => $slotAt,
            ];
        }

        return $blocks;
    }
}
