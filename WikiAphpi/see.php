<?php

/**
 * A trait that provides methods to read content from a MediaWiki API endpoint
 */
trait WikiAphpiSee
{
	/**
     * Generic function to get logged info without any action
     *
     * @param array $params Parameters to be send to the API
     * @return array API's response
     */
    public function see($params)
    {
        $see = $this->performRequest($params, false);
        if (isset($see['error'])) {
            throw new ContentRetrievalException($see['error']);
        }
        return $see;
    }

    /**
     * Get the content of a page
     *
     * @param strint $page Page to be readed.
     * @param strint $section Section to be readed.
     * @return string Content of the page.
     */
    public function get($page, $section = false)
    {
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
        if (is_numeric($section)) $params['rvsection'] = $section;
        $resultApi = $this->see($params);
        $result = $resultApi["query"]["pages"]["0"]["revisions"]["0"]["slots"]["main"]["content"] ?? false;

        //Interrompe script caso ocorra erro na obtenção do token
        if ($result === false) {
            throw new ContentRetrievalException($resultApi);
        }

        return $result;
    }

    /**
     * Get the content of a page divided in sections
     *
     * @param strint $page Page to be readed.
     * @return array Content of the page.
     */
    public function getSections($page)
    {

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

            $result = $this->see($params);

            $main = $result["query"]["pages"]["0"]["revisions"]["0"]["slots"]["main"] ?? false;

            if ($main === false) {
                throw new ContentRetrievalException($result);
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
     * Gets a CSRF token for the current user.
     *
     * @return string The CSRF token.
     */
    public function getCsrfToken()
    {
        //Coleta token
        $params = [
            "action"    => "query",
            "meta"      => "tokens",
            "format"    => "php"
        ];
        $resultApi = $this->see($params);
        $result = $resultApi["query"]["tokens"]["csrftoken"] ?? false;

        //Interrompe script caso ocorra erro na obtenção do token
        if ($result === false) {
            throw new ContentRetrievalException($resultApi);
        }

        return $result;
    }

    /**
     * Resolves the target page of a redirect and returns its title.
     *
     * @param string $page The title of the page that may be a redirect.
     * @return string The title of the target page, or the input page title if it is not a redirect.
     */
    public function resolveRedirect($page)
    {
        //Coleta token
        $params = [
            'action'    => 'query',
            'format'    => 'php',
            'titles'    => $page,
            'redirects' => '1'
        ];
        $resultApi = $this->see($params);
        if (isset($resultApi["query"]["redirects"])) {
            return $resultApi["query"]["redirects"]['0']["to"];
        } else {
            return $page;
        }
    }

    /**
     * Checks whether a page exists on the wiki.
     *
     * @param string $page The title of the page to check.
     * @return bool Returns `true` if the page exists, `false` otherwise.
     */
    public function isPageCreated($page)
    {
        $params = [
            'action'        => 'query',
            'format'        => 'php',
            'titles'        => $page,
            "formatversion" => "2"
        ];
        $api = $this->see($params);
        $missing = $api['query']['pages']['0']['missing'] ?? false;
        return !boolval($missing);
    }
}