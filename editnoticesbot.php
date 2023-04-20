<pre><?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';

class EditNotices extends WikiAphpiLogged
{

    /**
     * Extract the page title from a MediaWiki page title string.
     *
     * @param string $pageTitle The full page title string.
     * @return string The extracted page title.
     */
    private function extractPageTitle($pageTitle)
    {
        $pageTitle = str_replace(['MediaWiki:Editnotice-0-', 'Discussão:'], '', $pageTitle);
        $pageTitle = str_replace('/', '-', $pageTitle);

        return $pageTitle;
    }

    /**
     * Get a list of page titles for a given page key.
     *
     * @param array $params An array of API parameters to use in the request.
     * @param string $pageKey The key of the page list in the API response.
     * @return array The list of page titles.
     */
    private function getPageTitles($params, $pageKey)
    {
        $pageTitleList = [];

        do {
            $api = $this->see($params);
            foreach ($api["query"][$pageKey] as $page) {
                $pageTitleList[] = $this->extractPageTitle($page['title']);
            }
            $continueKey = key_exists("continue", $api) ? key($api["continue"]) : false;
            $params[$continueKey] = $api['continue'][$continueKey] ?? false;
        } while ($params[$continueKey]);

        return $pageTitleList;
    }

    /**
     * Get a list of members of a category with the given title.
     *
     * @param string $title The title of the category to get the members of.
     * @return array The list of category members' page titles.
     */
    private function getCategoryMembers($title)
    {
        $params = [
            "action"        => "query",
            "format"        => "php",
            "list"          => "categorymembers",
            "cmtitle"       => $title,
            "cmprop"        => "title",
            "cmnamespace"   => "1",
            "cmlimit"       => "max"
        ];
        return $this->getPageTitles($params, "categorymembers");
    }

    /**
     * Get a list of pages that embed the given page.
     *
     * @param string $title The title of the page to get the embedders of.
     * @return array The list of pages that embed the given page.
     */
    private function getEmbeddedIn($title)
    {
        $params = [
            "action"        => "query",
            "format"        => "php",
            "list"          => "embeddedin",
            "eititle"       => $title,
            "einamespace"   => "8",
            "eilimit"       => "max"
        ];

        $list = $this->getPageTitles($params, "embeddedin");

        //The given page is automatically included in the returned
        //list to avoid a false positive in the results.
        $list[] = $this->extractPageTitle($title);

        return $list;
    }

    /**
     * Get a list of page titles that should exist based on the given edit notice data.
     *
     * @param array $editNoticeData The edit notice data to use to determine which pages should exist.
     * @return array The list of page titles that should exist.
     */
    private function getPagesThatShouldExist($editNoticeData)
    {
        $needs = array_merge(
            $this->getCategoryMembers($editNoticeData["afinidade"]),
            $this->getCategoryMembers($editNoticeData["escrito"])
        );
        return array_unique($needs);
    }

    /**
     * Get a list of page titles that already exist based on the given edit notice data.
     * Note: The page specified by the "afluir" key in the edit notice data is automatically
     * included in the returned list to avoid a false negative in later methods.
     *
     * @param array $editNoticeData The edit notice data to use to determine which pages already exist.
     * @return array The list of page titles that already exist.
     */
    private function getPagesThatAlreadyExist($editNoticeData)
    {
        return $this->getEmbeddedIn($editNoticeData["afluir"]);
    }

    /**
     * Delete the pages with the specified titles.
     * Note: The bot will sleep for 10 seconds after
     * each page deletion in accordance with the local bot policy.
     *
     * @param array $pagesToDelete The list of page titles to delete.
     */
    private function deletePages($pagesToDelete)
    {
        foreach ($pagesToDelete as $page) {
            $this->delete(
                "MediaWiki:Editnotice-0-$page",
                "G1 - [[WP:ER#ERg1|Eliminação técnica]] (bot: Eliminando editnotice desnecessário)"
            );
            echo("<br>Eliminado MediaWiki:Editnotice-0-$page");
            sleep(10);
        }
    }

    /**
     * Create new pages with the provided page titles using the provided edit notice data.
     * Note: The bot will sleep for 10 seconds after
     * each page creation in accordance with the local bot policy.
     *
     * @param array $pagesToCreate The list of page titles to create.
     * @param string $editNoticeData The edit notice data to use when creating the pages.
     */
    private function createPages($pagesToCreate, $editNoticeData)
    {
        foreach ($pagesToCreate as $page) {
            $this->edit(
                "{{:$editNoticeData}}",
                NULL,
                TRUE,
                "bot: Criando editnotice",
                "MediaWiki:Editnotice-0-$page"
            );
            echo("<br>Criado MediaWiki:Editnotice-0-$page");
            sleep(10);
        }
    }

    /**
     * Runs the Edit Notice Bot for a given category and Edit Notice data.
     *
     * @param string $category The name of the category to process.
     * @param array $editNoticeData An array containing the data required for the Edit Notice.
     *        It should have the following keys:
     *        - "afinidade": The name of the category containing articles with strong ties to the country where the language variation is spoken.
     *        - "escrito": The name of the category containing articles written in the language variation.
     *        - "afluir": The name of the page that will be linked in the main Edit Notice.
     */
    public function run($category, $editNoticeData)
    {
        $needs = $this->getPagesThatShouldExist($editNoticeData);
        $exists = $this->getPagesThatAlreadyExist($editNoticeData);

        $toCreate = array_diff($needs, $exists);
        $toDelete = array_diff($exists, $needs);

        $this->deletePages($toDelete);
        $this->createPages($toCreate, $editNoticeData["afluir"]);
    }
}

/**
 * Runs the EditNotices bot for multiple Wikipedia language versions.
 *
 * @param array $data An associative array where each key represents an ISO code for a language version, and the value is another associative array with the following keys:
 *  - escrito: The name of the category containing articles written in the language variation.
 *  - afinidade: The name of the category containing articles with strong ties to the country or region where the language variation is spoken.
 *  - afluir: The name of the edit notice that should be associated with the articles.
 */
$data = [
    "br" => [
        "escrito"       => "Categoria:!Artigos escritos em português brasileiro",
        "afinidade"     => "Categoria:!Artigos que mantêm fortes afinidades com o Brasil",
        "afluir"        => "MediaWiki:Editnotice-0-Brasil"
    ],
    "pt" => [
        "escrito"       => "Categoria:!Artigos escritos em português europeu",
        "afinidade"     => "Categoria:!Artigos com fortes afinidades a Portugal",
        "afluir"        => "MediaWiki:Editnotice-0-Portugal"
    ],
    "mz" => [
        "escrito"       => "Categoria:!Artigos escritos em português moçambicano",
        "afinidade"     => "Categoria:!Artigos com fortes afinidades a Moçambique",
        "afluir"        => "MediaWiki:Editnotice-0-Moçambique"
    ],
    "ao" => [
        "escrito"       => "Categoria:!Artigos escritos em português angolano",
        "afinidade"     => "Categoria:!Artigos com fortes afinidades a Angola",
        "afluir"        => "MediaWiki:Editnotice-0-Angola"
    ]
];
$api = new EditNotices('https://pt.wikipedia.org/w/api.php', $usernameEN, $passwordEN);
foreach ($data as $iso => $variation) {
    $api->run($iso, $variation);
}