<?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';

/**
 * Siw Class
 *
 * The Siw class is responsible for retrieving and processing information related to valid contributors
 * based on verification criteria and user-provided parameters.
 */
class Siw extends WikiAphpiUnlogged
{

	/**
	 * Retrieves all contributors of a given article.
	 *
	 * This method queries the MediaWiki API to fetch the contributors of the specified article.
	 * It excludes contributors with bot rights. The retrieved contributors' names are returned as an array.
	 *
	 * @param string $article The title of the article.
	 *
	 * @return array|false The array of contributor names if contributors are found, false otherwise.
	 */
	private function getAllContributors($article)
	{
	    $apiParams = [
	        "action"          => "query",
	        "format"          => "php",
	        "prop"            => "contributors",
	        "titles"          => $article,
	        "pcexcluderights" => "bot",
	        "pclimit"         => "max"
	    ];

	    $apiResponse = $this->see($apiParams);

	    if (isset($apiResponse['query']['pages'])) {
	        $pageData = reset($apiResponse['query']['pages']);

	        if (!isset($pageData['missing'])) {
	            $list = [];

	            foreach ($pageData['contributors'] as $contributor) {
	                $list[] = $contributor["name"];
	            }

	            return $list;
	        }
	    }

	    return false;
	}

	/**
	 * Retrieves the list of unsubscribed users from the "Predefinição:Aviso-ESR-SIW/Descadastro" page.
	 *
	 * It splits the page content into individual lines and extracts the unsubscribed users' list.
	 * The list is returned as an array.
	 *
	 * @return array The array of unsubscribed users.
	 */
	private function getUnsubscribed()
	{
	    $pageContent = $this->get("Predefinição:Aviso-ESR-SIW/Descadastro", 1);
	    $optoutList = explode("\n#", $pageContent);

	    // Remove the first element, which is the intro of the page
	    $optoutList = array_slice($optoutList, 1);

	    return $optoutList;
	}


	/**
	 * Retrieves the users who made major edits to the given article.
	 *
	 * This method queries the MediaWiki API to fetch the revisions of the specified article.
	 * It filters out anonymous and minor revisions, and returns the usernames of users who made major edits.
	 *
	 * @param string $article The title of the article.
	 *
	 * @return array The array of usernames of users who made major edits to the article.
	 */
	private function getMajorEdits($article)
	{
	    $majorEdits = [];

	    $apiParams = [
	        "action"    => "query",
	        "format"    => "php",
	        "prop"      => "revisions",
	        "titles"    => trim($article),
	        "rvprop"    => "user|flags",
	        "rvlimit"   => "max"
	    ];

	    $apiResponse = $this->see($apiParams);

	    if (isset($apiResponse['query']['pages'])) {
	        $pageData = reset($apiResponse['query']['pages']);

	        if (!isset($pageData['missing'])) {
	            $revisions = $pageData['revisions'];

	            foreach ($revisions as $revision) {
	                if (!isset($revision['anon']) && !isset($revision['minor'])) {
	                    $majorEdits[] = $revision['user'];
	                }
	            }

	        }
	    }

	    return $majorEdits;
	}


	/**
	 * Retrieves information about a user from the MediaWiki API.
	 *
	 * This method queries the MediaWiki API to retrieve information about a user.
	 * It fetches details such as block info, global user info, and user contributions.
	 * The method returns the query result as an array.
	 *
	 * @param string $user The username of the user.
	 *
	 * @return array The query result containing user information.
	 */
	private function getUserInformation($user)
	{
	    $apiParams = [
	        "action"    => "query",
	        "format"    => "php",
	        "list"      => "users|usercontribs",
	        "meta"      => "globaluserinfo",
	        "usprop"    => "blockinfo",
	        "ususers"   => $user,
	        "ucuser"    => $user,
	        "guiuser"   => $user,
	        "uclimit"   => "1"
	    ];

	    return $this->see($apiParams)["query"];
	}

