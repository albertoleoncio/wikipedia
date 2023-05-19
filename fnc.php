<pre><?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';

class UnreliableSources extends WikiAphpiLogged
{

    /**
     * Retrieves an array of titles for all pages matching specific prefix (or just subpages).
     *
     * @return array The list of page titles.
     */
    private function getAllPages()
    {
        $params = [
            "action"        => "query",
            "format"        => "php",
            "list"          => "allpages",
            "apprefix"      => "Fontes confiáveis/Central de confiabilidade/",
            "apnamespace"   => "4",
            "apfilterredir" => "nonredirects",
            "aplimit"       => "max"
        ];
        $api = $this->see($params)["query"]["allpages"];

        foreach ($api as $pages) {
            if ($pages["pageid"] == '6839845') continue; //Intro page
            if ($pages["pageid"] == '6839835') continue; //Preload page
            $list[] = $pages["title"];
        }

        return $list;
    }
    

    /**
     * Retrieves an array of titles for rejected proposals of unreliable sources in its category.
     *
     * @return array The list of rejected proposal titles.
     */
    private function getRejected()
    {
        $params = [
            "action"  => "query",
            "format"  => "php",
            "list"    => "categorymembers",
            "cmtitle" => "Categoria:!Propostas de fontes não confiáveis rejeitadas",
            "cmprop"  => "title",
            "cmsort"  => "timestamp",
            "cmlimit" => "max"
        ];
        $api = $this->see($params)["query"]["categorymembers"];

        foreach ($api as $pages) {
            $list[] = $pages["title"];
        }

        return $list;
    }


    /**
     * Retrieves an array of titles for approved proposals by filtering out the rejected ones.
     *
     * @return array The list of approved page titles.
     */
    private function getApproved()
    {
        return array_diff($this->getAllPages(), $this->getRejected());
    }


    /**
     * Retrieves the actual consensus for a given proposal by analyzing its sections.
     *
     * This function retrieves the wikitext of the last section with a conclusive state from a given page.
     * It performs the following steps:
     *   1. Queries the API to fetch the sections of the page.
     *   2. Filters the sections to include only level 2 sections.
     *   3. Reverses the order of the sections, starting from the last section.
     *   4. Iterates over the filtered sections, examining their wikitext.
     *   5. Searches for the "estado" parameter in the wikitext to determine the state.
     *   6. If the "estado" parameter is present and set to "inconclusivo", the loop continues to the next section.
     *   7. If the "estado" parameter is not present or is set to a value other than "inconclusivo", the function returns the wikitext of that section.
     *
     * @param string $page The title of the page to retrieve the actual consensus for.
     * @throws Exception If the page is not formatted correctly
     * @return string|null The wikitext of the last section with a conclusive state, or null if no conclusive section is found.
     */
    private function getActualConsensus($page)
    {
        $approved_sections_params = [
            "action"    => "parse",
            "format"    => "php",
            "page"      => $page,
            "prop"      => "sections"
        ];
        $approved_sections = $this->see($approved_sections_params)["parse"]["sections"];

        $filter = array_column($approved_sections, "level");
        $filter = array_keys($filter, "2");
        $filter = array_flip($filter);
        $filter = array_intersect_key($approved_sections, $filter);
        $filter = array_reverse($filter, true);
        foreach ($filter as $section_last) {
            $section_last_wikitext = $this->get($page, $section_last["index"]);
            preg_match_all('/\| *?estado *?= *?\K[^\|]*/', $section_last_wikitext, $estado);
            if (isset($estado["0"]["0"])) {
                if (trim($estado["0"]["0"]) == "inconclusivo") {
                    continue;
                } else {
                    return $section_last_wikitext;
                }
            } else {
                return $section_last_wikitext;
            }
        }
        throw new InvalidArgumentException("Código de $page inadequado!");
        
    }

    /**
     * Processes a domain by checking its blacklist status and formatting it accordingly.
     * Depending on the blacklist result, the function formats the domain as a nowiki tag enclosed with triple quotes if blacklisted, or as a nowiki tag if not blacklisted.
     *
     * @param string|null $domain The domain to process.
     * @return string|false The processed domain enclosed in nowiki tags, or false if the domain is empty or null.
     */
    private function processDomain($domain)
    {
        $domain = trim($domain) ?? false;
        if (empty($domain)) {
            return false;
        }
        
        $params = [
            "action"    => "spamblacklist",
            "format"    => "php",
            "url"       => "https://$domain"
        ];

        if ($this->see($params)["spamblacklist"]["result"] == "blacklisted") {
            return "'''<nowiki>$domain</nowiki>'''";
        } else {
            return "<nowiki>$domain</nowiki>";
        }
    }


