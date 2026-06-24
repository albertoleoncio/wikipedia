<?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';
date_default_timezone_set('UTC');

class Urcold extends WikiAphpiLogged
{
	/**
	 * Gets files listed in the ESR category for yesterday.
	 *
	 * @return array
	 */
	private function getTargetFiles()
	{
		$params = [
			'action' => 'query',
			'list' => 'categorymembers',
			'cmtitle' => 'Categoria:!Ficheiros para eliminação semirrápida/dia ' . date('j', strtotime('-1 day')),
		];

		$response = $this->see($params);
		return $response['query']['categorymembers'] ?? [];
	}

	/**
	 * Retrieves upload timestamp for a file page.
	 *
	 * @param string $title
	 * @return int|false
	 */
	private function getFileTimestamp($title)
	{
		$params = [
			'action' => 'query',
			'prop' => 'imageinfo',
			'titles' => $title,
			'iiprop' => 'timestamp',
		];

		$response = $this->see($params);
		$pages = $response['query']['pages'] ?? [];
		$pageData = reset($pages);
		$timestamp = $pageData['imageinfo'][0]['timestamp'] ?? false;

		if ($timestamp === false) {
			return false;
		}

		return strtotime($timestamp);
	}

	/**
	 * Updates a file page to include "modificado = sim".
	 *
	 * @param string $page
	 * @return int
	 */
	private function markAsModified($page)
	{
		$wikiCode = $this->get($page);
		$newCode = str_replace('nformação', "nformação\n| modificado = sim", $wikiCode);

		return $this->edit(
			$newCode,
			null,
			true,
			'bot: Inserindo parâmetro "modificado" para evitar eliminação ([[Predefinição Discussão:Informação#Pergunta_técnica_II|detalhes]])',
			$page
		);
	}

	/**
	 * Runs the workflow and returns an execution report.
	 *
	 * @return array
	 */
	public function run()
	{
		$report = [
			'checked_files' => 0,
			'eligible_files' => 0,
			'edited_files' => [],
			'skipped_without_timestamp' => [],
		];

		$files = $this->getTargetFiles();

		foreach ($files as $file) {
			$page = $file['title'];
			$report['checked_files']++;

			$timestamp = $this->getFileTimestamp($page);
			if ($timestamp === false) {
				$report['skipped_without_timestamp'][] = $page;
				continue;
			}

			// Files uploaded before 2011-05-28 (diff 25470547).
			if ($timestamp < 1306540800) {
				$report['eligible_files']++;
				$revisionId = $this->markAsModified($page);
				$report['edited_files'][] = [
					'page' => $page,
					'revision_id' => $revisionId,
				];
			}
		}

		$report['message'] = (count($report['edited_files']) === 0)
			? 'Nenhum ficheiro elegível precisou de edição nesta execução.'
			: 'Execução concluída com atualizações em ficheiros elegíveis.';

		return $report;
	}
}

// Run script
$api = new Urcold('https://pt.wikipedia.org/w/api.php', $username, $password);
print_r($api->run());