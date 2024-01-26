<?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';

/**
 * Class BlockingDiscussion
 * This class represents a Wikipedia discussion about blocking a user.
 */
class BlockingDiscussion extends WikiAphpiOAuth
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
     * Create a new discussion page for a given account name, defense statement, evidence,
     * diff of the defense statement and subpage (optional).
     *
     * @param string $conta The account name to generate the new discussion page for.
     * @param string $defesa The defense statement for the new discussion page.
     * @param string $evidence The evidence for the new discussion page.
     * @param string $diff The diff of the defense statement
     * @param string $subpage The optional subpage to generate the new discussion page for.
     *
     * @return int The revision number of the edit.
     */
    private function doNewDB($conta, $defesa, $evidence, $diff, $subpage = '')
    {
        $text  = "{{subst:DB2";
        $text .= "|";
        $text .= "1 = $evidence";
        $text .= "|";
        $text .= "2 = {{subst:#if:$defesa|{{citação2|1=$defesa|2={{subst:#if:$diff|{{dif|$diff}}}}}}}}";
        $text .= "|";
        $text .= "3 = {{subst:#if:$evidence|~~~~~}}";
        $text .= "|";
        $text .= "4 = {{subst:#if:$defesa|~~~~~}}";
        $text .= "}}";
        return $this->edit(
            $text, 
            NULL, 
            FALSE, 
            "Criando discussão de bloqueio{$subpage}", 
            "Wikipedia:Pedidos a administradores/Discussão de bloqueio/{$conta}{$subpage}"
        );
    }

    /**
     * Add a discussion page to the list of pending discussions on Wikipedia.
     *
     * @param string $conta The name of the account to add to the list.
     * @param string $subpage The subpage for the discussion (optional).
     *
     * @return int The revision number of the edit.
     */
    private function doAddList($conta, $subpage = '')
    {
        return $this->edit(
            "{{Ver discussão|Wikipédia:Pedidos a administradores/Discussão de bloqueio/{$conta}{$subpage}|l1=$conta}}", 
            'append', 
            FALSE, 
            "Publicando DB na lista de pedidos", 
            "Wikipédia:Pedidos a administradores/Discussão de bloqueio/Lista de pedidos"
        );
    }

    /**
     * Update the "MRConduta" template on Wikipedia with a new discussion.
     *
     * @param string $conta The name of the account being discussed.
     * @param string $subpage The subpage for the discussion (optional).
     *
     * @return int The revision number of the edit
     */
    private function doMRConduta($conta, $subpage = '')
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
        return $this->edit(
            $text, 
            NULL, 
            FALSE, 
            "Publicando DB no painel de pedidos", 
            "Template:MRConduta"
        );
    }

    /**
     * Move the previous discussion page to a subpage.
     *
     * @param string $conta The username of the blocked user.
     * @return string The new page's title after renamed
     */
    private function doMoveOldDB($conta)
    {
        return $this->move(
            "Wikipedia:Pedidos a administradores/Discussão de bloqueio/$conta", 
            "Wikipédia:Pedidos a administradores/Discussão de bloqueio/$conta/1", 
            'Arquivando discussão anterior'
        );

    }

    /**
     * Create a new disambiguation page for the blocked user.
     *
     * @param string $conta The username of the blocked user.
     * @return int The revision ID of the edit
     */
    private function doDesambig($conta)
    {
        $text  = "{{!Desambiguação}}\n";
        $text .= "\n";
        $text .= "*[[/1|1.º pedido]]\n";
        $text .= "*[[/2|2.º pedido]]\n";
        $text .= "\n";
        $text .= "[[Categoria:!Desambiguações de $conta]]\n";
        $text .= "[[Categoria:!Desambiguações de pedidos de discussão de bloqueio|$conta]]";
        return $this->edit(
            $text, 
            NULL, 
            FALSE, 
            "Publicando página de desambiguação", 
            "Wikipedia:Pedidos a administradores/Discussão de bloqueio/$conta"
        );
    }


    /**
     * Request/send a mass message for an user's discussion block.
     *
     * @param string $conta The name of the blocked user.
     * @param bool $sysop A boolean indicating if the current user is a sysop or not.
     * @return int|array The revision ID of the edit (not sysop) or an array with the mass message API result.
     */
    private function doRequestMassMessage($conta, $sysop)
    {
        $params = [
                'spamlist'  => 'Wikipédia:Pedidos/Discussão de bloqueio/Massmessage',
                'subject'   => "Discussão de bloqueio de $conta",
                'message'   => "{{subst:Usuário:Teles/MassMessage/Desbloqueio|1=$conta|3=~~~~~}}"
        ];
        if ($sysop) {
            $params += [
                'action'    => 'massmessage',
                'token'     => $this->getCsrfToken(),
            ];
            return $this->do($params);
        } else {
            $params += [
                'title'     => 'Especial:Mensagens em massa'
            ];
            $link = 'https://pt.wikipedia.org/w/index.php?' . http_build_query($params);
            $text = file_get_contents("https://pt.wikipedia.org/w/index.php?title=Project:Pedidos/Outros/PreloadMassMessageDB&action=raw");
            $text = str_replace('$1', $conta, $text);
            $text = str_replace('$2',  $link, $text);
            $text = str_replace('<noinclude />', '', $text);
            return $this->edit(
                $text, 
                'new', 
                FALSE, 
                "Mensagens em massa para discussão de bloqueio do usuário $conta", 
                'Wikipédia:Pedidos/Outros'
            );
        }
    }

    /**
     * GNotifying a user about their discussion block.
     *
     * @param string $conta The username of the user to notify.
     * @return array An array containing information about the link.
     */
    private function doNotifyUser($conta)
    {
        return $this->edit(
            '{{subst:Notificação de discussão de bloqueio}}', 
            'new', 
            FALSE, 
            "Notificação de discussão de bloqueio", 
            "User talk:$conta"
        );
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
     * @param string $evidence The evidence string to be used in the new discussion page.
     * @param string $defesa The defense string to be used in the new discussion page.
     * @param string $diff The diff string to be used in the new discussion page.
     * @param array $user Info about the user from OAuth.
     * @return void
    */
    public function run($conta, $evidence, $defesa, $diff, $user)
    {
        $sysop = in_array('massmessage', $user['rights']);

        $result = $this->getCategory($conta);
        $isFirst = isset($result['-1']);
        $isDesambig = !$isFirst && $this->isDesambig($result);

        $index = $isFirst ? '' : ($isDesambig ? $this->getCount($conta) : '/2');

        $this->doNewDB($conta, $defesa, $evidence, $diff, $index);
        $this->doAddList($conta, $index);
        $this->doMRConduta($conta, $index);
        if (!$isFirst && !$isDesambig) {
            $this->doMoveOldDB($conta);
            $this->doDesambig($conta);
        }
        $this->doRequestMassMessage($conta, $sysop);
        $this->doNotifyUser($conta);

        return true;
    }
}


