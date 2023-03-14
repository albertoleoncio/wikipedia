<?php

/*

    Comandos:

    loginAPI($userAPI, $passAPI)
    editAPI($text, $section, $minor, $summary, $page, $userAPI)
    getAPI($page)
    getsectionsAPI($page)
    uploadAPI($text, $location, $summary, $page, $userAPI)
    deleteAPI($page, $reason, $userAPI)
    runAPI($params, $userAPI)

    
    Maior parte dos códigos derivada de:
    MediaWiki API Demos
    MIT license

*/

if (!isset($endPoint)) $endPoint = $api_url;

function loginAPI($userAPI , $passAPI) {
    global $endPoint;

    //Verifica maxlag e usuário atual com base no cookie armazenado
    $params_logged = [
        "action"    => "query",
        "meta"      => "userinfo",
        "uiprop"    => "rights",
        "format"    => "php",
        "maxlag"    => "5"
    ];
    $url_logged = $endPoint . "?" . http_build_query($params_logged);
    $ch_logged = curl_init($url_logged);
    curl_setopt($ch_logged, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_logged, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc");
    curl_setopt($ch_logged, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc");
    $output_logged = curl_exec($ch_logged);
    curl_close($ch_logged);
    $logged_data = unserialize($output_logged)["query"]["userinfo"] ?? false;

    //Interrompe script em caso de maxlag
    if(!$logged_data) die("Maxlagged!");

    //Retorna função caso já exista sessão válida
    if(!isset($logged_data["anon"])) return $logged_data["name"];

    //Coleta token para login
    $token_params = [
        "action"    => "query",
        "meta"      => "tokens",
        "type"      => "login",
        "format"    => "php"
    ];
    $url_token = $endPoint . "?" . http_build_query($token_params);
    $ch_token = curl_init($url_token);
    curl_setopt($ch_token, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_token, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc");
    curl_setopt($ch_token, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc");
    $output_token = curl_exec($ch_token);
    curl_close($ch_token);
    $token = unserialize($output_token)["query"]["tokens"]["logintoken"] ?? false;

    //Interrompe script caso ocorra erro na obtenção do token
    if(!$token) die("Não foi possível solicitar o token de login!");

    //Executa login
    $login_params = [
        "action"        => "login",
        "lgname"        => $userAPI,
        "lgpassword"    => $passAPI,
        "lgtoken"       => $token,
        "format"        => "php"
    ];
    $ch_login = curl_init();
    curl_setopt($ch_login, CURLOPT_URL, $endPoint);
    curl_setopt($ch_login, CURLOPT_POST, true);
    curl_setopt($ch_login, CURLOPT_POSTFIELDS, http_build_query($login_params));
    curl_setopt($ch_login, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_login, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc");
    curl_setopt($ch_login, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc");
    $output_login = curl_exec($ch_login);
    curl_close($ch_login);
    $logged_username = unserialize($output_login)["login"]["lgusername"] ?? false;

    //Interrompe script caso login não tenha ocorrido
    if(!$logged_username) die("Não houve log-in!");

    //Retorna nome de usuário logado
    return $logged_username;

}

function editAPI($text , $section , $minor , $summary , $page, $userAPI) {
    global $endPoint;

    //Coleta token para edição
    $token_params = [
        "action"    => "query",
        "meta"      => "tokens",
        "format"    => "php"
    ];
    $url_token = $endPoint . "?" . http_build_query($token_params);
    $ch_token = curl_init($url_token);
    curl_setopt($ch_token, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_token, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc");
    curl_setopt($ch_token, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc");
    $output_token = curl_exec($ch_token);
    curl_close($ch_token);
    $csrftoken = unserialize($output_token)["query"]["tokens"]["csrftoken"] ?? false;

    //Interrompe script caso ocorra erro na obtenção do token
    if(!$csrftoken) die("Não foi possível solicitar o token CSRF!");

    //Prepara parâmetros básicos para envio ao API
    $edit_params = [
        "action"        => "edit",
        "title"         => $page,
        "text"          => $text,
        "summary"       => $summary,
        "token"         => $csrftoken,
        "format"        => "php"
    ];

    //Caso parâmetro de sessão para edição tenha sido informado
    if (!is_null($section)) {

        //Caso tenha sido informado "append", transfere texto para variável correspondente
        //Caso contrário, realiza edição na sessão informada
        if ($section === "append") {
            $edit_params["appendtext"] = $edit_params["text"];
            unset($edit_params["text"]);
        } else {
            $edit_params["section"] = $section;
        }
    }

    //Atrela edição menor como edição de bot
    if ($minor) {
        $edit_params["minor"] = true;
        $edit_params["bot"] = true;
    }

    //Executa edição
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endPoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($edit_params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc");
    curl_setopt($ch, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc");
    $output = curl_exec($ch);
    curl_close($ch);

    //Coleta resposta do API
    $output = unserialize($output)['edit'] ?? false;

    //Mostra resposta da API via echo
    echo "<pre style='background-color: antiquewhite;'>";
    print_r($output);
    echo "</pre>";

    //Retorna número da revisão
    if (isset($output["newrevid"])) return $output["newrevid"];
}

function getAPI($page) {
    global $endPoint;

    //Prepara parâmetros básicos para envio ao API
    $params = [
        "action"        => "query",
        "prop"          => "revisions",
        "titles"        => $page,
        "rvprop"        => "content",
        "rvslots"       => "main",
        "formatversion" => "2",
        "format"        => "php"
    ];

    //Colega conteúdo da página
    $url = $endPoint . "?" . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);
    $result = unserialize($output);

    //Retorna conteúdo da página ou false em caso de erro
    return $result["query"]["pages"]["0"]["revisions"]["0"]["slots"]["main"]["content"] ?? false;
}

function getsectionsAPI($page) {
    global $endPoint;

    $section = 0;
    $validsection = true;
    $allsections = array();

    while ($validsection) {

        $params = [
            "action"        => "query",
            "prop"          => "revisions",
            "titles"        => $page,
            "rvprop"        => "content",
            "rvslots"       => "main",
            "formatversion" => "2",
            "rvsection"     => $section,
            "format"        => "php"
        ];

        $url = $endPoint . "?" . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);

        $result = unserialize($output);

        if (!isset($result["query"]["pages"]["0"]["revisions"]["0"]["slots"]["main"])) {
            return FALSE;
        } else {
            $main = $result["query"]["pages"]["0"]["revisions"]["0"]["slots"]["main"];
        }

        if (isset($main["nosuchsection"])) {
            $validsection = false;
        } else {
            $allsections[$section] = $main["content"];
            $section++;
        }
    }

    return $allsections;

}

function uploadAPI ($text, $location, $summary, $page, $userAPI) {
    global $endPoint;

    //Get token
    $params1 = [
        "action"    => "query",
        "meta"      => "tokens",
        "format"    => "php"
    ];

    $url1 = $endPoint . "?" . http_build_query($params1);

    $ch1 = curl_init($url1);

    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc");
    curl_setopt($ch1, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc");

    $output1 = curl_exec($ch1);
    curl_close($ch1);

    $result1 = unserialize($output1);
    $csrftoken = $result1["query"]["tokens"]["csrftoken"];

    //Get siteinfo
    $params2 = [
        "action"    => "query",
        "meta"      => "siteinfo",
        "siprop"    => "general|fileextensions",
        "format"    => "php"
    ];

    $url2 = $endPoint . "?" . http_build_query($params2);

    $ch2 = curl_init($url2);

    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc");
    curl_setopt($ch2, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc");

    $output2 = curl_exec($ch2);
    curl_close($ch2);

    $result2 = unserialize($output2);
    $max_upload_size = $result2["query"]["general"]["maxuploadsize"];
    foreach ($result2["query"]["fileextensions"] as $ext) $allowed_extensions[] = $ext["ext"];

    //Verifications about the file
    if (!file_exists($location)) return FALSE;
    if (!in_array(pathinfo($location)['extension'], $allowed_extensions)) return FALSE;
    if (filesize($location) > $max_upload_size) return FALSE;


    $params4 = [
        "action"        => "upload",
        "filename"      => $page,
        "comment"       => $summary,
        "text"          => $text,
        "file"          => curl_file_create($location, mime_content_type($location), $page),
        "token"         => $csrftoken,
        "ignorewarnings"=> "1",
        "format"        => "php"
    ];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $endPoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: multipart/form-data'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params4);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc");
    curl_setopt($ch, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc");

    $output = curl_exec($ch);
    curl_close($ch);

    echo ("<pre style=\"background-color: antiquewhite;\">");
    $output = unserialize($output);
    print_r ($output);
    echo ("</pre>");
}

function deleteAPI($page, $reason, $userAPI) {
    global $endPoint;

    $params3 = [
        "action"    => "query",
        "meta"      => "tokens",
        "format"    => "php"
    ];

    $url = $endPoint . "?" . http_build_query($params3);

    $ch1 = curl_init($url);

    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc");
    curl_setopt($ch1, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc");

    $output1 = curl_exec($ch1);
    curl_close($ch1);

    $result1 = unserialize($output1);
    $csrftoken = $result1["query"]["tokens"]["csrftoken"];

    $params4 = [
        "action"        => "delete",
        "title"         => $page,
        "reason"        => $reason,
        "token"         => $csrftoken,
        "format"        => "php"
    ];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $endPoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params4));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc");
    curl_setopt($ch, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc");

    $output = curl_exec($ch);
    curl_close($ch);

    echo ("<pre style=\"background-color: antiquewhite;\">");
    $output = unserialize($output);
    if (isset($output['delete'])) $output = $output['delete'];
    print_r ($output);
    echo ("</pre>");
    if (isset($output["logid"])) return $output["logid"];
}

function runAPI($params, $userAPI) {
    global $endPoint;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $endPoint . "?" . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc");
    curl_setopt($ch, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc");

    $output = curl_exec($ch);
    curl_close($ch);

    return $output;
}

function optionsAPI($name, $data, $userAPI) {
    global $endPoint;

    //Modo leitura
    if (!$data) {
        $read_params = [
            "action"        => "query",
            "format"        => "php",
            "meta"          => "userinfo",
            "formatversion" => "2",
            "uiprop"        => "options"
        ];
        $ch_read = curl_init();

        curl_setopt($ch_read, CURLOPT_URL, $endPoint . "?" . http_build_query($read_params));
        curl_setopt($ch_read, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_read, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc");
        curl_setopt($ch_read, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc");
        $read = curl_exec($ch_read);
        curl_close($ch_read);

        $read = unserialize(unserialize($read)["query"]["userinfo"]["options"]["userjs-$name"]) ?? false;

        return $read;

    //Modo gravação    
    } else {

        //Coleta token para edição
        $token_params = [
            "action"    => "query",
            "meta"      => "tokens",
            "format"    => "php"
        ];
        $url_token = $endPoint . "?" . http_build_query($token_params);
        $ch_token = curl_init($url_token);
        curl_setopt($ch_token, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_token, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc");
        curl_setopt($ch_token, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc");
        $output_token = curl_exec($ch_token);
        curl_close($ch_token);
        $csrftoken = unserialize($output_token)["query"]["tokens"]["csrftoken"] ?? false;

        //Interrompe script caso ocorra erro na obtenção do token
        if(!$csrftoken) die("Não foi possível solicitar o token CSRF!");

        //Prepara parâmetros básicos para envio ao API
        $write_params = [
            "action"        => "options",
            "token"         => $csrftoken,
            "optionname"    => "userjs-$name",
            "optionvalue"   => serialize($data),
            "format"        => "php"
        ];

        //Executa edição
        $ch_write = curl_init();
        curl_setopt($ch_write, CURLOPT_URL, $endPoint);
        curl_setopt($ch_write, CURLOPT_POST, true);
        curl_setopt($ch_write, CURLOPT_POSTFIELDS, http_build_query($write_params));
        curl_setopt($ch_write, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_write, CURLOPT_COOKIEJAR, $userAPI."_cookie.inc");
        curl_setopt($ch_write, CURLOPT_COOKIEFILE, $userAPI."_cookie.inc");
        $write = curl_exec($ch_write);
        curl_close($ch_write);

        //Coleta resposta do API
        $write = unserialize($write) ?? false;

        //Mostra resposta da API via echo
        echo "<pre style='background-color: antiquewhite;'>";
        print_r($write);
        echo "</pre>";

        //Retorna resultado da API
        if (isset($write["options"])) {
            return true;
        } else {
            return false;
        }
    }
}