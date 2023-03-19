<pre><?php
require_once './bin/globals.php';
require_once './bin/api2.php';
date_default_timezone_set('UTC');

class ProtectPages {

    /**
     * Construtor da classe ProtectPages. Inicializa a API com as credenciais fornecidas.
     * @param string $apiUrl O URL da API do MediaWiki.
     * @param string $usernameBQ O nome de usuário para autenticação na API.
     * @param string $passwordBQ A senha para autenticação na API.
     */
    public function __construct($apiUrl, $usernameBQ, $passwordBQ) {
        $this->api = new WikiAphpi($apiUrl, $usernameBQ, $passwordBQ);
    }

    /**
     * Verifica se uma seção possui uma marcação de "<!--{{Respondido", indicando que ainda não foi respondida
     * @param string $text O texto da seção a ser verificada
     * @return bool Retorna true se a seção não possuir a marcação "<!--{{Respondido", caso contrário retorna false
     */
    private function isSectionAnswered($text) {
        preg_match_all(
            "/<!--\n?{{Respondido/", 
            $text, 
            $regex
        );
        if (!isset($regex['0']['0'])) {
            return true;
        }
        return false;
    }

    /**
     * Converte o texto de descrição de um evento de proteção para um formato wikificado.
     * @param string $text O texto de descrição do evento de proteção a ser convertido.
     * @return string O texto de descrição wikificado.
    */
    private function descriptionToWikitext($text) {
        $sub1 = array(
            "[", 
            "edit=", 
            "move=", 
            "create=", 
            "autoconfirmed", 
            "extendedconfirmed", 
            "editautoreviewprotected", 
            "sysop"
        );
        $sub2 = array(
            "\n:*[", 
            "Editar: ", 
            "Mover: ", 
            "Criar: ", 
            "[[Ficheiro:Wikipedia_Autoconfirmed.svg|20px]] [[Wikipédia:Autoconfirmados|Autoconfirmado]]", 
            "[[Ficheiro:Usuario_Autoverificado.svg|20px]] [[Wikipédia:Autoconfirmados estendidos|Autoconfirmados estendidos]]", 
            "[[Ficheiro:Wikipedia_Autopatrolled.svg|20px]] [[Wikipédia:Autorrevisores|Autorrevisor]]", 
            "[[Ficheiro:Wikipedia_Administrator.svg|20px]] [[Wikipédia:Administradores|Administrador]]"
        );
        return str_replace($sub1, $sub2, $text);
    }


    /**
     * Obtém informações do evento de proteção mais recente de uma página.
     * @param string $page O título da página.
     * @return mixed Retorna um array associativa com as informações de registro ou falso se não houver nenhum evento de proteção na página.
    */
    private function getProtectionEventInfo($page) {
        $info_params = [
            'action'    => 'query',
            'format'    => 'php',
            'list'      => 'logevents',
            'letype'    => 'protect',
            'lelimit'   => '1',
            'letitle'   => $page
        ];

        $queryResult = $this->api->see($info_params);
        return $queryResult['query']['logevents']['0'] ?? false;
    }

    /**
     * Obtém informações de proteção de uma página, com base no código-fonte da requisição.
     * @param string $text O código-fonte da página.
     * @return array|false Um array associativo com as seguintes informações sobre a proteção da página:
     *  'timestamp': O timestamp Unix da proteção.
     *  'description': Uma descrição wikificada da proteção.
     *  'user': O usuário que protegeu a página.
     *  'logid': O ID do registro da proteção.
     *  'time': A data e hora da proteção no formato '00h00min de 0 de mês de 0000'.
     * Retorna false caso não encontre informações de proteção.
     * @throws Exception Caso não seja possível identificar o nome da página.
    */
    private function getProtectionInfo($text) {
        $lines = explode("\n", $text);

        $page = trim($lines['0'], "= ") ?? false;
        if ($page === false) {
            throw new Exception("Nome da página não encontrado!");
        }

        $info = $this->getProtectionEventInfo($page);
        if (!$info) {
            return false;
        }

        return [
            'timestamp' => strtotime($info['timestamp']),
            'description' => $this->descriptionToWikitext($info['params']['description']),
            'user' => $info['user'],
            'logid' => $info['logid'],
            'time' => utf8_encode(
                strftime(
                    "%Hh%Mmin de %d de %B de %Y", 
                    strtotime($info['timestamp'])
                )
            )
        ];
    }


