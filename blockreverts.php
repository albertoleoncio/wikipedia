<?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';

class BlockReverts extends WikiAphpiLogged
{
	/** @var string */
	private $reportPage = 'Usuário(a):BloqBot/Reversões';

	/** @var string */
	private $donePage = 'Usuário(a):BloqBot/revd';

	/**
	 * Retrieves all users in autoreviewer-related groups.
	 *
	 * @return array
	 */
	private function getAutoreviewers()
	{
		$users = [];
		$params = [
			'action'  => 'query',
			'list'    => 'allusers',
			'augroup' => 'autoreviewer|bureaucrat|eliminator|sysop',
			'aulimit' => '500',
		];

		do {
			$response = $this->see($params);
			foreach ($response['query']['allusers'] ?? [] as $user) {
				$users[] = $user['name'];
			}

			if (isset($response['continue']['aufrom'])) {
				$params['aufrom'] = $response['continue']['aufrom'];
			} else {
				unset($params['aufrom']);
			}
		} while (isset($response['continue']['aufrom']));

		return array_unique($users);
	}

	/**
	 * Retrieves rollback-tagged recent changes from the last 30 minutes.
	 *
	 * @return array
	 */
	private function getRecentRollbacks()
	{
		$params = [
			'action'  => 'query',
			'list'    => 'recentchanges',
			'rctag'   => 'mw-rollback',
			'rcprop'  => 'title|user|ids|comment|tags',
			'rclimit' => '500',
			'rcend'   => gmdate('Y-m-d\\TH:i:s.000\\Z', strtotime('-30 minutes')),
		];

		$response = $this->see($params);
		return $response['query']['recentchanges'] ?? [];
	}

	/**
	 * Extracts rollback cases that should be reported.
	 *
	 * @param array $rollbacks
	 * @param array $autoreviewers
	 * @return array
	 */
	private function getCasesToNotify(array $rollbacks, array $autoreviewers)
	{
		$notify = [];

		foreach ($rollbacks as $rollback) {
			preg_match_all(
				'/(?:Foi \[\[WP:REV\|revertida\]\] a edição|Foram \[\[WP:REV\|revertidas\]\] as edições) de \[\[Special:Contrib(?:s|uições|utions)\/\K[^\]\|]*/',
				$rollback['comment'] ?? '',
				$rollbacked
			);

			if (!isset($rollbacked[0][0])) {
				continue;
			}

			if (($rollbacked[0][0] ?? '') === ($rollback['user'] ?? '')) {
				continue;
			}

			if (!in_array($rollbacked[0][0], $autoreviewers, true)) {
				continue;
			}

			$notify[] = [
				'id'     => $rollback['revid'],
				'user'   => $rollback['user'],
				'title'  => $rollback['title'],
				'target' => $rollbacked[0][0],
			];
		}

		return $notify;
	}

	/**
	 * Sends a single notification message to Telegram.
	 *
	 * @param array $case
	 * @return string|false
	 */
	private function sendTelegram(array $case)
	{
		global $TelegramToken;

		$telegramContext = [
			'http' => [
				'method'  => 'POST',
				'header'  => "Content-Type:application/x-www-form-urlencoded\r\n",
				'content' => http_build_query([
					'chat_id'    => -1001169425230,
					'parse_mode' => 'MarkdownV2',
					'text'       => '[\\[Δ' . $case['id'] . '\\]](https://pt.wikipedia.org/wiki/Special:diff/' . $case['id'] . '): [' . $case['target'] . '](https://pt.wikipedia.org/wiki/User:' . $case['target'] . ') revertido por [' . $case['user'] . '](https://pt.wikipedia.org/wiki/User:' . $case['user'] . ') em [' . $case['title'] . '](https://pt.wikipedia.org/wiki/' . $case['title'] . ')\\.',
				]),
			],
		];

		$telegramStream = stream_context_create($telegramContext);
		return file_get_contents('https://api.telegram.org/bot' . $TelegramToken . '/sendMessage', false, $telegramStream);
	}

	/**
	 * Runs the workflow to detect unusual rollbacks and report them.
	 *
	 * @return array
	 */
	public function run()
	{
		$startedAt = date('c');
		$autoreviewers = $this->getAutoreviewers();
		$rollbacks = $this->getRecentRollbacks();
		$notify = $this->getCasesToNotify($rollbacks, $autoreviewers);

		$doneList = explode("\n", $this->get($this->donePage));
		$report = [
			'started_at' => $startedAt,
			'report_page' => $this->reportPage,
			'done_page' => $this->donePage,
			'autoreviewers_count' => count($autoreviewers),
			'rollbacks_in_window' => count($rollbacks),
			'candidates_found' => count($notify),
			'already_reported_skipped' => 0,
			'new_cases_processed' => 0,
			'processed_case_ids' => [],
			'telegram_ok' => 0,
			'telegram_failed' => 0,
			'telegram_responses' => [],
		];

		foreach ($notify as $case) {
			if (in_array($case['id'], $doneList, true)) {
				$report['already_reported_skipped']++;
				continue;
			}

			$reportContent = $this->get($this->reportPage);
			$reportContent .= "\n#{{dif|" . $case['id'] . "}}: Usuário '" . $case['target'] . "' revertido por '" . $case['user'] . "' na página [[:" . $case['title'] . ']].';

			$this->edit($reportContent, null, false, 'bot: Inserindo reversão não-usual', $this->reportPage);

			$doneContent = $this->get($this->donePage);
			$this->edit($doneContent . "\n" . $case['id'], null, false, 'bot: Lançando ID de reversão', $this->donePage);
			$report['new_cases_processed']++;
			$report['processed_case_ids'][] = $case['id'];

			$telegramResult = $this->sendTelegram($case);
			if ($telegramResult === false) {
				$report['telegram_failed']++;
			} else {
				$report['telegram_ok']++;
			}
			$report['telegram_responses'][] = [
				'case_id' => $case['id'],
				'response' => $telegramResult,
			];
		}

		$report['finished_at'] = date('c');

		if ($report['new_cases_processed'] === 0) {
			$report['message'] = 'Nenhum caso novo para registrar nesta execução.';
		} else {
			$report['message'] = 'Execução concluída com novos casos registrados.';
		}

		return $report;
	}
}

// Run script
$api = new BlockReverts('https://pt.wikipedia.org/w/api.php', $usernameBQ, $passwordBQ);
print_r($api->run());