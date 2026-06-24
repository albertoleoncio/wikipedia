<?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';

class Working extends WikiAphpiLogged
{
    /**
     * Retrieves recent top-level changes in the Page namespace from pt.wikisource.
     *
     * @return array
     */
    private function getRecentChanges()
    {
        $params = [
            'action'        => 'query',
            'list'          => 'recentchanges',
            'formatversion' => '2',
            'rcnamespace'   => '106',
            'rcprop'        => 'title',
            'rclimit'       => 'max',
            'rctoponly'     => '1'
        ];

        $response = $this->see($params);
        return $response['query']['recentchanges'] ?? [];
    }

    /**
     * Extracts unique Index pages from recent changes and keeps only the 10 most recent.
     *
     * @param array $recentChanges
     * @return array
     */
    private function getRecentIndexes(array $recentChanges)
    {
        $indexes = [];

        foreach ($recentChanges as $page) {
            $rootPage = explode('/', $page['title']);

            // Ignore edits not tied to an Index subpage.
            if (!isset($rootPage[1])) {
                continue;
            }

            $indexes[] = 'Index:' . substr($rootPage[0], 8);
        }

        $indexes = array_unique($indexes);
        $indexes = array_slice($indexes, 0, 10);
        asort($indexes);

        return $indexes;
    }

    /**
     * Retrieves quality counts for all pages of an Index.
     *
     * @param string $index
     * @return array
     */
    private function getIndexQuality(string $index)
    {
        $params = [
            'action'        => 'query',
            'prop'          => 'proofread',
            'generator'     => 'proofreadpagesinindex',
            'formatversion' => '2',
            'gprppiititle'  => $index
        ];

        $response = $this->see($params);
        $pages = $response['query']['pages'] ?? [];

        $quality = [
            'x' => 0,
            '0' => 0,
            '1' => 0,
            '2' => 0,
            '3' => 0,
            '4' => 0,
        ];

        foreach ($pages as $page) {
            if (isset($page['missing'])) {
                $quality['x']++;
            } elseif (($page['proofread']['quality'] ?? null) === 0) {
                $quality['0']++;
            } elseif (($page['proofread']['quality'] ?? null) === 1) {
                $quality['1']++;
            } elseif (($page['proofread']['quality'] ?? null) === 2) {
                $quality['2']++;
            } elseif (($page['proofread']['quality'] ?? null) === 3) {
                $quality['3']++;
            } elseif (($page['proofread']['quality'] ?? null) === 4) {
                $quality['4']++;
            } else {
                throw new ContentRetrievalException($page);
            }
        }

        return $quality;
    }

    /**
     * Retrieves and normalizes the display title from an Index page.
     *
     * @param string $index
     * @return string
     */
    private function getOfficialTitle(string $index)
    {
        $params = [
            'action'        => 'parse',
            'page'          => $index,
            'prop'          => 'wikitext',
            'formatversion' => '2'
        ];

        $response = $this->see($params);
        $content = $response['parse']['wikitext'] ?? '';
        preg_match('/Título=([\s\S]*?)\\n/', $content, $title);
        $title = trim($title[1] ?? $index, '[]');
        $titleExploded = explode('|', $title);

        return end($titleExploded);
    }

    /**
     * Builds the wikitext table for the progress template.
     *
     * @param array $qualityByIndex
     * @return string
     */
    private function buildWikitext(array $qualityByIndex)
    {
        $wikitext = "<templatestyles src='Progressos recentes/styles.css' />\n{|\n";

        foreach ($qualityByIndex as $index => $item) {
            $total = array_sum($item);

            if ($total === 0) {
                continue;
            }

            $item['0'] = round(($item['0'] / $total), 2, PHP_ROUND_HALF_DOWN) * 100;
            $item['1'] = round(($item['1'] / $total), 2, PHP_ROUND_HALF_DOWN) * 100;
            $item['2'] = round(($item['2'] / $total), 2, PHP_ROUND_HALF_DOWN) * 100;
            $item['3'] = round(($item['3'] / $total), 2, PHP_ROUND_HALF_DOWN) * 100;
            $item['4'] = round(($item['4'] / $total), 2, PHP_ROUND_HALF_DOWN) * 100;
            $item['x'] = 100 - $item['0'] - $item['1'] - $item['2'] - $item['3'] - $item['4'];

            $title = $this->getOfficialTitle($index);

            $wikitext .= "|-\n| {{Barra de progresso|{$item['1']}|{$item['2']}|{$item['3']}|{$item['4']}|{$item['0']}|{$item['x']}}}\n| [[{$index}|{$title}]]\n";
        }

        $wikitext .= "|}<noinclude>{{documentação}}</noinclude>";

        return $wikitext;
    }

    /**
     * Runs the full workflow and updates the target template page.
     *
     * @return int New revision id or 0 when no change was made.
     */
    public function run()
    {
        $recentChanges = $this->getRecentChanges();
        $indexes = $this->getRecentIndexes($recentChanges);

        $qualityByIndex = [];
        foreach ($indexes as $index) {
            $qualityByIndex[$index] = $this->getIndexQuality($index);
        }

        $wikitext = $this->buildWikitext($qualityByIndex);

        return $this->edit(
            $wikitext,
            null,
            false,
            'bot: Atualizando progressos',
            'Predefinição:Progressos recentes'
        );
    }
}

// Run script
$api = new Working('https://pt.wikisource.org/w/api.php', $username, $password);
print_r($api->run());