    /**
     * Processes the consensus text by extracting parameters and formatting domain information.
     *
     * @param string $text The consensus text to process.
     * @return array|false The processed consensus information as an associative array, or false if the required name parameter is empty.
     */
    private function processConsensus($text)
    {
        preg_match_all('/\| *?nome *?= *?\K[^\|]*/', $text, $nome);
        preg_match_all('/\| *?área *?= *?\K[^\|]*/', $text, $area);
        preg_match_all('/\| *?domínio1 *?= *?\K[^\|]*/', $text, $dominio1);
        preg_match_all('/\| *?domínio2 *?= *?\K[^\|]*/', $text, $dominio2);
        preg_match_all('/\| *?domínio3 *?= *?\K[^\|]*/', $text, $dominio3);
        preg_match_all('/\| *?domínio4 *?= *?\K[^\|]*/', $text, $dominio4);
        preg_match_all('/\| *?domínio5 *?= *?\K[^\|]*/', $text, $dominio5);
        preg_match_all('/\| *?timestamp *?= *?\K[^\|]*/', $text, $timestamp);

        $refname = isset($nome["0"]["0"]) ? trim($nome["0"]["0"]) : false;
        if(empty($refname)) {
            return false;
        }

        return [ 
            "name"      => $refname,
            "area"      => trim($area["0"]["0"] ?? ''),
            "timestamp" => trim($timestamp["0"]["0"] ?? ''),
            "dominio1"  => $this->processDomain($dominio1["0"]["0"] ?? false),
            "dominio2"  => $this->processDomain($dominio2["0"]["0"] ?? false),
            "dominio3"  => $this->processDomain($dominio3["0"]["0"] ?? false),
            "dominio4"  => $this->processDomain($dominio4["0"]["0"] ?? false),
            "dominio5"  => $this->processDomain($dominio5["0"]["0"] ?? false)
        ];
    }


    /**
     * Compiles a table based on the provided list of sources.
     *
     * @param array $list The list of sources to compile into a table.
     * @return string The compiled wikicode representing the table.
     */
    private function compileTable($list)
    {
        uksort($list, array(Collator::create( 'pt_BR' ), 'compare'));

        $wikicode  = "{| class='wikitable sortable' style='font-size: 87%;'\n";
        $wikicode .= "|+ Lista de fontes não confiáveis\n";
        $wikicode .= "|-\n";
        $wikicode .= "! Nome !! Área !! Dia de inclusão !! Domínio(s) associado(s) (em negrito: listado na [[WP:SBLD|SBL]])\n";

        foreach ($list as $key => $value) {
            $date = date("d/m/Y", ((int)$value["timestamp"]));
            $wikicode .= "|-\n";
            $wikicode .= "| <span id='{{anchorencode:$key}}'>[[WP:Fontes confiáveis/Central de confiabilidade/$key|$key]]</span>\n";
            $wikicode .= "| {$value["area"]}\n";
            $wikicode .= "| $date\n";
            $wikicode .= "| ";
            if (!empty($value["dominio1"])) $wikicode .= $value["dominio1"];
            if (!empty($value["dominio2"])) $wikicode .= ", {$value["dominio2"]}";
            if (!empty($value["dominio3"])) $wikicode .= ", {$value["dominio3"]}";
            if (!empty($value["dominio4"])) $wikicode .= ", {$value["dominio4"]}";
            if (!empty($value["dominio5"])) $wikicode .= ", {$value["dominio5"]}";
            $wikicode .= "\n";
        }

        $wikicode .= "|}";
        $wikicode = str_replace('<nowiki></nowiki>','',$wikicode);
        $wikicode = str_replace('<nowiki>, </nowiki>',', ',$wikicode);

        return $wikicode;
    }

    /**
     * Executes the main logic of the bot to update the list of unreliable sources.
     *
     * This function executes the main logic of the bot to update the list of unreliable sources on the Wikipedia page.
     * It performs the following steps:
     *   1. Initializes an empty array to store the compiled source information.
     *   2. Retrieves the approved sources using the `getApproved()` method.
     *   3. Iterates over each approved source and processes the consensus using the `processConsensus()` and `getActualConsensus()` methods.
     *   4. If the processed consensus data is empty, the iteration continues to the next source.
     *   5. Adds the processed consensus data to the list array, using the name as the key.
     *   6. Compiles the table using the `compileTable()` method, passing the list array.
     *   7. Calls the `edit()` method to update the Wikipedia page with the compiled table.
     */
    public function run()
    {
        $list = [];

        $approvedSources = $this->getApproved();
        foreach ($approvedSources as $source) {
            $data = $this->processConsensus($this->getActualConsensus($source));
            if (!$data) continue;
            $list[$data["name"]] = $data;
        }

        $this->edit($this->compileTable($list), NULL, FALSE, "bot: Atualizando lista", "Wikipédia:Fontes não confiáveis/Lista");
    }
}

$api = new UnreliableSources('https://pt.wikipedia.org/w/api.php',$username, $password);
$api->run();
