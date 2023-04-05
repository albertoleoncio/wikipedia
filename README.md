# albertoleoncio/wikipedia

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=albertoleoncio_wikipedia&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=albertoleoncio_wikipedia)

Scripts para alguns bots da Wikipédia, dentre outras coisas.

## Usuários

Operador dos bots: https://pt.wikipedia.org/wiki/Usuário:Albertoleoncio

Bots:

- https://pt.wikipedia.org/wiki/User:AlbeROBOT
- https://pt.wikipedia.org/wiki/User:SabiaQueBot
- https://pt.wikipedia.org/wiki/User:EventosAtuaisBot
- https://pt.wikipedia.org/wiki/User:BloqBot

## Script COVID-19

- covid-19-brasil.php - Atualiza os dados dos casos por unidade federativa brasileira. Utiliza os dados do Ministério da Saúde. Link: https://covid.saude.gov.br/.

## Bots

### BloqBot

- blockbadnames.php - Fechador de pedidos de bloqueios por nome impróprio onde a conta já está bloqueada.
- blockcatch.php - Script monitorador de atividade de edições de IP, criado para caso o filtro deixe de funcionar.
- blockrequest.php - Fechador de pedidos de bloqueios por vandalismo onde a conta já está bloqueada.
- blockreverts.php - Anotador de usuários autorrevisores que tiveram suas edições revertidas.
- blockrollback.php - Notificador de incidentes onde reversores efetuaram bloqueios inadequados.
- blockprotect.php - Fecha pedidos de proteção de páginas já cumpridos.

### SabiaQueBot

- sabiaque.php - Realiza procedimentos de atualização da seção "Sabia que".

### EventosAtuaisBot

- eatuais.php - Realiza procedimentos de atualização da seção "Eventos atuais".

### AlbeROBOT

- urcold.php - Script para remover arquivos de conteúdo restrito das categorias de eliminação inseridas erroneamente.
- fnc.php - Gera lista de fontes não confiáveis a partir da categoria da central de confiabilidade.
- editnoticesbot.php - Cria e elimina Editnotices de sobre variantes da Língua Portuguesa nos artigos.
- working.php - Textos em processo de transcrição no Wikisource que foram modificados recentemente.

## Redes sociais

- ead.php - Atualizador das redes sociais da Wikipédia em português com atualições da "Escolha do Artigo em Destaque".
- rss.php - Gera RSS utilizado pelo Zapier com atualizações da "SabiaQue" e "EventosAtuais"
- rsspotd.php - Gera RSS utilizado pelo Zapier com atualições da "Imagem do Dia".

## Utilitários

- apurador.php - Contabilizador de votos.
- db.php - Gerador de pedidos de abertura de discussões de bloqueio.
- siw.php - Script para auxiliar o envio de mensagens para o https://pt.wikipedia.org/wiki/WP:ESR-SIW
- voto.php - Verifica se usuário possui direito a voto de acordo com as regras vigentes da Wikipédia lusófona.
- wikiportugal - Gera código para atualizar https://pt.wikipedia.org/wiki/Usuário:Vanthorn/Wikipedistas_de_Portugal

## Little disclaimer

Sou progamador amador, portanto os scripts foram escritos em PHP em estilo procedural e violando diversas regras de boas práticas de programação. Don't judge me.