	/**
	 * Verifies the status of a contributor based on various criteria.
	 *
	 * This method verifies the status of a contributor by performing several checks.
	 * It takes into account the opt-out list, major edits requirement, block status,
	 * global lock status, and activity level of the contributor.
	 *
	 * @param string $user      The username of the contributor.
	 * @param array  $optout    The opt-out list containing usernames that should be excluded.
	 * @param array  $majors    The list of usernames of contributors who made major edits (optional).
	 * @param int    $inactive  The level of contributor inactivity (default: 1).
	 *                          Possible values: 1 (active), 2 (inactive for 90 days), 3 (inactive for 180 days),
	 *                          4 (inactive for 365 days), 5 (inactive for 1825 days).
	 *
	 * @return bool|int False if the contributor does not meet the verification criteria,
	 *                  or the number of days of inactivity.
	 */
	private function verifyContributor($user, $optout = [], $majors = [], $inactive = 1)
	{
	    // Check if the user is in the opt-out list and return false if true
	    if (in_array($user, $optout)) {
	        return false;
	    }

	    // Check if major edits are required and if the user is in the majors list, return false if not
	    if (!empty($majors) && !in_array($user, $majors)) {
	        return false;
	    }

	    //Get user information
	    $apiUser = $this->getUserInformation($user);

	    // Check if the user is blocked and return false if true
	    if (isset($apiUser['users']["0"]['blockid']) && !isset($apiUser['users']["0"]['blockpartial'])) {
	        return false;
	    }

	    // Check if the user is globally locked and return false if true
	    if (isset($apiUser['globaluserinfo']['locked'])) {
	        return false;
	    }

	    // Check if the user's activity level is selected and return false if inactive for the specified period
	    if ($inactive !== 1) {
	        $lastEditTimestamp = strtotime($apiUser['usercontribs']["0"]['timestamp']);
	        $daysInactive = round((time() - $lastEditTimestamp) / 86400);

	        if ($inactive == 5 && $daysInactive >= 1825) {
	            return false;
	        } elseif ($inactive == 4 && $daysInactive >= 365) {
	            return false;
	        } elseif ($inactive == 3 && $daysInactive >= 180) {
	            return false;
	        } elseif ($inactive == 2 && $daysInactive >= 90) {
	            return false;
	        }

	        return $daysInactive;
	    }
	}

	/**
	 * Retrieves valid contributors based on verification criteria.
	 *
	 * This method retrieves the list of all contributors to a specified article.
	 * It then applies verification checks to filter out contributors who do not meet the specified criteria.
	 * The criteria include being opt-outed, making major edits (if specified), and meeting the specified level of inactivity.
	 *
	 * @param string $formTitle     The title of the article.
	 * @param int    $formInactive  The level of contributor inactivity (1 for active, 2 for inactive 90 days,
	 *                              3 for inactive 180 days, 4 for inactive 365 days, 5 for inactive 1825 days).
	 * @param bool   $formNoMinor   Flag indicating whether to consider minor edits as valid edits (default: false).
	 *
	 * @return array|false The array of valid contributors and their respective inactivity periods, or false if no valid contributors are found.
	 */
	public function getValidContributors($formTitle, $formInactive, $formNoMinor)
	{
	    $allContributors = $this->getAllContributors($formTitle);

	    if ($allContributors === false) {
	        return false;
	    }

	    $optoutList = $this->getUnsubscribed();
	    $majorsList = ($formNoMinor) ? $this->getMajorEdits($formTitle) : [];

	    $validContributors = [];
	    foreach ($allContributors as $contributor) {
	        $verificationResult = $this->verifyContributor($contributor, $optoutList, $majorsList, $formInactive);
	        if ($verificationResult !== false) {
	            $validContributors[$contributor] = $verificationResult;
	        }
	    }

	    return $validContributors;
	}

}    


//Filter $_GET
$formNoMinor = (isset($_GET["menor"])) ? true : false ;
$formInactive = filter_var($_GET["inativo"] ?? 1, FILTER_SANITIZE_NUMBER_INT);
$formTitle = filter_var($_GET["artigo_titulo"] ?? "", FILTER_SANITIZE_STRING);
$formTitle = str_replace('_', ' ', $formTitle);
if (empty($formTitle)) $formTitle = false;

//Call Siw class
$api = new Siw('https://pt.wikipedia.org/w/api.php');
$users = $api->getValidContributors($formTitle, $formInactive, $formNoMinor);
if(count($users) != 0) { 
    $userArray = json_encode(array_keys($users));
}

