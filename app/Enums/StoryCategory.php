<?php

namespace App\Enums;

enum StoryCategory: string
{
    case Politica = 'politica';
    case Brasil = 'brasil';
    case Cultura = 'cultura';
    case Entretenimento = 'entretenimento';
    case Economia = 'economia';
    case Mundo = 'mundo';
    case Tecnologia = 'tecnologia';

    public function label(): string
    {
        return match ($this) {
            self::Politica => 'Política',
            self::Brasil => 'Brasil',
            self::Cultura => 'Cultura',
            self::Entretenimento => 'Entretenimento',
            self::Economia => 'Economia',
            self::Mundo => 'Mundo',
            self::Tecnologia => 'Tecnologia',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $category): string => $category->value, self::cases());
    }
}
