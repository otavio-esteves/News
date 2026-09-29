<?php

namespace App\Ai;

use App\Models\Story;
use DomainException;

final class StoryDraftOutputValidator
{
    /** @param array<string, mixed> $output
     * @param  list<int>  $allowedArticleIds
     * @return array{title: string, category: string, summary_blocks: list<array{text: string, article_ids: list<int>}>}
     */
    public function validate(Story $story, array $output, array $allowedArticleIds): array
    {
        if (array_diff(array_keys($output), ['title', 'category', 'paragraphs']) !== []
            || count($output) !== 3) {
            throw new DomainException('A estrutura do rascunho é inválida.');
        }

        $title = $output['title'];

        if (! is_string($title) || trim($title) === '' || mb_strlen($title) > 255 || strip_tags($title) !== $title) {
            throw new DomainException('O título do rascunho é inválido.');
        }

        if ($output['category'] !== $story->category?->value) {
            throw new DomainException('A categoria do rascunho não corresponde à Story.');
        }

        $paragraphs = $output['paragraphs'];

        if (! is_array($paragraphs) || ! array_is_list($paragraphs) || count($paragraphs) < 1 || count($paragraphs) > 5) {
            throw new DomainException('O rascunho deve conter de um a cinco parágrafos.');
        }

        $blocks = [];

        foreach ($paragraphs as $paragraph) {
            if (! is_array($paragraph) || array_diff(array_keys($paragraph), ['text', 'article_ids']) !== []
                || count($paragraph) !== 2) {
                throw new DomainException('A estrutura de um parágrafo é inválida.');
            }

            $text = $paragraph['text'];
            $ids = $paragraph['article_ids'];

            if (! is_string($text) || trim($text) === '' || mb_strlen($text) > 2000 || strip_tags($text) !== $text) {
                throw new DomainException('O texto de um parágrafo é inválido.');
            }

            if (! is_array($ids) || ! array_is_list($ids) || $ids === []) {
                throw new DomainException('As referências de um parágrafo são inválidas.');
            }

            foreach ($ids as $id) {
                if (! is_int($id) || ! in_array($id, $allowedArticleIds, true)) {
                    throw new DomainException('O rascunho cita um artigo fora do contexto.');
                }
            }

            if (count($ids) !== count(array_unique($ids))) {
                throw new DomainException('As referências de um parágrafo são inválidas.');
            }

            $blocks[] = ['text' => trim($text), 'article_ids' => $ids];
        }

        return [
            'title' => trim($title),
            'category' => $story->category->value,
            'summary_blocks' => $blocks,
        ];
    }
}
