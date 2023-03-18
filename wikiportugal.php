<?php
// Faz uma requisição para obter um texto wiki contendo uma lista de usuários portugueses da Wikipedia
$get = file_get_contents('https://pt.wikipedia.org/w/index.php?title=User:Vanthorn/Wikipedistas_de_Portugal&action=raw');

// Usa uma expressão regular para extrair os nomes dos usuários da lista obtida na requisição anterior
preg_match_all('/#\[\[User:([^|]*)/', $get, $output_array);

// Imprime o cabeçalho de uma tabela HTML que será utilizada para exibir os usuários e suas últimas contribuições na Wikipedia
echo "<pre>{| class=\"wikitable sortable\"\n|-\n!Usuário\n!Última contribuição\n|-\n";

// Itera sobre a lista de usuários extraída pela expressão regular
foreach ($output_array['1'] as $user) {
	// Faz uma requisição à API da Wikipedia para obter a última contribuição do usuário especificado na variável $user
	$api = file_get_contents('https://pt.wikipedia.org/w/api.php?action=query&format=json&list=usercontribs&uclimit=1&ucprop=timestamp&ucuser='.urlencode($user));
	
	// Converte a resposta da API de JSON para um array associativo do PHP e obtém a informação da última contribuição do usuário
	$data = json_decode($api, true)['query']['usercontribs']['0'];
	
	// Imprime uma linha da tabela HTML contendo o nome do usuário e sua última contribuição
	echo "|[[User:".$data['user']."|".$data['user']."]]\n|".$data['timestamp']."\n|-\n";
}

// Imprime o rodapé da tabela HTML
echo "|}";