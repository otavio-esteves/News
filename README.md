# News

Agregador de notícias com sínteses rastreáveis. O plano do produto está em [NEWS_PLAN.md](NEWS_PLAN.md).

## Requisitos

- Docker com Compose
- Make

PHP, Composer, Node e PostgreSQL são executados nos containers.

## Desenvolvimento

```sh
make setup
```

O comando cria `.env`, instala dependências, gera a chave da aplicação, executa as migrations e inicia os serviços. Abra <http://localhost:8000> para ver os títulos coletados de fontes reais. O Vite usa a porta 5174.
O seeder padrão cadastra apenas as fontes reais em qualquer ambiente e pode ser executado novamente sem duplicá-las. Em uma instalação de produção, execute `php artisan db:seed --force` no container da aplicação após as migrations.

Comandos úteis:

```sh
make up
make down
make test
make lint
make logs
make artisan cmd="route:list"
make artisan cmd="news:discover"
make artisan cmd="schedule:list"
make artisan cmd="queue:failed"
make artisan cmd="queue:restart"
make artisan cmd="news:remove-preview"
make artisan cmd="news:write-draft 123"
make composer cmd="install"
make npm cmd="run build"
```

Se as portas 8000 ou 5174 estiverem ocupadas, defina `APP_PORT` ou `VITE_PORT` no `.env`.
Em hosts cujo usuário não é 1000:1000, configure `LOCAL_UID` e `LOCAL_GID` no `.env`.

O endpoint `/up` fornece o health check da aplicação. O PostgreSQL tem um health check próprio no Compose.

Para expor a instalação local por um Cloudflare Quick Tunnel, gere os assets com `make npm cmd="run build"` e pare o serviço `node` com `docker compose stop node`. O Vite em modo de desenvolvimento cria `public/hot` e anuncia assets na porta local 5174, inacessível pelo túnel. Configure `TRUSTED_PROXIES` no `.env` com o IP do gateway do Docker que encaminha as requisições ao container `app` e recrie esse container com `docker compose up -d --no-deps --force-recreate app`. Assim os links dos assets usam o esquema HTTPS recebido pelo proxy. Execute `cloudflared tunnel --url http://127.0.0.1:8000` no host; o endereço gerado dura somente enquanto o processo estiver ativo.

## Estado atual

M6: a página inicial mostra títulos, veículos, autores quando disponíveis e links para matérias extraídas de fontes reais. As sínteses em Stories só aparecem após publicação editorial. O seeder padrão cadastra nove fontes reais; dados fictícios deixaram de ser criados e `news:remove-preview` remove os antigos registros de demonstração. O tema claro/escuro pode ser alternado e a escolha fica salva no navegador.

Os temas continuam no centro da barra superior, e o menu à esquerda de NEWS oferece os mesmos temas e as fontes habilitadas. As matérias da Agência Brasil e da Radioagência Nacional usam a seção da URL oficial; as da Agência Senado e Agência Câmara entram em Política. CNJ e IBGE ficam sem editoria automática até classificação editorial. Se a seção não tiver correspondência, a matéria continua na página inicial e não aparece em uma editoria até ser classificada. A migração de `articles.category` também classifica as matérias já coletadas.

M7: cada nova matéria de uma fonte real é comparada com rascunhos da mesma editoria publicados em uma janela de 36 horas. Títulos com as mesmas palavras relevantes podem entrar no mesmo acontecimento; sem candidato, surge uma nova Story em rascunho. Correspondências incertas ficam como Articles não associados para revisão posterior. O Scheduler reavalia matérias antigas ou pendentes a cada 30 minutos; o comando `make artisan cmd="news:match-articles"` também pode ser executado manualmente. Rascunhos nunca aparecem no site público antes da aprovação editorial. As listas públicas de Stories são paginadas em grupos de 20.

O Scheduler executa `news:discover` a cada cinco minutos. O comando seleciona fontes habilitadas cujo intervalo venceu e envia um job único por fonte à Database Queue. O worker tenta novamente falhas transitórias até três vezes; jobs esgotados ficam em `failed_jobs` e podem ser consultados com `make artisan cmd="queue:failed"` e repetidos com `make artisan cmd="queue:retry all"`.
Depois de alterar adapters ou sua configuração, execute `make artisan cmd="queue:restart"` para o worker carregar o código novo.

