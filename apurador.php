<?php

$get = file_get_contents('https://pt.wikipedia.org/w/index.php?title=Wikip%C3%A9dia:Vota%C3%A7%C3%B5es/Necessidade_de_registo_para_editar_a_Wikip%C3%A9dia_lus%C3%B3fona&action=raw');
$sect = explode("\n==", $get);
$x = array();
$y = 1;
foreach ($sect as $section) {
	$lines = explode("\n", $section);
	foreach ($lines as $line) {
		if (substr($line, 0, 1) == "#" AND substr($line, 0, 2) != "#:") $x[$y]++;
		if (substr($line, 0, 4) == "#...") $x[$y]--;
	}
	$y++;
}
?><!DOCTYPE html>
<html lang="pt-BR">
	<head>
		<title>Apurador</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="./tpar/w3.css">
	</head>
	<body>
		<div class="w3-container" id="menu">
			<div class="w3-content" style="max-width:1200px">
				<h5 class="w3-center w3-padding-48"><span class="w3-tag w3-wide">APURADOR</span></h5>
				<div class="w3-row-padding w3-center w3-margin-top">
					<div class="w3-half">
						<div class="w3-container w3-padding-48 w3-card">
							<p class="w3-center w3-wide">ABRANGÊNCIA</p>
							<div class="w3-responsive">
								<table class="w3-table w3-bordered">
									<tr>
										<th>Pergunta</th>
										<th>A favor</th>
										<th>Contra</th>
										<th>Abstenção</th>
										<th>Total</th>
									</tr>
									<tr>
										<td title='Restringir domínios principais'>1.1</td>
										<td><?php echo $x['4']." (".round((100*$x['4'])/($x['4']+$x['5']),1);?>%)</td>
										<td><?php echo $x['5']." (".round((100*$x['5'])/($x['4']+$x['5']),1);?>%)</td>
										<td><?php echo $x['6'];?></td>
										<td><?php echo ($x['4']+$x['5']+$x['6']);?></td>
									</tr>
									<tr>
										<td title='Exceção para discussão'>1.2</td>
										<td><?php echo $x['8']." (".round((100*$x['8'])/($x['8']+$x['9']),1);?>%)</td>
										<td><?php echo $x['9']." (".round((100*$x['9'])/($x['8']+$x['9']),1);?>%)</td>
										<td><?php echo $x['10'];?></td>
										<td><?php echo ($x['8']+$x['9']+$x['10']);?></td>
									</tr>
									<tr>
										<td title='Exceção para ajuda'>1.3</td>
										<td><?php echo $x['12']." (".round((100*$x['12'])/($x['12']+$x['13']),1);?>%)</td>
										<td><?php echo $x['13']." (".round((100*$x['13'])/($x['12']+$x['13']),1);?>%)</td>
										<td><?php echo $x['14'];?></td>
										<td><?php echo ($x['12']+$x['13']+$x['14']);?></td>
									</tr>
									<tr>
										<td title='Restringir criação de artigos'>1.4</td>
										<td><?php echo $x['16']." (".round((100*$x['16'])/($x['16']+$x['17']),1);?>%)</td>
										<td><?php echo $x['17']." (".round((100*$x['17'])/($x['16']+$x['17']),1);?>%)</td>
										<td><?php echo $x['18'];?></td>
										<td><?php echo ($x['16']+$x['17']+$x['18']);?></td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div class="w3-half">
						<div class="w3-container w3-padding-48 w3-card">
							<p class="w3-center w3-wide">FERRAMENTAS</p>
							<div class="w3-responsive">
								<table class="w3-table w3-bordered">
									<tr>
										<th>Ferramenta</th>
										<th>A favor</th>
										<th>Contra</th>
										<th>Abstenção</th>
										<th>Total</th>
									</tr>
									<tr>
										<td title='Range blocks'>2.1</td>
										<td><?php echo $x['21']." (".round((100*$x['21'])/($x['21']+$x['22']),1);?>%)</td>
										<td><?php echo $x['22']." (".round((100*$x['22'])/($x['21']+$x['22']),1);?>%)</td>
										<td><?php echo $x['23'];?></td>
										<td><?php echo ($x['21']+$x['22']+$x['23']);?></td>
									</tr>
									<tr>
										<td title='Scripts'>2.2</td>
										<td><?php echo $x['25']." (".round((100*$x['25'])/($x['25']+$x['26']),1);?>%)</td>
										<td><?php echo $x['26']." (".round((100*$x['26'])/($x['25']+$x['26']),1);?>%)</td>
										<td><?php echo $x['27'];?></td>
										<td><?php echo ($x['25']+$x['26']+$x['27']);?></td>
									</tr>
									<tr>
										<td title='Filtro de edições'>2.3</td>
										<td><?php echo $x['29']." (".round((100*$x['29'])/($x['29']+$x['30']),1);?>%)</td>
										<td><?php echo $x['30']." (".round((100*$x['30'])/($x['29']+$x['30']),1);?>%)</td>
										<td><?php echo $x['31'];?></td>
										<td><?php echo ($x['29']+$x['30']+$x['31']);?></td>
									</tr>
									<tr>
										<td title='Proteção autoconfirmado'>2.4</td>
										<td><?php echo $x['33']." (".round((100*$x['33'])/($x['33']+$x['34']),1);?>%)</td>
										<td><?php echo $x['34']." (".round((100*$x['34'])/($x['33']+$x['34']),1);?>%)</td>
										<td><?php echo $x['35'];?></td>
										<td><?php echo ($x['33']+$x['34']+$x['35']);?></td>
									</tr>
								</table>
							</div>
						</div>
					</div>
				</div>
				<div class="w3-center w3-margin-top" style="padding: 0 16px;">
					<div class="w3-container w3-padding-48 w3-card">
						<p class="w3-center w3-wide">RITMO DE IMPLANTAÇÃO</p>
						<div class="w3-responsive">
							<table class="w3-table w3-bordered">
								<tr>
									<th>Imediatamente</th>
									<th>Gradualmente</th>
									<th>Total</th>
								</tr>
								<tr>
									<td><?php echo $x['37']." (".round((100*$x['37'])/($x['37']+$x['38']),1);?>%)</td>
									<td><?php echo $x['38']." (".round((100*$x['38'])/($x['37']+$x['38']),1);?>%)</td>
									<td><?php echo ($x['37']+$x['38']);?></td>
								</tr>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
		<hr>
		<a href="https://wikitech.wikimedia.org/wiki/Portal:Toolforge"><img src="https://tools-static.wmflabs.org/toolforge/banners/Powered-by-Toolforge-button.png" alt="Powered by Wikimedia Toolforge"></a>
	</body>
</html>