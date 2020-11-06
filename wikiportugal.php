<?php

$get = file_get_contents('https://pt.wikipedia.org/w/index.php?title=User:Vanthorn/Wikipedistas_de_Portugal&action=raw');

preg_match_all('/#\[\[User:([^|]*)/', $get, $output_array);

echo "<pre>{| class=\"wikitable sortable\"\n|-\n!Usuário\n!Última contribuição\n|-\n";

foreach ($output_array['1'] as $user) {
	$api = file_get_contents('https://pt.wikipedia.org/w/api.php?action=query&format=json&list=usercontribs&uclimit=1&ucprop=timestamp&ucuser='.urlencode($user));
	$data = json_decode($api, true)['query']['usercontribs']['0'];
	echo "|[[User:".$data['user']."|".$data['user']."]]\n|".$data['timestamp']."\n|-\n";
}

echo "|}";