A primeira fonte é a Agência Brasil, via [RSS oficial](https://agenciabrasil.ebc.com.br/feed/), com coleta a cada 30 minutos. O adapter aceita apenas itens atribuídos à própria Agência Brasil; exclui parceiros, scripts e imagens, guarda texto normalizado e o link original. Essa escolha segue a [política de reprodução da agência](https://agenciabrasil.ebc.com.br/sobre), que prevê atribuição, e o [robots.txt](https://agenciabrasil.ebc.com.br/robots.txt), que indica intervalo mínimo de 10 segundos entre acessos. O sistema faz uma única requisição por execução ao RSS, sem buscar cada página.

A segunda fonte é a Agência Senado, via [RSS oficial](https://www12.senado.leg.br/noticias/rss.xml) e páginas de matérias. A [política de uso](https://www12.senado.leg.br/assessoria-de-imprensa/noticias/politica-de-uso) permite reprodução com citação da agência e do autor. O adapter consulta até três matérias por execução, valida domínio e caminho, não segue redirecionamentos e remove imagens e elementos externos do texto armazenado. O [robots.txt](https://www12.senado.leg.br/robots.txt) não bloqueia essas páginas.

A terceira fonte é a Agência Câmara, pelo [RSS oficial](https://www.camara.leg.br/noticias/rss), que já inclui o texto das matérias. A agência [autoriza a reprodução com atribuição](https://www.camara.leg.br/noticias/1281197-rede-legislativa-de-radio-deve-ampliar-alcance-com-novas-emissoras-e-tecnologias/). O adapter faz uma única requisição por coleta, remove HTML externo e apresenta o veículo e o link original. As matérias entram em Política, como as da Agência Senado.

A quarta fonte é a Radioagência Nacional, pelo [RSS oficial da EBC](https://agenciabrasil.ebc.com.br/feed/). O feed traz o texto da notícia e o crédito do repórter. A [EBC permite a reutilização de conteúdo próprio com atribuição, mas exclui material de parceiros](https://acessoainformacao.ebc.com.br/participacao-social/ouvidoria/relatorios/relatorios-da-ouvidoria/2023-04.pdf); o adapter exige autoria da Rádio Nacional e URLs do seu canal. O [robots.txt](https://agenciabrasil.ebc.com.br/robots.txt) pede dez segundos entre acessos; os jobs dos feeds da EBC compartilham esse intervalo.

A quinta fonte é a Agência CNJ, pela [API pública do portal](https://www.cnj.jus.br/wp-json/wp/v2/posts?categories=1415&per_page=10&_fields=id,date_gmt,link,title,content,categories). A [página da Agência CNJ](https://www.cnj.jus.br/agencia-cnj/) permite usar conteúdo próprio ou de parceiros com citação da fonte. O adapter seleciona apenas a categoria oficial de notícias do CNJ, exclui a categoria de notícias de outros órgãos e verifica os links originais.

A sexta fonte é a Agência de Notícias do IBGE, pelo [RSS oficial](https://agenciadenoticias.ibge.gov.br/agencia-rss). O adapter seleciona releases do próprio portal e preserva o autor informado no feed. A [licença institucional](https://agenciadenoticias.ibge.gov.br/agencia-sala-de-imprensa/2080-agencia-de-noticias/an-artigos-diversos/9277-licenca.html) permite reprodução com crédito à fonte; a página da licença respondeu HTTP 403 quando acessada diretamente desta rede, portanto essa condição deve ser confirmada novamente antes de usar o texto em sínteses editoriais.

Folha, G1, Meio e Estadão entram como fontes de **títulos e links** pelos seus RSS públicos. A Folha [lista o feed em seu site](https://www1.folha.uol.com.br/feed/); os feeds [G1](https://g1.globo.com/rss/g1/), [Meio](https://www.canalmeio.com.br/feed/) e [Estadão](https://www.estadao.com.br/arc/outboundfeeds/rss/?outputType=xml) responderam normalmente na verificação de 29/09/2026. O News guarda apenas metadados e links diretos dessas fontes: não armazena o corpo das matérias, não as associa automaticamente a Stories e não as envia à IA. Itens ao vivo do G1 e edições agregadoras do Meio são ignorados. A integração da CartaCapital está preparada, mas a fonte fica desativada até haver autorização escrita para exibir seus títulos. Mais detalhes constam em [docs/source-research.md](docs/source-research.md).

A ingestão normaliza URLs HTTPS, remove parâmetros de rastreamento e usa o caminho da notícia como identidade no adapter da Agência Brasil. A URL canônica tem índice único. O hash do texto normalizado detecta cópias dentro da mesma fonte; fontes diferentes mantêm artigos próprios para preservar a atribuição. Ao encontrar uma URL ou conteúdo já registrado, a coleta mantém o primeiro registro. Alterações editoriais de uma matéria existente exigirão um fluxo próprio de atualização em marco posterior.

`make test` usa SQLite em memória e não altera o PostgreSQL de desenvolvimento.

O M8 gera sínteses em `story_drafts` com revisão editorial obrigatória. Por padrão, usa o [Qwen3 4B quantizado](https://ollama.com/library/qwen3%3A4b) local via Ollama e o Laravel AI SDK. O Qwen3 1.7B foi testado, mas repetiu trechos extensos dos artigos; o 4B preservou melhor as entidades no teste local. Suba o serviço e baixe o modelo uma vez:

```bash
docker compose up -d ollama ai-worker
docker compose exec ollama ollama pull qwen3:4b
```

Para gerar uma Story em fila, use `docker compose exec app php artisan news:write-draft ID --queue`. Para enfileirar Stories elegíveis em lote, use `docker compose exec app php artisan news:queue-drafts --limit=5`. Depois de confirmar que o modelo está instalado, defina `NEWS_AI_AUTO_QUEUE=true` no `.env` e reinicie o scheduler; ele enfileirará no máximo uma Story a cada cinco minutos. O worker `ai-worker` processa uma síntese por vez, separado da fila de coleta. O comando `news:write-draft ID` continua disponível para execução síncrona.

O fluxo aceita de um a dez Articles com texto e fonte real habilitada, valida título, categoria, parágrafos, referências, IDs internos no texto e cópia extensa, registra cada tentativa em `ai_runs` e nunca publica automaticamente. O modelo local recebe até 2.500 caracteres por artigo para limitar o contexto. Falhas de validação não criam rascunho; uma nova tentativa manual pode ser enfileirada com `--queue`. Para usar OpenAI, configure `NEWS_AI_STORY_WRITER_PROVIDER=openai`, `NEWS_AI_STORY_WRITER_MODEL=gpt-4o-mini` e `OPENAI_API_KEY`.

A revisão editorial e a publicação das Stories entram nos marcos seguintes.
