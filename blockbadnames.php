<?php
require_once './bin/globals.php';
require_once './bin/api2.php';

/**
 *
 */
class BadNames {

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
     * Remove marcação de categoria de usuários notificados
     * @param sting $userTalk Página de discussão do usuário
     * @return int Revision number
     */
    private function removeImproperNameCategory($userTalk) {
        $html = preg_replace(
            '/{{#ifeq:[^\|]*\|{{PAGENAME}}\|{{#ifexpr:.*\]\]}}}}/',
            '',
            $this->api->get($userTalk)
        );
        return $this->api->edit(
            $html,
            null,
            true,
            "bot: Removendo categoria de nome impróprio",
            $userTalk
        );
    }

    /**
     * Recupera usuários listados na categoria de monitoramento
     * @param sting $categoryTitle Nome da categoria
     * @return array Lista de páginas de discussão destes usuários
     */
    private function getCategorizedUserTalkPages($categoryTitle) {
        $params = [
            "action"  => "query",
            "format"  => "php",
            "list"    => "categorymembers",
            "cmtitle" => $categoryTitle,
            "cmprop"  => "title|timestamp",
            "cmsort"  => "timestamp",
            "cmlimit" => "max"
        ];
        return $this->api->see($params)["query"]["categorymembers"];
    }

    /**
     * Recupera nome de usuário pela sua página de discussão
     * @param string $userTalk Página de discussão do usuário
     * @return string Nome do usuário
     */
    private function getUserFromUserTalk($userTalk) {
        return preg_replace('/.*?:/', '', $userTalk);
    }

    /**
     * Verifica se há bloqueios ativos para o usuário informado
     * @param string $userTalk Página de discussão do usuário
     * @return bool True if blocked, false if is not
     */
    private function isUserBlocked($userTalk) {
        $params = [
            "action"  => "query",
            "format"  => "php",
            "list"    => "blocks",
            "bkusers" => $this->getUserFromUserTalk($userTalk)
        ];

        //Executa API
        $api = $this->api->see($params);

        //Coleta subarray com bloqueios
        $info = $api['query']['blocks'] ?? false;
        if ($info === false) {
            throw new Exception(print_r($api, true));
        }

        //Verifica se há bloqueio ativo
        if (isset($info['0'])) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * Coleta lista de afluentes da página de discussão do usuário
     * @param string $userTalk Página de discussão do usuário
     * @return array Lista de páginas afluentes
     */
    private function getLinkshere($userTalk) {
        $params = [
            "action"  => "query",
            "format"  => "php",
            "prop"    => "linkshere",
            "titles"  => $userTalk
        ];
        $result = end($this->api->see($params)["query"]["pages"]);
        return $result["linkshere"] ?? array();
    }

    /**
     * Executa edição nula em página para que as categorias sejam reiniciadas
     * @param string $userTalk Página de discussão do usuário
     * @return int Revision number
     */
    private function nullEdit($userTalk) {
        return $this->api->edit(
            "\n\n",
            "append",
            true,
            "bot: Null edit",
            $userTalk
        );
    }

    /**
     * Verifica se usuário possui pedido pendente de renomeação ou bloqueio
     * @param string $userTalk Página de discussão do usuário
     * @return bool Verdadeiro caso exista pedido, falso caso contrário
     */
    private function isPendingRequest($userTalk) {
        $linkshere = $this->getLinkshere($userTalk);
        if (array_search("6286011", array_column($linkshere, 'pageid')) !== false) {
            return true;
        }
        if (array_search("2077627", array_column($linkshere, 'pageid')) !== false) {
            return true;
        }
        return false;
    }

    /**
     * Verifica se usuário notificado ainda não está bloqueado.
     * Caso não esteja, faz um null edit para recarregar categorias.
     * Caso contrário, remove categoria de monitoramento
     * @param string $userTalk Página de discussão do usuário
     * @param int $timestamp Horário de categorização
     */
    private function checkAndRemoveCategory($userTalk, $timestamp) {
        if ($this->isUserBlocked($userTalk)) {
            $this->removeImproperNameCategory($userTalk);
        } else {
            $renameDeadline = date("U", strtotime($timestamp)) + 432000;
            if ($renameDeadline < time()) {
                $this->nullEdit($userTalk);
            }
        }
    }

    /**
     * Verifica se usuário pendente ainda não está bloqueado
     * Caso esteja, remove categorias de monitoramento
     * Caso contrário, verifica se há pedidos em espera
     * Se não houver, retorna código para notificar
     * @param type $userTalk Página de discussão do usuário
     * @return type
     */
    private function checkAndPrepareRequest($userTalk) {
        if ($this->isUserBlocked($userTalk)) {
            $this->removeImproperNameCategory($userTalk);
        } else {
            if ($this->isPendingRequest($userTalk)) {
                return '';
            } else {
                $user = $this->getUserFromUserTalk($userTalk);
                return "\n\n{{subst:Nome de usuário impróprio/BloqBot|${user}}}";
            }
        }
    }

    /**
     * Processa usuários notificados
     */
    private function processNotifiedUsers() {
        $cat = "Categoria:!Usuários_com_nomes_impróprios_notificados";
        $notified = $this->getCategorizedUserTalkPages($cat);
        foreach ($notified as $user) {
            $this->checkAndRemoveCategory($user["title"], $user["timestamp"]);
        }
    }

    /**
     * Processa usuários passíveis de bloqueio
     */
    private function processPendingUsers() {
        $cat = "Categoria:!Usuários_com_nomes_impróprios_passíveis_de_bloqueio";
        $pending = $this->getCategorizedUserTalkPages($cat);

        $requests = '';
        foreach ($pending as $user) {
        	echo "Processando usuário ".$user;
            $requests .= $this->checkAndPrepareRequest($user["title"], $user["timestamp"]);
        }
        $this->api->edit(
            $requests,
            "append",
            false,
            "bot: Inserindo pedido(s) de usuário(s) notificado(s) há 5 dias",
            "Wikipédia:Pedidos/Revisão de nomes de usuário"
        );
    }

    /**
     * Processa usuários
     */
    public function processUsers() {
        $this->processNotifiedUsers();
        $this->processPendingUsers();
    }

}


$badNames = new BadNames('https://pt.wikipedia.org/w/api.php', $usernameBQ, $passwordBQ);
$badNames->processUsers();
