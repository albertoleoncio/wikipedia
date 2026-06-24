<?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';

date_default_timezone_set('UTC');

class RssFeed extends WikiAphpiUnlogged
{
    /**
     * Read wikitext from a revision slot, supporting both API shapes.
     *
     * @param array $revision
     * @return string
     */
    private function getRevisionText(array $revision)
    {
        return $revision['slots']['main']['content']
            ?? $revision['slots']['main']['*']
            ?? '';
    }

    /**
     * Resolve final URL after redirects.
     *
     * @param string $address
     * @return string
     */
    public function resolveRedirect($address)
    {
        if (empty($address)) {
            return '';
        }

        $ch = curl_init($address);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        $location = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return $location;
    }

    /**
     * Get image payload used by social media publishing.
     *
     * @param string $article
     * @return array|null
     */
    private function findImage($article)
    {
        $pageImageApi = $this->see([
            'action' => 'query',
            'redirects' => '1',
            'prop' => 'pageimages',
            'piprop' => 'name',
            'titles' => $article,
        ]);
        $pages = $pageImageApi['query']['pages'] ?? [];
        if (empty($pages)) {
            return null;
        }
        $pageData = end($pages);

        if (!isset($pageData['pageimage'])) {
            return null;
        }

        $metaApi = $this->see([
            'action' => 'query',
            'prop' => 'imageinfo',
            'iiprop' => 'extmetadata',
            'titles' => 'Ficheiro:' . rawurlencode($pageData['pageimage']),
        ]);
        $metaPages = $metaApi['query']['pages'] ?? [];

        // Keep historical behavior: only continue when image id is -1 (URC).
        if (!isset($metaPages['-1'])) {
            return null;
        }

        $meta = $metaPages['-1']['imageinfo'][0]['extmetadata'] ?? [];

        $ch = curl_init('https://pt.wikipedia.org/wiki/Especial:Redirecionar/file/' . $pageData['pageimage'] . '?width=1000');
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        $location = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (!isset($meta['Artist']['value'])) {
            $meta['Artist']['value'] = 'Desconhecido';
        }

        return [
            'image_about' => 'Para saber mais sobre o tema, basta acessar o link na bio e o artigo estará na nossa página principal!' . "\n\n\n\n" . 'Sobre a imagem:' . "\n" . 'Autor: ' . trim(strip_tags($meta['Artist']['value'])) . '.' . "\n" . 'Licença ' . trim(strip_tags($meta['LicenseShortName']['value'] ?? 'desconhecida')) . '.' . "\n" . 'Para mais informações sobre essa imagem, entre no endereço da bio e pesquise por Imagem:' . $pageData['pageimage'],
            'image_url' => $location,
            'image_lenght' => $size,
            'image_type' => $type,
        ];
    }

    /**
     * Build EAD feed items.
     *
     * @return array
     */
    private function buildEadItems()
    {
        $api = $this->see([
            'action' => 'query',
            'prop' => 'revisions',
            'titles' => 'Usuário(a):AlbeROBOT/EAD',
            'rvprop' => 'timestamp|content|ids',
            'rvslots' => 'main',
            'rvlimit' => '1',
        ]);
        $pages = $api['query']['pages'] ?? [];
        $page = reset($pages);
        $revisions = $page['revisions'] ?? [];

        $items = [];
        foreach ($revisions as $article) {
            $title = $this->getRevisionText($article);
            $items[] = [
                'title' => $title,
                'description' => $title . ' é um artigo de destaque na Wikipédia!' . "\n\n" . 'Isso significa que foi identificado como um dos melhores artigos produzidos pela comunidade da Wikipédia.' . "\n\n" . 'O que achou? Ainda tem como melhorar?' . "\n\n" . '#wikipedia #ptwikipedia #ptwiki #conhecimentolivre #artigodedestaque',
                'instagram' => $this->findImage(rawurlencode($title)),
                'link' => $this->resolveRedirect('https://pt.wikipedia.org/wiki/' . rawurlencode($title)),
                'timestamp' => date('D, d M Y H:i:s O', strtotime($article['timestamp'])),
                'guid' => $article['revid'],
            ];
        }

        return $items;
    }

