<pre><?php
require_once './bin/globals.php';
require_once './bin/api2.php';

/**
 *
 */
class BadRollback {

    /**
     * Construtor da classe com criação do objeto 'WikiAphpi'
     * @param string $apiUrl
     * @param string $usernameBQ
     * @param string $passwordBQ
     */
    public function __construct($apiUrl, $usernameBQ, $passwordBQ) {
        $this->api = new WikiAphpi($apiUrl, $usernameBQ, $passwordBQ);
    }

    /**
     * Levanta lista de reversores
     * @return array Lista de IDs de usuários reversores
     */
    private function getRollbackers() {
        $params = [
          'action'  => 'query',
          'format'  => 'php',
          'list'    => 'allusers',
          'augroup' => 'rollbacker',
          'aulimit' => 'max'
        ];
        $rollbackers_API = $this->api->see($params)['query']['allusers'];
        $rollbackers_IDs = array();
        foreach ($rollbackers_API as $user) {
            $rollbackers_IDs[] = $user['userid'];
        }
        
        return $rollbackers_IDs;
    }

    /**
     * Levanta lista de bloqueios ocorridos nos últimos minutos
     * @return array Lista de eventos e seus detalhes
     */
    public function getRecentBlocks() {
        $params = [
            'action'    => 'query',
            'format'    => 'php',
            'list'      => 'logevents',
            'leprop'    => 'userid|details|ids|title|user|type|timestamp',
            'letype'    => 'block',
            'lelimit'   => 'max',
            'ledir'     => 'older',
            'leend'     => gmdate('Y-m-d\TH:i:s\Z', strtotime('-180 minutes'))
        ];
        $result = $this->api->see($params)['query']['logevents'];
        return $result;
    }

    /**
     * Verifica se usuário é autoconfirmado
     * @param string $username Nome do usuário
     * @return bool Verdadeiro se for autoconfirmado, falso caso contrário ou caso seja IP
     */
    private function isUserAutoConfirmed($username) {
        $params = [
            'action'    => 'query',
            'format'    => 'php',
            'list'      => 'users',
            'usprop'    => 'rights',
            'ususers'   => $username
        ];
        $result = $this->api->see($params)['query']['users'][0]['rights'] ?? [false];
        return in_array('editsemiprotected', $result);
    }

    /**
     * Recupera nome do usuário de acordo com o nome de sua página de usuário
     * @param string $pagename Página do usuário
     * @return string Nome do usuário
     */
    private function getNameFromUserPage($pagename) {
        $name = explode(':', $pagename, 2);
        return $name["1"];
    }

    /**
     * Verifica parâmetros do log de bloqueio
     * @param array $log Parâmetros de log
     * @return mixed String com erro detectado ou falso caso contrário
     */
    private function verifyLog($log) {

        //Verifica se alvo é autoconfirmado
        $target = $this->getNameFromUserPage($log['title']);
        if ($this->isUserAutoConfirmed($target)) {
            return "autoconfirmado";
        }

        //Ignora desbloqueios de não-autoconfirmados
        if ($log["action"] == "unblock") {
            return false;
        }

        //Verifica se bloqueio foi infinito
        if (!isset($log['params']['expiry'])) {
            return "infinito";
        }

        //Verifica se bloqueio é superior a 24 horas
        $lenght = strtotime($log['params']['expiry']) - strtotime($log['timestamp']);
        if ($lenght > 86401) {
            return ($lenght/3600) . ' horas';
        }

        //Fallback
        return false;
    }

    /**
     * Recupera lista de incidentes já notificados
     * @return array IDs de log
     */
    private function getNotified() {
        $list = $this->api->get('User:BloqBot/rev');
        $list = explode("\n", $list);
        return $list;
    }

    /**
     * Compila array com incidentes a serem notificados
     * @param array $logs Lista de arrays dos parâmetros de cada bloqueio
     * @param array $rollbackersIDs Lista de IDs dos usuários reversores
     * @param array $notified Lista de IDs dos incidentes já lançados
     * @return array Incidentes a serem lançados
     */
    private function compileNotifications($logs, $rollbackersIDs, $notified) {
        $notify = [];

        //Processa cada registro de bloqueio
        foreach ($logs as $log) {

        	// Exibe mensagem de processamento do log
        	echo "Processando log {$log["logid"]}\n";

            //Verifica se incidente já foi lançado
            if (in_array($log['logid'], $notified)) {
                continue;
            }

            //Verifica se autor não é reversor
            if (!in_array($log['userid'], $rollbackersIDs)) {
                continue;
            }
            
            //Verifica parâmetros do registro
            $verify = $this->verifyLog($log);

            //Insere registro na array
            if ($verify !== false) {
            	$user = $log['user'];
            	$target = $this->getNameFromUserPage($log['title']);
            	$incident = "\n{{subst:Incidente/Bloqbot|$user|$verify|$target|{$log['logid']}}}\n";
            	$notify[$user] = $incident;
            }
        }

        return $notify;
    }

    /**
	 * Salva os incidentes na página de notificação de incidentes.
	 * @param array $notifications Array contendo as notificações dos incidentes.
	 */
	private function saveIncidents($notifications) {
	    $notifications = implode('', $notifications);
	    $this->api->edit(
	        $notifications,
	        'append',
	        false,
	        "bot: Inserindo notificação de incidente envolvendo reversor",
	        "Wikipédia:Pedidos/Notificação de incidentes"
	    );
	}

	/**
	 * Salva os números dos logs dos incidentes.
	 * @param array $notifications Array contendo as notificações dos incidentes.
	 */
	private function saveIncidentLogs($notifications) {
	    $logs = implode("\n", array_keys($notifications));
	    $this->api->edit(
	        "\n$logs",
	        'append',
	        true,
	        "bot: Lançando ID de incidente",
	        "Usuário(a):BloqBot/rev"
	    );
	}

	/**
	 * Executa o processo de compilação de notificações e salva no log e na página de incidentes.
	 * Este método é responsável por compilar notificações de incidentes envolvendo reversores na Wikipédia e salvar
	 * esses incidentes em duas páginas: a página de incidentes e a página de log para evitar duplicidade. 
	 * Para isso, a função chama as funções auxiliares `compileNotifications()`, `saveIncidentLogs()` e `saveIncidents()`.
	 * @return void
	 */
	public function run() {
	    $notifications = $this->compileNotifications(
	        $this->getRecentBlocks(),
	        $this->getRollbackers(),
	        $this->getNotified()
	    );

	    $this->saveIncidentLogs($notifications);
	    $this->saveIncidents($notifications);
	}
}

//Executa script
$badRollback = new BadRollback('https://pt.wikipedia.org/w/api.php', $usernameBQ, $passwordBQ);
$badRollback->run();