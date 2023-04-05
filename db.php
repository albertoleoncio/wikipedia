<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
class WikipediaDiscussion {
    private $conta;
    private $sysop;
    private $evidence;
    private $defesa;
    private $diff;

    public function __construct($conta, $sysop, $evidence, $defesa, $diff) {
        $this->conta = $conta;
        $this->sysop = $sysop;
        $this->evidence = $evidence;
        $this->defesa = $defesa;
        $this->diff = $diff;
    }

    private function getCurl($params) {
        $ch = curl_init("https://pt.wikipedia.org/w/api.php?" . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        $api = unserialize($output)["query"] ?? false;
        if ($api === false) {
            throw new Exception(print_r($output, true));
        }
        return $api;
    }

    private function getCategory() {
        $conta  = $this->conta;
        $params = [
            "action"    => "query",
            "format"    => "php",
            "prop"      => "categories",
            "titles"    => "Wikipédia:Pedidos a administradores/Discussão de bloqueio/$conta"
        ];
        return $this->getCurl($params)["pages"];
    }

    private function getCount() {
        $conta  = $this->conta;
        $params = [
          "action"   => "query",
          "list"     => "prefixsearch",
          "pslimit"  => "max",
          "pssearch" => "Wikipédia:Pedidos a administradores/Discussão de bloqueio/$conta/",
          "format"   => "php"
        ];
        return '/'.count($this->getCurl($params)["prefixsearch"]) + 1;
    }

    private function doNewDB($subpage = '') {
        $defesa     = $this->defesa;
        $conta      = $this->conta;
        $evidence   = $this->evidence;
        $diff       = $this->diff;
        $array      = [
            'type' => 'link',
            'text' => "Criar DB{$subpage}",
            'params' => [
                'action'    => 'edit',
                'title'     => "Wikipedia:Pedidos a administradores/Discussão de bloqueio/{$conta}{$subpage}",
                'preload'   => 'Template:DB1/Preload',
                'preloadparams' => [
                    $evidence,
                    "{{subst:#if:$defesa|{{citação2|1=$defesa|2={{subst:#if:$diff|{{dif|$diff}}}}}}}}"
                ]
            ]
        ];
        return $array;
    }

    private function doAddList($subpage = '') {
        $conta = $this->conta;
        $array = [
            'type' => 'link',
            'text' => 'Publicar DB na lista de pedidos',
            'params' => [
                'action'    => 'edit',
                'title'     => 'Wikipédia:Pedidos a administradores/Discussão de bloqueio/Lista de pedidos',
                'preload'   => 'Wikipédia:Pedidos a administradores/Discussão de bloqueio/Lista de pedidos/Preload',
                'section'   => 'new',
                'nosummary' => 1,
                'preloadparams' => [
                    "{$conta}{$subpage}",
                    $conta
                ]
            ]
        ];
        return $array;
    }

    private function doGenerateMRConduta($subpage = '') {
        $conta = $this->conta;
        $text = file_get_contents("https://pt.wikipedia.org/w/index.php?title=Template:MRConduta&action=raw");
        $text = preg_replace(
            '/BloqueioAbertosTotal=(\d)/', 
            'BloqueioAbertosTotal={{subst:#expr:$1+1}}',
            $text
        );
        $text = str_replace(
            "|BloqueioConcluídosTotal", 
            "* [[Wikipédia:Pedidos a administradores/Discussão de bloqueio/{$conta}{$subpage}|$conta]]\n|BloqueioConcluídosTotal", 
            $text
        );
        $array = [
            'type'      => 'textarea',
            'id'        => 'panel',
            'button'    => 'Copiar código do painel',
            'text'      => $text
        ];
        return $array;
    }

    private function doPasteMRConduta() {
        $array = [
            'type' => 'link',
            'text' => 'Colar (substituir) novo código no painel',
            'params' => [
                'action'    => 'edit',
                'title'     => 'Template:MRConduta',
            ]
        ];
        return $array;
    }

    private function doMoveOldDB() {
        $conta = $this->conta;
        $array = [
            'type' => 'link',
            'text' => 'Mover DB anterior para /1',
            'params' => [
                'title'           => "Especial:Mover página/Wikipedia:Pedidos a administradores/Discussão de bloqueio/$conta",
                'wpNewTitle'      => "Wikipédia:Pedidos a administradores/Discussão de bloqueio/$conta/1",
                'wpNewTitleNs'    => '4',
                'wpLeaveRedirect' => '1',
                'wpReason'        => 'Arquivando discussão anterior'
            ]
        ];
        return $array;
    }

    private function doGenerateDesambig() {
        $conta = $this->conta;
        $array = [
            'type'      => 'textarea',
            'id'        => 'newdesambig',
            'button'    => 'Copiar código de desambiguação',
            'text'      => "{{!Desambiguação}}\n\n*[[/1|1.º pedido]]\n*[[/2|2.º pedido]]\n\n[[Categoria:!Desambiguações de $conta]]\n[[Categoria:!Desambiguações de pedidos de discussão de bloqueio|$conta]]"
        ];
        return $array;
    }

    private function doPasteDesambig() {
        $conta = $this->conta;
        $array = [
            'type' => 'link',
            'text' => 'Colar (substituir) novo código para página de desambiguação',
            'params' => [
                'action'    => 'edit',
                'title'     => "Wikipedia:Pedidos a administradores/Discussão de bloqueio/$conta",
            ]
        ];
        return $array;
    }

    private function doRequestMassMessage() {
        $conta = $this->conta;
        if ($this->sysop) {
            $array = [
                'type'      => 'link',
                'text'      => 'Enviar mensagens em massa',
                'params'    => [
                    'title'     => 'Especial:Mensagens em massa',
                    'spamlist'  => 'Wikipédia:Pedidos/Discussão de bloqueio/Massmessage',
                    'subject'   => "Discussão de bloqueio de $conta",
                    'message'   => "{{subst:Usuário:Teles/MassMessage/Desbloqueio|1=$conta|3=~~~~~}}"
                ]
            ];
        } else {
            $array = [
                'type'      => 'link',
                'text'      => 'Enviar mensagens em massa',
                'params'    => [
                    'action'        => 'edit',
                    'title'         => 'Wikipédia:Pedidos/Outros',
                    'section'       => 'new',
                    'preloadtitle'  => "Mensagens em massa para discussão de bloqueio do usuário $conta",
                    'preload'       => 'Wikipédia:Pedidos/Outros/PreloadMassMessageDB',
                    'preloadparams' => [
                        $conta,
                        'https://pt.wikipedia.org/w/index.php?' . http_build_query([
                            'title'     => 'Especial:Mensagens em massa',
                            'spamlist'  => 'Wikipédia:Pedidos/Discussão de bloqueio/Massmessage',
                            'subject'   => "Discussão de bloqueio de $conta",
                            'message'   => "{{subst:Usuário:Teles/MassMessage/Desbloqueio|1=$conta}}"
                        ])
                    ]
                ]
            ];
        }
        return $array;
    }

    private function doNotifyUser() {
        $conta = $this->conta;
        $array = [
            'type' => 'link',
            'text' => 'Enviar notificação ao usuário',
            'params' => [
                'action'    => 'edit',
                'title'     => "User talk:$conta",
                'section'   => 'new',
                'nosummary' => '1',
                'preload'   => 'Predefinição:Notificação de discussão de bloqueio/Preload'
            ]
        ];
        return $array;
    }

    public function run() {
        $result = $this->getCategory();
        $echo = [];

        if (isset($result['-1'])) {
            $echo[] = $this->doNewDB();
            $echo[] = $this->doAddList();
            $echo[] = $this->doGenerateMRConduta();
            $echo[] = $this->doPasteMRConduta();
            $echo[] = $this->doRequestMassMessage();
            $echo[] = $this->doNotifyUser();
        } else {

            $desambig = false;
            $categories = end($result)['categories'];
            foreach ($categories as $cat) {
                if ($cat['title'] === 'Categoria:!Desambiguações de pedidos de discussão de bloqueio') {
                    $desambig = true;
                    break;
                }
            }

            if (!$desambig) {
                $echo[] = $this->doNewDB('/2');
                $echo[] = $this->doAddList('/2');
                $echo[] = $this->doGenerateMRConduta('/2');
                $echo[] = $this->doPasteMRConduta();
                $echo[] = $this->doMoveOldDB();
                $echo[] = $this->doGenerateDesambig();
                $echo[] = $this->doPasteDesambig();
                $echo[] = $this->doRequestMassMessage();
                $echo[] = $this->doNotifyUser();
            } else {
                $count = $this->getCount();
                $echo[] = $this->doNewDB($count);
                $echo[] = $this->doAddList($count);
                $echo[] = $this->doGenerateMRConduta($count);
                $echo[] = $this->doPasteMRConduta();
                $echo[] = $this->doRequestMassMessage();
                $echo[] = $this->doNotifyUser();
            }
        }

        return $echo;
    }
}

if ($_GET["conta"]) {
    $discussion = new WikipediaDiscussion(
        $_GET["conta"],
        $_GET["sysop"] ?? '',
        $_GET["evidence"] ?? '',
        $_GET["defesa"] ?? '',
        $_GET["diff"] ?? ''
    );
    $echo = $discussion->run();
} else {
    $echo = false;
}
?><!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <title>Assistente de abertura de discussões de bloqueio</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="./tpar/w3.css">
        <script>function copyclip(inputId) {
            var e = document.getElementById(inputId);

            if (e) {
                navigator.clipboard.writeText(e.value)
                .then(() => console.log('Text copied to clipboard'))
                .catch((err) => console.error('Failed to copy text: ', err));
            } else {
                console.log('Element not found: ' + inputId);
            }
        }</script>
    </head>
    <body>
        <div class="w3-container" id="menu">
            <div class="w3-content" style="max-width:800px">
                <h5 class="w3-center w3-padding-48"><span class="w3-tag w3-wide">ASSISTENTE DE ABERTURA DE DISCUSSÕES DE BLOQUEIO</span></h5>
                <div class="w3-row-padding w3-center w3-margin-top">
                    <div class="w3-half">
                        <form method="get">
                            <div class="w3-container w3-padding-48 w3-card">
                                <p class="w3-center w3-wide">NOME DO USUÁRIO</p>
                                <p class="w3-text-grey">
                                    <input class="w3-input w3-padding-16 w3-border" 
                                    value='<?=$_GET["conta"]??''?>' 
                                    type="text" name="conta" placeholder="Usuário">
                                </p>
                                <br>
                                <p class="w3-center w3-wide">VOCÊ É ADMINISTRADOR?</p>
                                <p>
                                    <input name="sysop" class="w3-check" type="checkbox"
                                    <?=(@$_GET["sysop"])?"checked":''?>>
                                    <label>Sim</label>
                                </p>
                                <br>
                                <p class="w3-center w3-wide">EVIDÊNCIAS:</p>
                                <p>
                                    <textarea class="w3-input w3-padding-16 w3-border" 
                                    id="evidence" name="evidence" rows="4" cols="50" 
                                    placeholder="Insira aqui as evidências para a solicitação do bloqueio. Utilize [[wikicode]] e não esqueça de assinar com ~~~~."><?=$_GET["evidence"]??''?></textarea>
                                </p>
                                <br>
                                <p class="w3-center w3-wide">DEFESA:</p>
                                <p>
                                    <textarea class="w3-input w3-padding-16 w3-border" id="defesa" name="defesa" rows="4" cols="50" placeholder="Insira aqui a defesa, caso fornecida, acompanhada do diff abaixo. Caso contrário, deixe ambos os campos em branco."><?=$_GET["defesa"]??''?></textarea>
                                </p>
                                <br>
                                <p class="w3-center w3-wide">DIFF DA DEFESA:</p>
                                <p class="w3-text-grey">
                                    <input class="w3-input w3-padding-16 w3-border" 
                                    type="text" name="diff" placeholder="67890123" 
                                    value="<?=$_GET["diff"]??''?>">
                                </p>
                                <p>
                                    <button class="w3-button w3-block w3-black w3-margin-top" type="submit">Preparar lista de links</button>
                                </p>
                            </div>
                        </form>
                    </div>
                    <div class="w3-half">
                        <div class="w3-container w3-padding-48 w3-card">
                            <ul class='w3-ul w3-hoverable w3-border'>
                                <?php if (!$echo): ?>
                                    <p>Preencha o formulário ao lado</p>
                                <?php else: ?> 
                                    <p class='w3-center w3-wide'>DISCUSSÃO DE BLOQUEIO</p>
                                    <h3 class='w3-center'><b><?=$_GET["conta"]??''?></b></h3>
                                    <small><b>Clique em cada link abaixo na ordem apresentada.</b> Ao clicar, uma nova janela será aberta para a edição da página. Em seguida, clique em "Publicar alterações".<br>Esta ferramenta está sujeita a erros, então não esqueça de verificar se as edições foram feitas corretamente.</small>
                                    <br><br>
                                    <ul class='w3-ul w3-hoverable w3-border'>
                                    <?php foreach ($echo as $line): ?>
                                        <?php if ($line['type'] == 'link'): ?>
                                            <li 
                                            class="w3-padding-small w3-left-align" 
                                            style="cursor: pointer;" 
                                            onclick="window.open(
                                                'https://pt.wikipedia.org/w/index.php?<?=http_build_query($line['params'])?>'
                                            )"><?=$line['text']?></li>
                                        <?php else: ?> 
                                            <li class="w3-padding-small w3-left-align">
                                                <textarea 
                                                readonly rows='1' cols='2' id='<?=$line['id']?>'
                                                style='resize: none; margin-bottom: -8px;' 
                                                ><?=$line['text']?></textarea>
                                                <button 
                                                onclick="copyclip('<?=$line['id']?>')"
                                                ><?=$line['button']?></button>
                                            </li>
                                        <?php endif; ?> 
                                    <?php endforeach; ?> 
                                <?php endif; ?> 
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <hr>
        <a href="https://wikitech.wikimedia.org/wiki/Portal:Toolforge"><img src="https://tools-static.wmflabs.org/toolforge/banners/Powered-by-Toolforge-button.png" alt="Powered by Wikimedia Toolforge"></a>
    </body>
</html>