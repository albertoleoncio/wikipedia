<?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';

/**
 * Class BlockingDiscussion
 * This class represents a Wikipedia discussion about blocking a user.
 */
class BlockingDiscussion extends WikiAphpiUnlogged
{

    /**
     * Retrieve the category data for a given account name.
     *
     * @param string $conta The account name to retrieve the category data for.
     *
     * @return array The category data.
     */
    private function getCategory($conta)
    {
        $params = [
            "action"    => "query",
            "format"    => "php",
            "prop"      => "categories",
            "titles"    => "Wikipédia:Pedidos a administradores/Discussão de bloqueio/$conta"
        ];
        $data = $this->see($params);
        return $data['query']["pages"];
    }

    /**
     * Retrieve the count of prefix search data for a given account name.
     *
     * @param string $conta The account name to retrieve the prefix search data count for.
     *
     * @return string The count of prefix search data plus one.
     */
    private function getCount($conta)
    {
        $params = [
          "action"   => "query",
          "list"     => "prefixsearch",
          "pslimit"  => "max",
          "pssearch" => "Wikipédia:Pedidos a administradores/Discussão de bloqueio/$conta/",
          "format"   => "php"
        ];
        $data = $this->see($params);
        $count = count($data['query']['prefixsearch']) + 1;
        return "/$count";
    }