    /**
     * Build Sabia Que feed items.
     *
     * @return array
     */
    private function buildSqItems()
    {
        $api = $this->see([
            'action' => 'query',
            'prop' => 'revisions',
            'titles' => 'Usuário(a):SabiaQueBot/log',
            'rvprop' => 'timestamp|content|ids',
            'rvslots' => 'main',
            'rvlimit' => '1',
        ]);
        $pages = $api['query']['pages'] ?? [];
        $page = reset($pages);
        $revisions = $page['revisions'] ?? [];

        $items = [];
        foreach ($revisions as $prop) {
            $contentRaw = $this->getRevisionText($prop);
            preg_match_all('/…[^\n]*/', $contentRaw, $content);
            preg_match_all('/https:.*/', $contentRaw, $address);
            preg_match_all('/(?<=wiki\/).*/', $contentRaw, $title);

            if (($title[0][0] ?? '') === 'LZ%20129%20Hindenburg') {
                continue;
            }

            $articleTitle = $title[0][0] ?? '';
            $items[] = [
                'title' => $articleTitle,
                'description' => 'Você sabia que...' . "\n\n" . ($content[0][0] ?? '') . "\n\n" . '#wikipedia #ptwikipedia #ptwiki #conhecimentolivre #wikicuriosidade #sabiaque',
                'instagram' => $this->findImage($articleTitle),
                'link' => $this->resolveRedirect(str_replace('https://pt.wikipedia.org/wiki/', 'https://pt.wikipedia.org/w/index.php?title=', $address[0][0] ?? '')),
                'timestamp' => date('D, d M Y H:i:s O', strtotime($prop['timestamp'])),
                'guid' => $prop['revid'],
            ];
        }

        return $items;
    }

    /**
     * Build Eventos Atuais feed items.
     *
     * @return array
     */
    private function buildEaItems()
    {
        $api = $this->see([
            'action' => 'query',
            'prop' => 'revisions',
            'titles' => 'Usuário(a):EventosAtuaisBot/log',
            'rvprop' => 'timestamp|content|ids',
            'rvslots' => 'main',
            'rvlimit' => '1',
        ]);
        $pages = $api['query']['pages'] ?? [];
        $page = reset($pages);
        $revisions = $page['revisions'] ?? [];

        $items = [];
        foreach ($revisions as $event) {
            $contentRaw = $this->getRevisionText($event);
            $content = preg_replace('/ *<!--(.*?)--> */', '', $contentRaw);
            preg_match_all('/\'\'\'\[\[([^\]\|\#]*)|\[\[([^\|]*)\|\'\'\'/', $content, $title);
            $text = preg_replace('/\'|\[\[[^\|\]]*\||\]|\[\[/', '', $content);

            $mainTitle = $title[1][0] ?? '';
            $items[] = [
                'title' => $mainTitle,
                'description' => $text . "\n\n" . 'Esse é um evento recente ou em curso que está sendo acompanhado por nossas voluntárias e voluntários. Veja mais detalhes no link.' . "\n\n" . '#wikipedia #ptwikipedia #ptwiki #conhecimentolivre #eventosatuais',
                'instagram' => $this->findImage(rawurlencode($mainTitle)),
                'link' => $this->resolveRedirect('https://pt.wikipedia.org/w/index.php?title=' . rawurlencode($mainTitle)),
                'timestamp' => date('D, d M Y H:i:s O', strtotime($event['timestamp'])),
                'guid' => $event['revid'],
            ];
        }

        return $items;
    }

    /**
     * Build weekly most visited item (Sunday only).
     *
     * @return array
     */
    private function buildMvItems()
    {
        if (date('N') != 7) {
            return [];
        }

        $list = [];
        for ($i = -1; $i > -8; $i--) {
            $day = date('Y/m/d', strtotime($i . ' day'));
            $jsonAll = @file_get_contents('https://wikimedia.org/api/rest_v1/metrics/pageviews/top/pt.wikipedia.org/all-access/' . $day);
            $apiAll = json_decode($jsonAll, true)['items'][0]['articles'] ?? [];

            foreach ($apiAll as $page) {
                $title = $page['article'];
                if (strpos($title, ':') !== false) {
                    continue;
                }

                if (!isset($list[$title])) {
                    $list[$title] = intval($page['views']);
                } else {
                    $list[$title] += intval($page['views']);
                }
            }
        }

        $fp = [
            'AMBEV',
            'Instagram',
            'Facebook',
            'Estados_Unidos',
            'YouTube',
            'Cleópatra',
            'Canal_Brasil',
            'Sony_Channel',
            'XXx',
            'ChatGPT',
            'Porno_Graffitti',
        ];
        foreach ($fp as $key) {
            unset($list[$key]);
        }

        arsort($list);
        $topArticles = array_slice($list, 0, 3, true);

        $firstKey = key($topArticles);
        $firstValue = number_format(current($topArticles), 0, ',', '.');
        next($topArticles);

        $secondKey = key($topArticles);
        $secondValue = number_format(current($topArticles), 0, ',', '.');
        next($topArticles);

        $thirdKey = key($topArticles);
        $thirdValue = number_format(current($topArticles), 0, ',', '.');

        $firstDisplay = str_replace('_', ' ', $firstKey);
        $secondDisplay = str_replace('_', ' ', $secondKey);
        $thirdDisplay = str_replace('_', ' ', $thirdKey);

        return [[
            'title' => $firstKey,
            'description' => $firstDisplay . ' foi o artigo mais visto na Wikipédia em português na semana passada. Foi visto ' . $firstValue . ' vezes.' . "\n" . 'Outros artigos de destaque da semana foram: ' . $secondDisplay . ' (' . $secondValue . ') e ' . $thirdDisplay . ' (' . $thirdValue . ')' . "\n\n" . '#wikipedia #maisvistos #conhecimentolivre',
            'instagram' => $this->findImage(rawurlencode($firstDisplay))
                ?? $this->findImage(rawurlencode($secondDisplay))
                ?? $this->findImage(rawurlencode($thirdDisplay)),
            'link' => $this->resolveRedirect('https://pt.wikipedia.org/w/index.php?title=' . rawurlencode($firstDisplay)),
            'timestamp' => date('D, d M Y H:i:s O', strtotime('midnight')),
            'guid' => strtotime('midnight') + 2,
        ]];
    }

