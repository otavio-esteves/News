<?php

namespace Tests\Fixtures;

final class PreviewStories
{
    public static function all(): array
    {
        return [
            [
                'slug' => 'aurora-amplia-horario-das-bibliotecas',
                'category' => 'brasil',
                'title' => 'Aurora amplia o horário de funcionamento das bibliotecas de bairro',
                'published_at' => '2026-09-27T14:32:00-03:00',
                'paragraphs' => [
                    [
                        'text' => 'A prefeitura de Aurora anunciou um teste de oito semanas com horário estendido em três bibliotecas de bairro. Durante esse período, as unidades participantes ficarão abertas até as 20h nos dias úteis.',
                        'sources' => ['boletim', 'jornal'],
                    ],
                    [
                        'text' => 'Segundo o comunicado, a avaliação considerará o número de visitantes e a procura por atividades no fim do dia. Ainda não há decisão sobre ampliar a mudança para as demais unidades.',
                        'sources' => ['boletim'],
                    ],
                ],
                'sources' => [
                    'boletim' => ['name' => 'Boletim de Aurora', 'title' => 'Bibliotecas terão horário estendido em projeto piloto'],
                    'jornal' => ['name' => 'Jornal do Bairro', 'title' => 'Três bibliotecas participam de teste de atendimento noturno'],
                ],
            ],
            [
                'slug' => 'vale-verde-testa-onibus-eletricos',
                'category' => 'economia',
                'title' => 'Vale Verde inicia teste de ônibus elétricos em duas linhas',
                'published_at' => '2026-09-27T13:10:00-03:00',
                'paragraphs' => [
                    [
                        'text' => 'A cooperativa de transporte de Vale Verde colocou quatro ônibus elétricos em circulação experimental em duas linhas municipais. O período de teste previsto é de três meses.',
                        'sources' => ['cooperativa', 'gazeta'],
                    ],
                    [
                        'text' => 'A cooperativa informou que acompanhará o consumo de energia, a autonomia e os gastos de manutenção antes de decidir sobre uma eventual expansão da frota.',
                        'sources' => ['cooperativa'],
                    ],
                ],
                'sources' => [
                    'cooperativa' => ['name' => 'Cooperativa Vale Verde', 'title' => 'Operação experimental de veículos elétricos'],
                    'gazeta' => ['name' => 'Gazeta Local', 'title' => 'Linhas municipais recebem quatro ônibus para teste'],
                ],
            ],
            [
                'slug' => 'universidade-abre-laboratorio-de-energia-solar',
                'category' => 'tecnologia',
                'title' => 'Universidade do Litoral abre laboratório dedicado à energia solar',
                'published_at' => '2026-09-27T11:48:00-03:00',
                'paragraphs' => [
                    [
                        'text' => 'A Universidade do Litoral inaugurou um laboratório para pesquisas sobre materiais e aplicações de energia solar. O espaço também receberá projetos de estudantes de graduação.',
                        'sources' => ['universidade', 'observador'],
                    ],
                    [
                        'text' => 'A primeira etapa de trabalho inclui medições de desempenho de pequenos sistemas fotovoltaicos. A instituição ainda não divulgou resultados dos experimentos.',
                        'sources' => ['universidade'],
                    ],
                ],
                'sources' => [
                    'universidade' => ['name' => 'Universidade do Litoral', 'title' => 'Novo laboratório inicia atividades de pesquisa'],
                    'observador' => ['name' => 'Observador do Litoral', 'title' => 'Campus apresenta estrutura para estudos em energia solar'],
                ],
            ],
            [
                'slug' => 'museu-serra-alta-programacao-gratuita',
                'category' => 'cultura',
                'title' => 'Museu de Serra Alta anuncia programação gratuita para outubro',
                'published_at' => '2026-09-26T17:05:00-03:00',
                'paragraphs' => [
                    [
                        'text' => 'O Museu de Serra Alta anunciou uma programação gratuita de visitas guiadas e oficinas para os fins de semana de outubro. As atividades terão vagas limitadas.',
                        'sources' => ['museu', 'caderno'],
                    ],
                    [
                        'text' => 'De acordo com o museu, as inscrições serão abertas no início do mês. Os horários completos ainda serão publicados pela instituição.',
                        'sources' => ['museu'],
                    ],
                ],
                'sources' => [
                    'museu' => ['name' => 'Museu de Serra Alta', 'title' => 'Agenda de atividades de outubro'],
                    'caderno' => ['name' => 'Caderno Cultural', 'title' => 'Museu prepara oficinas e visitas gratuitas'],
                ],
            ],
        ];
    }
}