    /**
     * Generate an array to create a new database page for a given account name, defense statement, evidence,
     * diff of the defense statement and subpage (optional).
     *
     * @param string $conta The account name to generate the new discussion page for.
     * @param string $defesa The defense statement for the new discussion page.
     * @param string $evidence The evidence for the new discussion page.
     * @param string $diff The diff of the defense statement
     * @param string $subpage The optional subpage to generate the new discussion page for.
     *
     * @return array The array to create the new discussion page.
     */
    private function doNewDB($conta, $defesa, $evidence, $diff, $subpage = '')
    {
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

    /**
     * Generates a link to add a discussion page to the list of pending discussions on Wikipedia.
     *
     * @param string $conta The name of the account to add to the list.
     * @param string $subpage The subpage for the discussion (optional).
     *
     * @return array The generated link as an array.
     */
    private function doAddList($conta, $subpage = '')
    {
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

    /**
     * Generates the code to update the "MRConduta" template on Wikipedia with a new discussion.
     *
     * @param string $conta The name of the account being discussed.
     * @param string $subpage The subpage for the discussion (optional).
     *
     * @return array The generated code as an array.
     */
    private function doGenerateMRConduta($conta, $subpage = '')
    {
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

    /**
     * Generates an array with parameters for a link to edit the "Template:MRConduta" page and replace the existing code with the new code.
     *
     * @return array An array with parameters for a link to edit the "Template:MRConduta" page.
     */
    private function doPasteMRConduta()
    {
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

    /**
     * Generates an array with parameters for a link to move the previous discussion page to a subpage.
     *
     * @param string $conta The username of the blocked user.
     * @return array An array with parameters for a link to move the previous discussion page to a subpage.
     */
    private function doMoveOldDB($conta)
    {
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

    /**
     * Generates an array with parameters for a text area containing code to create a new disambiguation page for the blocked user.
     *
     * @param string $conta The username of the blocked user.
     * @return array An array with parameters for a text area containing code to create a new disambiguation page for the blocked user.
     */
    private function doGenerateDesambig($conta)
    {
        $array = [
            'type'      => 'textarea',
            'id'        => 'newdesambig',
            'button'    => 'Copiar código de desambiguação',
            'text'      => "{{!Desambiguação}}\n\n*[[/1|1.º pedido]]\n*[[/2|2.º pedido]]\n\n[[Categoria:!Desambiguações de $conta]]\n[[Categoria:!Desambiguações de pedidos de discussão de bloqueio|$conta]]"
        ];
        return $array;
    }

    /**
     * Generates an array with parameters for a link to edit the disambiguation page and replace the existing code with the new code.
     *
     * @param string $conta The username of the blocked user.
     * @return array An array with parameters for a link to edit the disambiguation page.
     */
    private function doPasteDesambig($conta)
    {
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

    /**
     * Generates an array with the link and params to request/send a mass message for an user's unblocking.
     *
     * @param string $conta The name of the blocked user.
     * @param bool $sysop A boolean indicating if the current user is a sysop or not.
     * @return array An array with the link and params to request/send a mass message.
     */
    private function doRequestMassMessage($conta, $sysop)
    {
        if ($sysop) {
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

    /**
     * Generates a link for notifying a user about their discussion block.
     *
     * @param string $conta The username of the user to notify.
     * @return array An array containing information about the link.
     */
    private function doNotifyUser($conta)
    {
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

    /**
     * Check if a discussion page is a disambiguation page
     *
     * @param array $result The API result from querying the discussion page
     * @return bool True if the discussion page is a disambiguation page, false otherwise
     */
    private function isDesambig($result)
    {
        $categories = end($result)['categories'];
        foreach ($categories as $cat) {
            if ($cat['title'] === 'Categoria:!Desambiguações de pedidos de discussão de bloqueio') {
                return true;
            }
        }
        return false;
    }

    /**
     * Runs the script for a given user account, generating and returning the required actions to be taken as an array of strings.
     *
     * @param string $conta The name of the user account to generate the actions for.
     * @param bool $sysop Indicates if the user performing the action is a sysop or not.
     * @param string $evidence The evidence string to be used in the new discussion page.
     * @param string $defesa The defense string to be used in the new discussion page.
     * @param string $diff The diff string to be used in the new discussion page.
     * @return array The array containing the required actions as strings.
    */
    public function run($conta, $sysop, $evidence, $defesa, $diff)
    {

        $result = $this->getCategory($conta);
        $isFirst = isset($result['-1']);
        $isDesambig = !$isFirst && $this->isDesambig($result);

        $index = $isFirst ? '' : ($isDesambig ? $this->getCount($conta) : '/2');

        $echo = [];
        $echo[] = $this->doNewDB($conta, $defesa, $evidence, $diff, $index);
        $echo[] = $this->doAddList($conta, $index);
        $echo[] = $this->doGenerateMRConduta($conta, $index);
        $echo[] = $this->doPasteMRConduta();
        if (!$isFirst && !$isDesambig) {
            $echo[] = $this->doMoveOldDB($conta);
            $echo[] = $this->doGenerateDesambig($conta);
            $echo[] = $this->doPasteDesambig($conta);
        }
        $echo[] = $this->doRequestMassMessage($conta, $sysop);
        $echo[] = $this->doNotifyUser($conta);

        return $echo;
    }
}


/**
 * This code block instantiates the necessary classes and runs the `run()` method
 * of the `BlockingDiscussion` class with the provided parameters.
 *
 * If the `conta` parameter is not set, the code block returns `false`.
 */
$conta =    filter_input(INPUT_POST, 'conta',    FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$sysop =    filter_input(INPUT_POST, 'sysop',    FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$evidence = filter_input(INPUT_POST, 'evidence', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$defesa =   filter_input(INPUT_POST, 'defesa',   FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$diff =     filter_input(INPUT_POST, 'diff',     FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if ($conta) {
    $discussion = new BlockingDiscussion('https://pt.wikipedia.org/w/api.php');
    $echo = $discussion->run($conta, $sysop, $evidence, $defesa, $diff);
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
                        <form method="post">
                            <div class="w3-container w3-padding-48 w3-card">
                                <p class="w3-center w3-wide">NOME DO USUÁRIO</p>
                                <p class="w3-text-grey">
                                    <input class="w3-input w3-padding-16 w3-border"
                                    value='<?=$conta??''?>'
                                    type="text" name="conta" placeholder="Usuário">
                                </p>
                                <br>
                                <p class="w3-center w3-wide">VOCÊ É ADMINISTRADOR?</p>
                                <p>
                                    <input name="sysop" class="w3-check" type="checkbox"
                                    <?=(@$sysop)?"checked":''?>>
                                    <label>Sim</label>
                                </p>
                                <br>
                                <p class="w3-center w3-wide">EVIDÊNCIAS:</p>
                                <p>
                                    <textarea class="w3-input w3-padding-16 w3-border"
                                    id="evidence" name="evidence" rows="4" cols="50"
                                    placeholder="Insira aqui as evidências para a solicitação do bloqueio. Utilize [[wikicode]] e não esqueça de assinar com ~~~~."><?=$evidence??''?></textarea>
                                </p>
                                <br>
                                <p class="w3-center w3-wide">DEFESA:</p>
                                <p>
                                    <textarea class="w3-input w3-padding-16 w3-border" id="defesa" name="defesa" rows="4" cols="50" placeholder="Insira aqui a defesa, caso fornecida, acompanhada do diff abaixo. Caso contrário, deixe ambos os campos em branco."><?=$defesa??''?></textarea>
                                </p>
                                <br>
                                <p class="w3-center w3-wide">DIFF DA DEFESA:</p>
                                <p class="w3-text-grey">
                                    <input class="w3-input w3-padding-16 w3-border"
                                    type="text" name="diff" placeholder="67890123"
                                    value="<?=$diff??''?>">
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
                                    <h3 class='w3-center'><b><?=$conta??''?></b></h3>
                                    <small>
                                        <b>Clique em cada link abaixo na ordem apresentada.</b>
                                        Ao clicar, uma nova janela será aberta para a edição da página. Em seguida, clique em "Publicar alterações".
                                        <br>
                                        Esta ferramenta está sujeita a erros, então não esqueça de verificar se as edições foram feitas corretamente.
                                    </small>
                                    <br>
                                    <br>
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
        <?php include_once('footer.html'); ?>
    </body>
</html>