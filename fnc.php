<pre><?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';

class UnreliableSources extends WikiAphpiLogged
{

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
            $list[] = $pages["title"];
        }

        return $list;
    }

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

    private function getApproved()
    {
        return array_diff($this->getAllPages(), $this->getRejected());
    }

    private function getActualConsensus($page)
    {
        //Procura número da última seção de nível principal
        $approved_sections_params = [
            "action"    => "parse",
            "format"    => "php",
            "page"      => $page,
            "prop"      => "sections"
        ];
        $approved_sections = $this->see($approved_sections_params)["parse"]["sections"];

        //Cria array com parâmetros "level" de cada seção
        $filter = array_column($approved_sections, "level");

        //Coleta as keys de cada level e insere como valores em uma array
        $filter = array_keys($filter, "2");

        //Inverte array, para que os valores se tornem keys
        $filter = array_flip($filter);

        //Finaliza filtragem, criando array com as keys correspondentes
        $filter = array_intersect_key($approved_sections, $filter);

        //Inverte ordem das seções para começar análise a partir da última seção, mantendo as keys
        $filter = array_reverse($filter, true);

        //Loop para recuperar o código-fonte de cada seção, a partir da última
        foreach ($filter as $section_last) {
            $section_last_wikitext = $this->get($page, $section_last["index"]);

            //Procura pelo parâmetro "estado"
            preg_match_all('/\| *?estado *?= *?\K[^\|]*/', $section_last_wikitext, $estado);

            //Verifica se o parâmetro existe
            //Caso sim, verifica se o valor é "inconclusivo" e, caso sim, pula loop atual e procede para a próxima seção
            //Caso não, interrompe loop e segue para a análise da seção
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
    }

    private function processDomain($domain)
    {
        if (empty($domain)) {
            return false;
        } else {
            $domain = trim($domain);
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

    private function processConsensus($text)
    {
        //Captura parâmetros via regex
        preg_match_all('/\| *?nome *?= *?\K[^\|]*/', $text, $nome);
        preg_match_all('/\| *?área *?= *?\K[^\|]*/', $text, $area);
        preg_match_all('/\| *?domínio1 *?= *?\K[^\|]*/', $text, $dominio1);
        preg_match_all('/\| *?domínio2 *?= *?\K[^\|]*/', $text, $dominio2);
        preg_match_all('/\| *?domínio3 *?= *?\K[^\|]*/', $text, $dominio3);
        preg_match_all('/\| *?domínio4 *?= *?\K[^\|]*/', $text, $dominio4);
        preg_match_all('/\| *?domínio5 *?= *?\K[^\|]*/', $text, $dominio5);
        preg_match_all('/\| *?timestamp *?= *?\K[^\|]*/', $text, $timestamp);

        //Insere dados na array
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