?><!DOCTYPE html>
<html lang="pt-BR">
	<head>
		<title>ESR-SIW</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="./tpar/w3.css">
	    <script type="text/javascript">

	        // JavaScript function to send notifications to all editors
	        function sendAvisos() {

	            // Get user array from PHP and iterate through each user
	            var formTitle = '<?=$formTitle?>';
	            
	            var users = <?=$userArray?>;
	            if (users.length > 1) {
			        alert('Lembre-se de habilitar os pop-ups!');
			    }
	            
	            users.forEach(function(user) {

	                var url = new URL('https://pt.wikipedia.org/w/index.php');
					var params = new URLSearchParams();
					params.append('title', 'User_talk:' + user);
					params.append('action', 'edit');
					params.append('section', 'new');
					params.append('preloadtitle', '[[' + formTitle + ']] ([[WP:ESR-SIW]])');
					params.append('preload', 'Template:Aviso-ESR-SIW/Preload');
					params.append('preloadparams[]', formTitle);
					params.append('preloadparams[]', ''); // Empty preloadparams[] parameter

					url.search = params.toString();

					var fullUrl = url.toString();

	                // Open a pop-up window for each user
	                window.open(fullUrl, '_blank');
	            });
	        }
	    </script>
	</head>
	<body>
		<div class="w3-container" id="menu">
			<div class="w3-content" style="max-width:800px">
				<h5 class="w3-center w3-padding-48"><span class="w3-tag w3-wide">ESR-SIW</span></h5>
				<div class="w3-row-padding w3-center w3-margin-top">
					<div class="w3-half">
						<form method="get">
							<div class="w3-container w3-padding-48 w3-card">
		      					<p class="w3-center w3-wide">ARTIGO</p>
		      					<p class="w3-text-grey">
		      						<input
		      						class="w3-input w3-padding-16 w3-border"
		      						value='<?=($formTitle)?:"";?>'
		      						type="text"
		      						name="artigo_titulo">
		      					</p>
		      					<br>
		      					<p class="w3-center w3-wide">EDITORES INATIVOS</p>
		      					<p class="w3-text-grey">
			      					<select class="w3-select w3-border" name="inativo">
										<option value="" disabled>Selecione...</option>
										<option value="1" <?=($formTitle && $formInactive == 1)?"selected":"";?>>Incluir todos os editores</option>
										<option value="2" <?=($formTitle && $formInactive == 2)?"selected":"";?>>Remover inativos há 3 meses</option>
										<option value="3" <?=($formTitle && $formInactive == 3)?"selected":"";?>>Remover inativos há 6 meses</option>
										<option value="4" <?=($formTitle && $formInactive == 4)?"selected":"";?>>Remover inativos há 1 ano</option>
										<option value="5" <?=($formTitle && $formInactive == 5)?"selected":"";?>>Remover inativos há 5 anos</option>
									</select>
								</p>
		      					<br>
								<p class="w3-center w3-wide">EDIÇÕES MENORES</p>
		      					<p>
		      						<input
		      						name="menor"
		      						class="w3-check"
		      						type="checkbox"
		      						<?=($formTitle && $formNoMinor)?"checked":"";?>>
		      						<label>Excluir edições menores</label>
		      					</p>
		      					<br>
		      					<p>
			      					<button class="w3-button w3-block w3-black w3-margin-top" type="submit">Verificar</button>
			      				</p>
		      				</div>
		      			</form>
		      		</div>
		      		<div class="w3-half">
		      			<div class="w3-container w3-padding-48 w3-card">
		      				<?php if(!$formTitle): ?>
		      					<!-- Display a message prompting the user to fill the form. -->
		      					<h3 class='w3-center' style='hyphens: auto;'>
		      						<b>Preencha o formulário ao lado.</b>
		      					</h3>
		      				<?php elseif(!$users): ?>
		      					<!-- Display a message indicating that the specified article does not exist. -->
		      					<h3 class='w3-center' style='hyphens: auto;'>
		      						<b>Artigo <?=$formTitle?> não existe!</b>
		      					</h3>
		      				<?php else: ?>
		      					<!-- Display the list of article editors and provide options for sending notifications. -->
		      					<p class='w3-center w3-wide'>EDITORES DO ARTIGO</p>
								<h3 class='w3-center' style='hyphens: auto;'>
									<b><?=$formTitle?></b>
								</h3>
								<small>Ao clicar, uma nova janela será aberta para o envio da mensagem. Em seguida, clique em "Publicar alterações", ou use o atalho ALT+SHIFT+S.</small>
								<br>
								<br>
								<ul class='w3-ul w3-hoverable w3-border'>
									<?php foreach(array_keys($users) as $user): ?>
										<!-- Generate a list item for each article editor. -->
										<?php 
											$individual_link = [
												'title' 			=> "User talk:{$user}",
												'action' 			=> 'edit',
												'section' 			=> 'new',
												'preloadtitle' 		=> "[[{$formTitle}]] ([[WP:ESR-SIW]])",
												'preload' 			=> 'Predefinição:Aviso-ESR-SIW/Preload',
												'preloadparams[]'	=> trim($formTitle)
											];
											$individual_link = http_build_query($individual_link);
										?>
										<li class="w3-padding-small w3-left-align" style="cursor:pointer;">
											<a style='text-decoration-line:none' target='_blank'
											href='https://pt.wikipedia.org/w/index.php?<?=$individual_link?>&preloadparams%5b%5d='
											><?=$user?></a>
											<?php if($users[$user] > 90): ?>
												<!-- Display the number of inactive days if it exceeds 90 -->
												<small>(inativo há <?=$users[$user]?> dias)</small>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
									<?php if(count($users) != 0): ?>
										<!-- Button to trigger the sendAvisos() function -->
									    <button type="button" onclick="sendAvisos()">Avisar todos</button>
									<?php else: ?>
									    <!-- Display a message if there are no users to notify -->
									    <p>Não há quem deseje ser avisado.</p>
									<?php endif; ?>
								</ul>
							<?php endif; ?>
						</div>
		      		</div>
		      	</div>
      		</div>
      	</div>
		<hr>
		<?php include('footer.html'); ?>
	</body>
</html>
