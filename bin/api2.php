<?php


/**
 * The WikiAphpi class provides an interface for interacting with MediaWiki API.
 *
 * This abstract class implements common methods for accessing and modifying data
 * on a MediaWiki website using its API. It is designed to be extended by concrete
 * classes that implement specific functionality. Authentication is required through
 * a endpoint, username and password, performing a login operation upon instantiation.
 */
abstract class WikiAphpi {

    /** @var string The base URL for API requests. */
    private $endpoint;

    /** @var string The username to use for API requests. */
    private $userAPI;

    /** @var string The password to use for API requests. */
    private $passAPI;

    /**
     * Constructor for WikiAphpi class with login.
     *
     * @param string $endpoint The base URL for API requests.
     * @param string $userAPI The username to use for API requests.
     * @param string $passAPI The password to use for API requests.
     */
    public function __construct($endpoint, $userAPI, $passAPI) {
        $this->endpoint = $endpoint;
        $this->userAPI = $userAPI;
        $this->passAPI = $passAPI;

        // perform login in constructor
        $this->login();
    }


    /**
     * Sends a curl request to the specified endpoint with the provided parameters.
     *
     * @param string $endpoint The endpoint to send the request to.
     * @param array $params The parameters to include in the request.
     * @param bool $isPost Whether the request should be a POST request (true) or a GET request (false).
     * @return array The response from the curl request.
     * @throws Exception if there is an error sending the curl request.
     */
    private function sendCurlRequest($params, $isPost, $headers = false) {
        $url = $this->endpoint;
        $ch = curl_init();

        // making sure to request the right format
        $params['format'] = 'php';

        // set the request type and parameters
        if ($isPost) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            $url .= "?" . http_build_query($params);
        }

