# albertoleoncio/wikipedia

Scripts para alguns bots da Wikipédia, dentre outras coisas.

## Usuários

Operador dos bots: https://pt.wikipedia.org/wiki/Usuário:Albertoleoncio

Bots: https://pt.wikipedia.org/wiki/User:AlbeROBOT, https://pt.wikipedia.org/wiki/User:SabiaQueBot, https://pt.wikipedia.org/wiki/User:BloqBot

## Scripts COVID-19

covid-19-brasil-uf.php - Atualiza os dados dos casos por unidade federativa brasileira. Utiliza os dados das secretarias de saúde das unidades federativas com a curadoria de Álvaro Justen e outros colaboradores. Link: https://brasil.io/dataset/covid19/caso.

covid-19-brasil-cons.php - Atualiza os dados dos casos por unidade federativa brasileira. Utiliza os dados das secretarias de saúde das unidades federativas com a curadoria dos veículos de comunicação UOL, O Estado de S. Paulo, Folha de S.Paulo, O Globo, g1 e Extra. Link: https://brasil.io/dataset/covid19/caso.

covid-19-brasil.php - Atualiza os dados dos casos por unidade federativa brasileira. Utiliza os dados do Ministério da Saúde. Link: https://covid.saude.gov.br/.

## Bots

### BloqBot

blockbadnames.php - Fechador de pedidos de bloqueios por nome impróprio onde a conta já está bloqueada.

blockcatch.php - Script monitorador de atividade de edições de IP, criado para caso o filtro deixe de funcionar.

blockrequest.php - Fechador de pedidos de bloqueios por vandalismo onde a conta já está bloqueada.

blockreverts.php - Anotador de usuários autorrevisores que tiveram suas edições revertidas.

blockrollback.php - Notificador de incidentes onde reversores efetuaram bloqueios inadequados.

blockprotect.php - Fecha pedidos de proteção de páginas já cumpridos.

### SabiaQueBot

sabiaque.php - Realiza procedimentos de atualização da seção "Sabia que".

### EventosAtuaisBot

eatuais.php - Realiza procedimentos de atualização da seção "Eventos atuais".

### AlbeROBOT

caa.php - Atualizador de contagem de candidatos a artigo no painel dos administradores.

urcold.php - Script para remover arquivos de conteúdo restrito das categorias de eliminação inseridas erroneamente.

fnc.php - Gera lista de fontes não confiáveis a partir da categoria da central de confiabilidade.

## Utilitários

apurador.php - Contabilizador de votos.

db.php - Gerador de pedidos de abertura de discussões de bloqueio.

ead.php - Atualizador das redes sociais da Wikipédia em português com atualições da "Escolha do Artigo em Destaque".

potd.php - Atualizador das redes sociais da Wikipédia em português com atualições da "Imagem do Dia".

siw.php - Script para auxiliar o envio de mensagens para o https://pt.wikipedia.org/wiki/WP:ESR-SIW

voto.php - Verifica se usuário possui direito a voto de acordo com as regras vigentes da Wikipédia lusófona.

wikiportugal - Gera código para atualizar https://pt.wikipedia.org/wiki/Usuário:Vanthorn/Wikipedistas_de_Portugal

## Little disclaimer

Sou progamador amador, portanto os scripts foram escritos em PHP em estilo procedural e violando diversas regras de boas práticas de programação. Don't judge me.
