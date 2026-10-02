<?php

namespace App\Ai;

use App\Models\Article;
use App\Models\Story;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

final class StoryDraftOutputValidator
{
    /** @param array<string, mixed> $output
     * @param  Collection<int, Article>  $articles
     * @return array{title: string, category: string, summary_blocks: list<array{text: string, article_ids: list<int>}>}
     */
    public function validate(Story $story, array $output, Collection $articles): array
    {
        $allowedArticleIds = $articles->modelKeys();
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

        if (! is_array($paragraphs) || ! array_is_list($paragraphs) || count($paragraphs) < 1 || count($paragraphs) > 2) {
            throw new DomainException('O rascunho deve conter um ou dois parágrafos.');
        }

        $blocks = [];
        $sourceText = $articles->pluck('content')->filter()->implode(' ');

        foreach ($paragraphs as $paragraph) {
            if (! is_array($paragraph) || array_diff(array_keys($paragraph), ['text', 'article_ids']) !== []
                || count($paragraph) !== 2) {
                throw new DomainException('A estrutura de um parágrafo é inválida.');
            }

            $text = $paragraph['text'];
            $ids = $paragraph['article_ids'];

            if (! is_string($text) || trim($text) === '' || mb_strlen($text) > 500 || strip_tags($text) !== $text) {
                throw new DomainException('O texto de um parágrafo é inválido.');
            }

            if (preg_match('/\b(?:id|article_ids)\s*[:#]\s*\d+\b/i', $text)) {
                throw new DomainException('As referências devem aparecer apenas em article_ids.');
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

            if ($this->copiesLongPassage($text, $sourceText)) {
                throw new DomainException('O rascunho copia um trecho longo de um artigo.');
            }

            $blocks[] = ['text' => trim($text), 'article_ids' => $ids];
        }

        return [
            'title' => trim($title),
            'category' => $story->category->value,
            'summary_blocks' => $blocks,
        ];
    }

    public function copiesLongPassage(string $text, string $source): bool
    {
        $normalize = static fn (string $value): array => array_values(array_filter(explode(' ', trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($value)) ?? ''))));
        $words = $normalize($text);

        if (count($words) < 8) {
            return false;
        }

        $sourceWords = $normalize($source);
        $sourceWindows = [];

        for ($index = 0; $index <= count($sourceWords) - 8; $index++) {
            $sourceWindows[implode(' ', array_slice($sourceWords, $index, 8))] = true;
        }

        $copiedPositions = [];

        for ($index = 0; $index <= count($words) - 8; $index++) {
            if (isset($sourceWindows[implode(' ', array_slice($words, $index, 8))])) {
                for ($position = $index; $position < $index + 8; $position++) {
                    $copiedPositions[$position] = true;
                }
            }
        }

        return count($copiedPositions) / count($words) >= 0.45;
    }
}