    /**
     * Render one RSS item block.
     *
     * @param array $item
     * @param bool $isMvGuid
     * @return string
     */
    private function renderItem(array $item, $isMvGuid = false)
    {
        $guid = $isMvGuid
            ? 'https://pt.wikipedia.org/w/index.php?title=' . $item['guid']
            : 'https://pt.wikipedia.org/w/index.php?diff=' . $item['guid'];

        $xml = "\n  <item>";
        $xml .= "\n    <title>" . $item['title'] . '</title>';
        $xml .= "\n    <link>" . $item['link'] . '</link>';
        $xml .= "\n    <pubDate>" . $item['timestamp'] . '</pubDate>';
        $xml .= "\n    <guid>" . $guid . '</guid>';
        $xml .= "\n    <description>" . $item['description'] . '</description>';

        if (!is_null($item['instagram'])) {
            $xml .= "\n    <instagram>" . $item['instagram']['image_about'] . '</instagram>';
            $xml .= "\n    <enclosure url=\"" . $item['instagram']['image_url'] . "\" length=\"" . $item['instagram']['image_lenght'] . "\" type=\"" . $item['instagram']['image_type'] . "\" />";
        }

        $xml .= "\n  </item>";
        return $xml;
    }

    /**
     * Build complete RSS XML.
     *
     * @return string
     */
    public function buildRss()
    {
        $ea = $this->buildEaItems();
        $ead = $this->buildEadItems();
        $sq = $this->buildSqItems();
        $mv = $this->buildMvItems();

        $xml = '<?xml version="1.0" encoding="UTF-8" ?>';
        $xml .= "\n<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">";
        $xml .= "\n<channel>";
        $xml .= "\n  <atom:link href=\"https://alberobot.toolforge.org/rss.php\" rel=\"self\" type=\"application/rss+xml\" />";
        $xml .= "\n  <title>WikiPT</title>";
        $xml .= "\n  <link>https://pt.wikipedia.org/</link>";
        $xml .= "\n  <description>Wikipédia em português</description>";

        foreach ($ea as $item) {
            $xml .= $this->renderItem($item, false);
        }
        foreach ($ead as $item) {
            $xml .= $this->renderItem($item, false);
        }
        foreach ($sq as $item) {
            $xml .= $this->renderItem($item, false);
        }
        foreach ($mv as $item) {
            $xml .= $this->renderItem($item, true);
        }

        $xml .= "\n</channel>";
        $xml .= "\n</rss>";

        return $xml;
    }
}

header('Content-type: application/xml');

try {
    $feed = new RssFeed('https://pt.wikipedia.org/w/api.php');
    echo $feed->buildRss();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<?xml version="1.0" encoding="UTF-8" ?>';
    echo "\n<rss version=\"2.0\">";
    echo "\n<channel>";
    echo "\n  <title>WikiPT</title>";
    echo "\n  <description>Erro ao gerar feed</description>";
    echo "\n  <item>";
    echo "\n    <title>Erro interno</title>";
    echo "\n    <description>" . htmlspecialchars($e->getMessage(), ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</description>";
    echo "\n  </item>";
    echo "\n</channel>";
    echo "\n</rss>";
}