    /**
     * Converte o nome de um mês para o seu número correspondente.
     * @param string $m O nome do mês a ser convertido.
     * @return string O número correspondente do mês (com dois dígitos).
     * @throws Exception Se o nome do mês não for reconhecido.
     */
    private function convertMonthNameToNumber($m) {
        $months = [
            'janeiro'   => '01',
            'fevereiro' => '02',
            'março'     => '03',
            'abril'     => '04',
            'maio'      => '05',
            'junho'     => '06',
            'julho'     => '07',
            'agosto'    => '08',
            'setembro'  => '09',
            'outubro'   => '10',
            'novembro'  => '11',
            'dezembro'  => '12'
        ];
        if (!isset($months[$m])) {
            throw new Exception("$m não é um mês válido!");
        }
        return $months[$m];
    }

    /**
     * Extrai a data e a hora da assinatura do solicitante e retorna um timestamp Unix.
     * A função procura pela marcação de tempo no formato "dd de mês de yyyy (UTC)". 
     * Em seguida, converte a data e hora para um timestamp Unix e retorna o valor.
     * @param string $code O código da seção que contém a assinatura.
     * @return int Retorna um timestamp Unix representando a data e hora da assinatura.
     */
    private function requestTimestamp($code) {
        preg_match_all(
            '/(\d{1,2})h(\d{1,2})min de (\d{1,2}) de ([^ ]*) de (\d{1,4}) \(UTC\)/',
            $code,
            $timestamp
        );

        $y = $timestamp['5']['0'];
        $m = $this->convertMonthNameToNumber($timestamp['4']['0']);
        $d = $timestamp['3']['0'];
        $h = $timestamp['2']['0'];
        $i = $timestamp['1']['0'];

        return strtotime("{$y}-{$m}-{$d}T{$h}:{$i}:00Z");
    }

    /**
     * Substitui a parte inicial da seção indicando que o pedido de proteção foi cumprido.
     * @param string $text Texto da seção
     * @return string Texto modificado
     */
    private function replaceInitialSection($text) {
        return preg_replace(
            '/<!--\n?{{Respondido[^>]*>/', 
            '{{Respondido2|feito|texto=', 
            $text
        );
    }

    /**
     * Substitui a parte final da seção indicando que o pedido de proteção foi cumprido.
     * @param string $text O conteúdo da seção a ser processada.
     * @param array $protectLog Array contendo o registro da proteção
     * @return string Texto modificado
     */
    private function replaceFinalSection($text, $protectLog) {
        $logLink = "[[Special:Redirect/logid/{$protectLog['logid']}|{$protectLog['time']} (UTC)]]";
        $userLink = "[[User:{$protectLog['user']}|{$protectLog['user']}]]";
        $params = $protectLog['description'];
        $botLink = ":--[[User:BloqBot|BloqBot]] <small>~~~~~</small>}}";

        $newText = preg_replace(
            '/<!--:{{proteção[^>]*>/', 
            ":{{subst:feito|Feito}}. Proteção realizada em {$logLink} por {$userLink} com o(s) seguinte(s) parâmetro(s): {$params}\n{$botLink}", 
            $text
        );

        return $newText;
    }

    /**
     * Processa a seção para determinar se ela precisa ser fechada.
     * Se a página referente for encontrada com um registro de proteção e a solicitação de proteção foi feita antes
     * da proteção atual, a seção será marcada como respondida e fechada.
     * @param string $text O conteúdo da seção a ser processada.
     * @param int $section O número da seção a ser processada.
     * @param string $page O nome da página wiki.
     */
    private function processSection($text, $section, $page) {
        $protectLog = $this->getProtectionInfo($text);

        if ($protectLog && $this->requestTimestamp($text) > $protectLog['timestamp']) {
            $text = $this->replaceInitialSection($text);
            $text = $this->replaceFinalSection($text, $protectLog);

            $this->api->edit($text, $section, true, "bot: Fechando pedido cumprido", $page);
        }
    }

    /**
     * Executa o bot para fechar todas as seções de uma página que ainda não foram marcadas como respondidas.
     * Para cada seção da página fornecida, verifica se ela ainda está aberta e, se estiver, processa a seção para
     * determinar se ela deve ser fechada. As seções são fechadas se tiverem um registro de proteção e a proteção
     * foi realizada após a solicitação atual.
     * @param string $page O nome da página de pedidos a ser processada.
     */
    public function run($page) {
        $sections = $this->api->getSections($page);
        unset($sections[array_key_first($sections)]);
        foreach ($sections as $section => $text) {
        	echo "\nProcessando seção ".$section;
            if (!$this->isSectionAnswered($text)) {
            	echo " ainda aberta";
                $this->processSection($text, $section, $page);
            }
        }
    }
}

//Executa script
$api = new ProtectPages('https://pt.wikipedia.org/w/api.php', $usernameBQ, $passwordBQ);
$api->run('Wikipédia:Pedidos/Proteção');