/**
 * This code block instantiates the necessary classes and runs the `run()` method
 * of the `BlockingDiscussion` class with the provided parameters.
 *
 * If the `conta` parameter is not set, the code block returns `false`.
 */
$conta =    filter_input(INPUT_POST, 'conta',    FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$evidence = filter_input(INPUT_POST, 'evidence', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$defesa =   filter_input(INPUT_POST, 'defesa',   FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$diff =     filter_input(INPUT_POST, 'diff',     FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$discussion = new BlockingDiscussion('https://pt.wikipedia.org/w/api.php', $db_consumer_token, $db_secret_token);
$user = $discussion->checkLogin();
if ($conta) {
    $run = $discussion->run($conta, $evidence, $defesa, $diff, $user);
} else {
    $run = false;
}

?><!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <title>Assistente de abertura de discussões de bloqueio</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="./tpar/w3.css">
    </head>
    <body>
        <div class="w3-container" id="menu">
            <div class="w3-content" style="max-width:800px">
                <h5 class="w3-center w3-padding-48"><span class="w3-tag w3-wide">ASSISTENTE DE ABERTURA DE DISCUSSÕES DE BLOQUEIO</span></h5>
                <div class="w3-row-padding w3-center w3-padding-8 w3-margin-top">
                    <div class="w3-container w3-padding-12 w3-card w3-center">
                        <?php if($run): ?>
                            <p>Olá <?=$user['username']?>!</p>
                            <p>Pedido aberto com sucesso!</p>
                            <p>Para abrir um novo pedido, <a href="javascript:window.location.href = window.location.href;">clique aqui</a>.</p>
                        <?php elseif($user): ?>
                            <p>Olá <?=$user['username']?>!</p>
                        <?php else: ?>
                            <p>Olá! Você precisará se identificar 
                                <a href="<?=$_SERVER['SCRIPT_NAME']?>?oauth=seek">aqui</a>
                            antes de usar a ferramenta.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if($user && !$run) : ?>
                    <div class="w3-row-padding w3-center w3-margin-top">
                        <form method="post">
                            <div class="w3-container w3-padding-48 w3-card">
                                <p class="w3-center w3-wide">NOME DO USUÁRIO</p>
                                <p class="w3-text-grey">
                                    <input class="w3-input w3-padding-16 w3-border"
                                    type="text" name="conta" placeholder="Usuário">
                                </p>
                                <br>
                                <p class="w3-center w3-wide">EVIDÊNCIAS:</p>
                                <p>
                                    <textarea class="w3-input w3-padding-16 w3-border"
                                    id="evidence" name="evidence" rows="4" cols="50"
                                    placeholder="Insira aqui as evidências para a solicitação do bloqueio. Utilize [[wikicode]] e não esqueça de assinar com ~~~~."></textarea>
                                </p>
                                <br>
                                <p class="w3-center w3-wide">DEFESA:</p>
                                <p>
                                    <textarea class="w3-input w3-padding-16 w3-border" id="defesa" name="defesa" rows="4" cols="50" placeholder="Insira aqui a defesa, caso fornecida, acompanhada do diff abaixo. Caso contrário, deixe ambos os campos em branco."></textarea>
                                </p>
                                <br>
                                <p class="w3-center w3-wide">DIFF DA DEFESA:</p>
                                <p class="w3-text-grey">
                                    <input class="w3-input w3-padding-16 w3-border"
                                    type="text" name="diff" placeholder="67890123">
                                </p>
                                <p>
                                    <button class="w3-button w3-block w3-black w3-margin-top" type="submit">Abrir discussão de bloqueio</button>
                                </p>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <hr>
        <?php include_once('footer.html'); ?>
    </body>
</html>