        // optional for specific headers
        if ($headers !== false) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // set other curl options as needed
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->userAPI."_cookie.inc");
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->userAPI."_cookie.inc");

        // execute the curl request and handle any errors
        $result = curl_exec($ch);
        if ($result === false) {
            throw new Exception("Error sending curl request: " . curl_error($ch));
        }

        // close the curl handle and return the result
        curl_close($ch);
        $result2 = @unserialize($result);
        if ($result2 === false) {
            throw new Exception("Error unserializing. API' format is PHP?");
        }

        return $result2;
    }


    /**
     * Logs in to the API using the provided username and password.
     *
     * @return string The username of the logged-in user.
     */
    private function login() {
        //Verifica maxlag e usuário atual com base no cookie armazenado
        $logged_params = [
            "action"    => "query",
            "meta"      => "userinfo",
            "uiprop"    => "rights",
            "format"    => "php",
            "maxlag"    => "5"
        ];
        $logged_api = $this->sendCurlRequest($logged_params, false);
        $logged_data = $logged_api["query"]["userinfo"] ?? false;

        if ($logged_data === false) {
            throw new Exception(print_r($logged_api, true));
        }

        //Retorna caso já exista sessão válida
        if (!isset($logged_data["anon"])) {
            return $logged_data["name"];
        }

        //Coleta token para login
        $token_params = [
            "action"    => "query",
            "meta"      => "tokens",
            "type"      => "login",
            "format"    => "php"
        ];
        $token_api = $this->sendCurlRequest($token_params, false);
        $token = $token_api["query"]["tokens"]["logintoken"] ?? false;

        //Interrompe script caso ocorra erro na obtenção do token de login
        if ($token === false) {
            throw new Exception(print_r($token_api, true));
        }

        //Executa login
        $login_params = [
            "action"        => "login",
            "lgname"        => $this->userAPI,
            "lgpassword"    => $this->passAPI,
            "lgtoken"       => $token,
            "format"        => "php"
        ];
        $login_api = $this->sendCurlRequest($login_params, true);
        $logged_username = $login_api["login"]["lgusername"] ?? false;

        //Interrompe script caso login não tenha ocorrido
        if ($logged_username === false) {
            throw new Exception(print_r($login_api, true));
        }

        //Retorna nome de usuário logado
        return $logged_username;
    }


    /**
     * Gets a CSRF token for the current user.
     *
     * @return string The CSRF token.
     */
    private function getCsrfToken() {
        //Coleta token
        $params = [
            "action"    => "query",
            "meta"      => "tokens",
            "format"    => "php"
        ];
        $resultApi = $this->sendCurlRequest($params, false);
        $result = $resultApi["query"]["tokens"]["csrftoken"] ?? false;

        //Interrompe script caso ocorra erro na obtenção do token
        if ($result === false) {
            throw new Exception(print_r($resultApi, true));
        }

        return $result;
    }


    /**
     * Get the content of a page
     *
     * @param strint $page Page to be readed.
     * @param strint $section Section to be readed.
     * @return string Content of the page.
     */
    public function get($page, $section = false) {
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
        if ($section) $params['rvsection'] = $section;
        $resultApi = $this->sendCurlRequest($params, false);
        $result = $resultApi["query"]["pages"]["0"]["revisions"]["0"]["slots"]["main"]["content"] ?? false;

        //Interrompe script caso ocorra erro na obtenção do token
        if ($result === false) {
            throw new Exception(print_r($resultApi, true));
        }

        return $result;
    }


    /**
     * Get the content of a page divided in sections
     *
     * @param strint $page Page to be readed.
     * @return array Content of the page.
     */
    public function getSections($page) {

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

            $result = $this->sendCurlRequest($params, false);

            $main = $result["query"]["pages"]["0"]["revisions"]["0"]["slots"]["main"] ?? false;

            if ($main === false) {
                throw new Exception(print_r($result, true));
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


    /**
     * Edits a page.
     *
     * @param string $text Content of the page
     * @param sting $section Number of section to be edited, "append" to add at the end of page or null to edit the entire page.
     * @param bool $minor True makes the edit be marked as a minor edit and a bot edit.
     * @param type $summary Summary of edit.
     * @param type $page Page to be edited.
     * @return int The revision ID.
     */
    public function edit($text, $section, $minor, $summary, $page) {

        //Prepara parâmetros básicos para envio ao API
        $params = [
            "action"        => "edit",
            "title"         => $page,
            "text"          => $text,
            "summary"       => $summary,
            "token"         => $this->getCsrfToken(),
            "format"        => "php"
        ];

        //Caso parâmetro de sessão para edição tenha sido informado
        if (!is_null($section)) {

            //Caso tenha sido informado "append", transfere texto para variável correspondente
            //Caso contrário, realiza edição na sessão informada
            if ($section === "append") {
                $params["appendtext"] = $params["text"];
                unset($params["text"]);
            } else {
                $params["section"] = $section;
            }
        }

        //Atrela edição menor como edição de bot
        if ($minor) {
            $params["minor"] = true;
            $params["bot"] = true;
        }

        //Executa edição
        $resultApi = $this->sendCurlRequest($params, true);

        //Retorna número zero caso não não tenha ocorrido mudanças na página
        if (isset($resultApi['edit']["nochange"])) {
            return 0;
        }

        //Retorna número da revisão
        $result = $resultApi['edit']["newrevid"] ?? false;
        if ($result === false) {
            throw new Exception(print_r($resultApi, true));
        }
        return $result;
    }


    /**
     * Deletes a page
     *
     * @param string $page The title of the page to delete.
     * @param string $reason The reason for deleting the page.
     * @return bool True on success, false on failure.
     */
    public function delete($page, $reason) {
        $params = [
            "action"        => "delete",
            "title"         => $page,
            "reason"        => $reason,
            "token"         => $this->getCsrfToken(),
            "format"        => "php"
        ];
        $resultApi = $this->sendCurlRequest($params, true);
        $result = $resultApi['delete']["logid"] ?? false;

        //Retorna número da revisão
        if ($result === false) {
            throw new Exception(print_r($resultApi, true));
        }

        //Retorna número do log
        return $result;
    }


    /**
     * Upload a file
     *
     * @param string $text Content of the associated page.
     * @param string $location Location of the file to upload.
     * @param string $summary Summary of edit.
     * @param string $page Name of file on wiki.
     * @return string Name of file on wiki.
     */
    public function upload($text, $location, $summary, $page) {

        //Get siteinfo
        $params_siteinfo = [
            "action"    => "query",
            "meta"      => "siteinfo",
            "siprop"    => "general|fileextensions",
            "format"    => "php"
        ];

        $siteinfo_api = $this->sendCurlRequest($params_siteinfo, false);

        //Get max upload size
        $siteinfo["maxuploadsize"] = $siteinfo_api["query"]["general"]["maxuploadsize"] ?? false;
        if ($siteinfo["maxuploadsize"] === false) {
            throw new Exception(print_r($siteinfo_api, true));
        }

        //Get valid extensions
        foreach ($siteinfo_api["query"]["fileextensions"] ?? array() as $ext) {
            $siteinfo["fileextensions"][] = $ext["ext"];
        }
        if (!isset($siteinfo["fileextensions"])) {
            throw new Exception(print_r($siteinfo_api, true));
        }

        //Verifications about the file
        if (!file_exists($location)) {
            throw new Exception("File not found!");
        }
        if (!in_array(pathinfo($location)['extension'], $siteinfo["fileextensions"])) {
            throw new Exception("Extension not allowed!");
        }
        if (filesize($location) > $siteinfo["maxuploadsize"]) {
            throw new Exception("File too big!");
        }


        $params_upload = [
            "action"        => "upload",
            "filename"      => $page,
            "comment"       => $summary,
            "text"          => $text,
            "file"          => curl_file_create($location, mime_content_type($location), $page),
            "token"         => csrfAPI($userAPI),
            "ignorewarnings"=> "1",
            "format"        => "php"
        ];

        $upload_api = $this->sendCurlRequest($params_upload, true, array('Content-type: multipart/form-data'));

        $result = $upload_api["upload"]["filename"] ?? false;

        //Retorna número da revisão
        if ($result === false) {
            throw new Exception(print_r($resultApi, true));
        }

        //Retorna nome do arquivo
        return $result;
    }


    /**
     * Read a option
     *
     * @param string $name Name of the option
     * @return string The content of the option
     */
    public function readOption($name) {

        $params = [
            "action"        => "query",
            "format"        => "php",
            "meta"          => "userinfo",
            "formatversion" => "2",
            "uiprop"        => "options"
        ];
        $resultApi = $this->sendCurlRequest($params, false);

        return unserialize(unserialize($resultApi)["query"]["userinfo"]["options"]["userjs-wikiaphpi-$name"]) ?? false;
    }


    /**
     * Write a option
     *
     * @param string $name Name of the option
     * @param string $data Content of the option
     * @return bool True if saved
     */
    public function writeOption($name, $data) {


        $params = [
            "action"        => "options",
            "token"         => $this->getCsrfToken(),
            "optionname"    => "userjs-wikiaphpi-$name",
            "optionvalue"   => serialize($data),
            "format"        => "php"
        ];

        //Executa edição
        $resultApi = $this->sendCurlRequest($params, true);

        //Coleta resposta do API
        $result = unserialize($resultApi) ?? false;

        //Retorna resultado da API
        if (!isset($result["options"])) {
            throw new Exception(print_r($resultApi, true));
        }

        return true;
    }


    /**
     * Generic function to get logged info without any action
     *
     * @param array $params Parameters to be send to the API
     * @return array API's response
     */
    public function see($params) {
        $see = $this->sendCurlRequest($params, false);
        if (isset($see['error'])) {
            throw new Exception(print_r($see['error'], true));
        }
        return $see;
    }


    /**
     * Generic function to do actions that require POST
     *
     * @param array $params Parameters to be send to the API
     * @return array API's response
     */
    public function do($params) {
        $do = $this->sendCurlRequest($params, true);
        if (isset($do['error'])) {
            throw new Exception(print_r($do['error'], true));
        }
        return $do;
    }

}
