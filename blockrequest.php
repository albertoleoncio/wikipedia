<pre><?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';
date_default_timezone_set('UTC');

/**
 * Classe responsável pela análise e fechamento de pedidos de bloqueio/proteção na Wikipédia em
 * português.
 */
class BloqBotRequests extends WikiAphpiLogged
{

    /**
     * Verifica se uma seção possui uma marcação de "<!--{{Respondido", indicando que ainda não foi respondida
     * @param string $text O texto da seção a ser verificada
     * @return bool Retorna true se a seção não possuir a marcação "<!--{{Respondido", caso contrário retorna false
     */
    private function isSectionAnswered($text)
    {
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
    private function descriptionToWikitext($text)
    {
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
    private function getProtectionEventInfo($page)
    {
        $info_params = [
            'action'    => 'query',
            'format'    => 'php',
            'list'      => 'logevents',
            'letype'    => 'protect',
            'lelimit'   => '1',
            'letitle'   => $page
        ];

        $queryResult = $this->see($info_params);
        return $queryResult['query']['logevents']['0'] ?? false;
    }

    /**
     * Obtém o título da seção de acordo com seu wikitexto
     * @param string $text O código-fonte da página.
     * @return string O título da seção.
     */
    private function getSectionTitle($text)
    {
        $lines = explode("\n", $text);

        $page = trim($lines['0'], "= ") ?? false;
        if ($page === false) {
            throw new Exception("Nome da página não encontrado!");
        }

        return $page;
    }

    /**
     * Obtém informações de proteção de uma página, com base no código-fonte da requisição.
     * @param string $title O nome da página protegida.
     * @return array|false Um array associativo com as seguintes informações sobre a proteção da página:
     *  'timestamp': O timestamp Unix da proteção.
     *  'description': Uma descrição wikificada da proteção.
     *  'user': O usuário que protegeu a página.
     *  'logid': O ID do registro da proteção.
     *  'time': A data e hora da proteção no formato '00h00min de 0 de mês de 0000'.
     * Retorna false caso não encontre informações de proteção.
     * @throws Exception Caso não seja possível identificar o nome da página.
    */
    private function getProtectionInfo($title)
    {

        $info = $this->getProtectionEventInfo($title);
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
    private function convertMonthNameToNumber($m)
    {
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
    private function requestTimestamp($code)
    {
        preg_match_all(
            '/(\d{1,2})h(\d{1,2})min de (\d{1,2}) de ([^ ]*) de (\d{1,4}) \(UTC\)/',
            $code,
            $timestamp
        );

        $y = sprintf("%04d", $timestamp['5']['0']);
        $m = $this->convertMonthNameToNumber($timestamp['4']['0']);
        $d = sprintf("%02d", $timestamp['3']['0']);
        $h = sprintf("%02d", $timestamp['1']['0']);
        $i = sprintf("%02d", $timestamp['2']['0']);

        $time = strtotime("{$y}-{$m}-{$d}T{$h}:{$i}:00Z");

        if (!$time) {
            throw new UnexpectedValueException("Error converting {$y}-{$m}-{$d}T{$h}:{$i}:00Z");
        }

        return $time;
    }

    /**
     * Verifica se há bloqueios ativos para o usuário informado
     * @param string $user Nome do usuário
     * @return array|false Caso exista bloqueio ativo ou não
     */
    private function isUserBlocked($user)
    {
        $params = [
            "action"  => "query",
            "format"  => "php",
            "list"    => "blocks",
            "bkusers" => $user
        ];

        //Executa API
        $api = $this->see($params);

        //Coleta subarray com bloqueios
        $info = $api['query']['blocks'] ?? false;
        if ($info === false) {
            throw new ContentRetrievalException($api);
        }

        //Retorna informações do bloqueio ativo ou falso caso não exista
        return $info['0'] ?? false;
    }

    /**
     * Substitui a parte inicial da seção indicando que o pedido de proteção foi cumprido.
     * @param string $text Texto da seção
     * @return string Texto modificado
     */
    private function replaceInitialSection($text)
    {
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
    private function replaceFinalProtectSection($text, $protectLog)
    {
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
     * Substitui a parte final da seção com uma predefinição de bloqueio concluído
     * @param string $text O conteúdo da seção a ser processada.
     * @param array $protectLog Array contendo o registro do bloqueio
     * @param string $tempo O prazo do bloqueio aplicado.
     * @return string Texto modificado
     */
    private function replaceFinalBlockSection($text, $blockLog, $tempo)
    {
        $newText = preg_replace(
            '/<!--\n?:{{subst:(Bloqueio )?[Ff]eito[^>]*>/',
            ":{{subst:Bloqueio feito|por=".$blockLog['by']."|".$tempo."}}. [[User:BloqBot|BloqBot]] ~~~~~}}",
            $text
        );

        return $newText;
    }

    /**
     * Calcula o tempo restante para o término do bloqueio, se houver.
     * @param array $blockLog Um registro de bloqueio com informações sobre o bloqueio.
     * @return string Uma string que descreve o tempo restante até o término do bloqueio, ou "tempo indeterminado" se o bloqueio for permanente.
     */
    private function calculateBlockTime($blockLog)
    {
        if ($blockLog['expiry'] == "infinity") {
            return "tempo indeterminado";
        } else {
            $interval = date_diff(date_create($blockLog['timestamp']), date_create($blockLog['expiry']));
            $tempo = "";
            if ($interval->format('%y') != 0) $tempo = $tempo.$interval->format('%y')." ano(s), ";
            if ($interval->format('%m') != 0) $tempo = $tempo.$interval->format('%m')." mese(s), ";
            if ($interval->format('%d') != 0) $tempo = $tempo.$interval->format('%d')." dia(s), ";
            if ($interval->format('%h') != 0) $tempo = $tempo.$interval->format('%h')." hora(s), ";
            if ($interval->format('%i') != 0) $tempo = $tempo.$interval->format('%i')." minuto(s), ";
            if ($interval->format('%s') != 0) $tempo = $tempo.$interval->format('%s')." segundo(s), ";
            $tempo = trim($tempo, ", ");
            return $tempo;
        }
    }

    /**
     * Processa a seção para determinar se ela precisa ser fechada, extraindo o nome da
     * conta a partir do título da seção. Se a conta referente for encontrada com um
     * bloqueio ativo e o prazo de bloqueio for maior que 25 horas,
     * a seção será marcada como respondida e fechada.
     * @param string $text O conteúdo da seção a ser processada.
     * @param int $section O número da seção a ser processada.
     * @param string $page O nome da página de pedidos.
     */
    private function processBlockingSection($text, $section, $page)
    {
        $blockLog = $this->isUserBlocked($this->getSectionTitle($text));
        if ($blockLog !== false) {
            if ($blockLog['expiry'] != "infinity" AND (strtotime($blockLog['expiry']) - strtotime($blockLog['timestamp']) < 90000)){
                return;
            }
            echo " e já finalizada. Fechando...";
            $text = $this->replaceInitialSection($text);
            $text = $this->replaceFinalBlockSection(
                $text,
                $blockLog,
                $this->calculateBlockTime($blockLog)
            );
            echo $this->edit($text, $section, true, "bot: Fechando pedido cumprido", $page);
        }
    }

    /**
     * Processa a seção para determinar se ela precisa ser fechada, extraindo o nome da
     * página a partir do título da seção. Se a página referente for encontrada com um
     * registro de proteção e a solicitação de proteção foi feita antes
     * da proteção atual, a seção será marcada como respondida e fechada.
     * @param string $text O conteúdo da seção a ser processada.
     * @param int $section O número da seção a ser processada.
     * @param string $page O nome da página de pedidos.
     */
    private function processProtectingSection($text, $section, $page)
    {
        $protectLog = $this->getProtectionInfo($this->getSectionTitle($text));
        if ($protectLog && $this->requestTimestamp($text) < $protectLog['timestamp']) {
            echo " e já finalizada. Fechando...";
            $text = $this->replaceInitialSection($text);
            $text = $this->replaceFinalProtectSection($text, $protectLog);
            echo $this->edit($text, $section, true, "bot: Fechando pedido cumprido", $page);
        }
    }

    /**
     * Executa o bot para fechar todas as seções de uma página que ainda não foram marcadas como respondidas.
     * Para cada seção da página fornecida, verifica se ela ainda está aberta e, se estiver, processa a seção para
     * determinar se ela deve ser fechada. As seções são fechadas se o pedido correspondente tiver sido cumprido.
     * @param string $page O nome da página de pedidos a ser processada.
     */
    public function run($page, $type)
    {
        echo "\n\nIniciando página $page";
        $sections = $this->getSections($page);
        unset($sections[array_key_first($sections)]);
        foreach ($sections as $section => $text) {
            echo "\nProcessando seção ".$section;
            if (!$this->isSectionAnswered($text)) {
                echo " ainda aberta";
                if ($type == 'protect') {
                    $this->processProtectingSection($text, $section, $page);
                } else {
                    $this->processBlockingSection($text, $section, $page);
                }
            }
        }
    }
}

//Executa script
$api = new BloqBotRequests('https://pt.wikipedia.org/w/api.php', $usernameBQ, $passwordBQ);
$api->run('Wikipédia:Pedidos/Notificações de vandalismo', 'block');
$api->run('Wikipédia:Pedidos/Revisão de nomes de usuário', 'block');
$api->run('Wikipédia:Pedidos/Proteção', 'protect');