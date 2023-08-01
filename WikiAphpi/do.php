<?php

/**
 * A trait that provides methods to perform actions by a MediaWiki API endpoint
 */
trait WikiAphpiDo
{

	use WikiAphpiSee;

	/**
     * Generic function to do actions that require POST
     *
     * @param array $params Parameters to be send to the API
     * @return array API's response
     */
    public function do($params)
    {
        $do = $this->performRequest($params, true);
        if (isset($do['error'])) {
            throw new ContentRetrievalException($do['error']);
        }
        return $do;
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
    public function edit($text, $section, $minor, $summary, $page)
    {

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
        $resultApi = $this->do($params);

        //Retorna número zero caso não não tenha ocorrido mudanças na página
        if (isset($resultApi['edit']["nochange"])) {
            return 0;
        }

        //Retorna número da revisão
        $result = $resultApi['edit']["newrevid"] ?? false;
        if ($result === false) {
            throw new ContentRetrievalException($resultApi);
        }
        return $result;
    }

    /**
     * Deletes a page
     *
     * @param string $page The title of the page to delete.
     * @param string $reason The reason for deleting the page.
     * @return int|bool Log ID on success, false on failure.
     */
    public function delete($page, $reason)
    {
        $params = [
            "action"        => "delete",
            "title"         => $page,
            "reason"        => $reason,
            "token"         => $this->getCsrfToken(),
            "format"        => "php"
        ];
        $resultApi = $this->performRequest($params, true);
        $result = $resultApi['delete']["logid"] ?? false;

        //Retorna número da revisão
        if ($result === false) {
            throw new ContentRetrievalException($resultApi);
        }

        //Retorna número do log
        return $result;
    }

    /**
     * Moves a page
     *
     * @param string $page The title of the page to move.
     * @param string $reason The reason for moving the page.
     * @return string|bool New page title on success, false on failure.
     */
    public function move($oldTitle, $newTitle, $reason, $moveTalk = 1) 
    {
        $params = [
            "action"        => "move",
            "from"          => $oldTitle,
            "to"            => $newTitle,
            "reason"        => $reason,
            "movetalk"      => $moveTalk,
            "token"         => $this->getCsrfToken(),
            "format"        => "php"
        ];
        $resultApi = $this->performRequest($params, true);
        $result = $resultApi['move']["to"] ?? false;

        //Retorna número da revisão
        if ($result === false) {
            throw new ContentRetrievalException($resultApi);
        }

        //Retorna novo nome da página
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
    public function upload($text, $location, $summary, $page)
    {

        //Get siteinfo
        $params_siteinfo = [
            "action"    => "query",
            "meta"      => "siteinfo",
            "siprop"    => "general|fileextensions",
            "format"    => "php"
        ];

        $siteinfo_api = $this->performRequest($params_siteinfo, false);

        //Get max upload size
        $siteinfo["maxuploadsize"] = $siteinfo_api["query"]["general"]["maxuploadsize"] ?? false;
        if ($siteinfo["maxuploadsize"] === false) {
            throw new ContentRetrievalException($siteinfo_api);
        }

        //Get valid extensions
        foreach ($siteinfo_api["query"]["fileextensions"] ?? array() as $ext) {
            $siteinfo["fileextensions"][] = $ext["ext"];
        }
        if (!isset($siteinfo["fileextensions"])) {
            throw new ContentRetrievalException($siteinfo_api);
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

        $upload_api = $this->performRequest($params_upload, true, array('Content-type: multipart/form-data'));

        $result = $upload_api["upload"]["filename"] ?? false;

        //Retorna número da revisão
        if ($result === false) {
            throw new ContentRetrievalException($resultApi);
        }

        //Retorna nome do arquivo
        return $result;
    }
}