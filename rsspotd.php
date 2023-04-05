<?php
require_once './bin/globals.php';
header('Content-type: application/xml');

/**
 * A classe PotdRss é responsável por buscar as imagens do dia (POTD) mais recentes que foram
 * publicadas via bot no Twitter, extrair as informações de texto da imagem da página "Imagem
 * em Destaque" da Wikipédia, buscar informações adicionais sobre a imagem, como o nome do
 * arquivo, o tamanho e o tipo do arquivo, e buscar metadados adicionais da imagem através da
 * API do MediaWiki. As informações são devolvidas em um RSS ATOM.
 */
class PotdRss
{

    /**
     * Construtor da classe responsável por realizar chamadas à API do Wikipedia em Português.
     */
    public function __construct()
    {
        $this->url = 'https://pt.wikipedia.org/w/api.php?';
    }

    /**
     * Recupera os dados da imagem do dia (POTD) recentes já publicadas no Twitter
     * @return array contendo os títulos das últimas 5 imagens do dia
     */
    private function fetchPotdData()
    {
        $potd_params = [
            'action'  => 'query',
            'format'  => 'php',
            'prop'    => 'revisions',
            'titles'  => 'Usuário(a):AlbeROBOT/POTD',
            'rvprop'  => 'timestamp|content|ids',
            'rvslots' => 'main',
            'rvlimit' => 5
        ];
        $potd_api = $this->url . http_build_query($potd_params);
        $potd_api = unserialize(file_get_contents($potd_api))["query"]["pages"]["6720820"]["revisions"];
        return $potd_api;
    }

    /**
     * Busca o conteúdo textual da página de "Imagem em destaque" da Wikipédia.
     * @param string $dayTitle Título da imagem do dia (Ex: 1 de janeiro de 2020).
     * @return string O conteúdo textual da imagem.
     */
    private function fetchTextData($dayTitle)
    {
        $text_params = [
            'action' => 'parse',
            'format' => 'php',
            'page'   => 'Wikipédia:Imagem_em_destaque/' . $dayTitle
        ];
        $content = $this->url . http_build_query($text_params);
        $content = unserialize(file_get_contents($content));
        $text = $content["parse"]["text"]["*"] ?? false;
        if (!$text) {
            throw new Exception(print_r($content, true));
        }
        return $text;
    }

    /**
     * Extrai o conteúdo do atributo "alt" de uma tag de imagem no texto fornecido.
     * @param string $text O texto contendo a tag de imagem da qual deseja-se extrair o conteúdo.
     * @return string O conteúdo do atributo "alt" da tag de imagem.
     */
    private function extractContent($text)
    {
        preg_match_all('/(?<=<img alt=")[^"]*/', $text, $content);
        $alt = $content["0"]["0"] ?? false;
        if (!$alt) {
            throw new Exception(print_r($content, true));
        }
        return $alt;

    }

    /**
     * Extrai o nome do arquivo de imagem a partir do texto.
     * @param string $text O texto que contém a imagem.
     * @return string O nome do arquivo de imagem.
     */
    private function extractFilename($text)
    {
        preg_match_all('/(?<=<a href="\/wiki\/Ficheiro:)[^"]*/', $text, $content);
        $filename = urldecode($content["0"]["0"]);
        if (!$filename) {
            throw new Exception(print_r($content, true));
        }
        return $filename;
    }

    /**
     * Busca os dados de uma imagem a partir de seu endereço.
     * @param type string $filename O nome do arquivo de imagem.
     * @return array Retorna um array com os seguintes elementos:
     ** string $location: O endereço final da imagem após redirecionamentos.
     ** int $size: O tamanho do arquivo em bytes.
     ** string $type: O tipo do conteúdo da imagem (MIME type).
     */
    private function fetchFileInfo($filename)
    {
        $ch = curl_init("https://pt.wikipedia.org/wiki/Especial:Redirecionar/file/$filename?width=1000");
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        $location = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE );
        curl_close($ch);
        return array($location, $size, $type);
    }

    /**
     * Busca os metadados de uma imagem através da API do MediaWiki.
     * @param string $filename O nome do arquivo de imagem.
     * @return array Uma matriz contendo os metadados da imagem.
     */
    private function fetchImageMeta($filename)
    {
        $api_params = [
            'action'  => 'query',
            'format'  => 'php',
            'prop'    => 'imageinfo',
            'iiprop'  => 'extmetadata',
            'titles'  => 'Ficheiro:' . $filename
        ];
        $api = $this->url . http_build_query($api_params);
        $api = unserialize(file_get_contents($api));
        $meta = $api["query"]["pages"]["-1"]["imageinfo"]["0"]["extmetadata"] ?? false;
        if (!$meta) {
            throw new Exception(print_r($filename, true));
        }
        return $meta;
    }

    /**
     * Constrói a descrição de um item RSS a partir do título da imagem.
     * @param string $dayTitle Título da imagem.
     * @param array $imageInfo Contendo as informações da imagem, incluindo o texto, nome do arquivo e metadados.
     * @return string Descrição da imagem no formato de texto
     */
    private function buildDescription($dayTitle, $imageInfo)
    {

        return "Imagem do dia em {$dayTitle}: {$this->extractContent($imageInfo['text'])}\nAutor: "
            . trim(strip_tags($imageInfo['meta']["Artist"]["value"]))
            . " (Licença: "
            . strip_tags($imageInfo['meta']["LicenseShortName"]["value"])
            . " - "
            . strip_tags($imageInfo['meta']["LicenseUrl"]["value"])
            . ")\nVeja mais informações no link.\n\n#wikipedia #ptwikipedia #ptwiki #conhecimentolivre #fotododia #imagemdodia #wikicommons";
    }

    /**
     * Cria um item do RSS a partir das informações da Imagem do Dia (POTD).
     * @param array $image Array com informações do log da Imagem do Dia
     * @return array Array com as informações formatadas para o RSS.
     */
    private function buildRssItem($thisDay)
    {
        $dayTitle = $thisDay["slots"]["main"]["*"];
        $imageInfo = $this->fetchImageInfo($dayTitle);
        $description = $this->buildDescription($dayTitle, $imageInfo);
        $link = "https://pt.wikipedia.org/wiki/WP:Imagem_em_destaque/" . rawurlencode($dayTitle);
        $guid = $thisDay["revid"];
        $timestamp = date('D, d M Y H:i:s O', strtotime($thisDay["timestamp"]));
        $fileInfo = $this->fetchFileInfo($imageInfo['filename']);

        return [
            'title'         => $dayTitle,
            'description'   => $description,
            'link'          => $link,
            'guid'          => $guid,
            'timestamp'     => $timestamp,
            'image_url'     => $fileInfo['0'],
            'image_length'  => $fileInfo['1'],
            'image_type'    => $fileInfo['2']
        ];
    }

    /**
     * Retorna informações sobre a imagem a partir do título.
     * @param string $dayTitle Título da imagem do dia (Ex: 1 de janeiro de 2020).
     * @return array Contendo as informações da imagem, incluindo o texto, nome do arquivo e metadados.
     */
    private function fetchImageInfo($dayTitle)
    {
        $text = $this->fetchTextData($dayTitle);
        $filename = $this->extractFilename($text);
        $meta = $this->fetchImageMeta($filename);

        return [
            'text' => $text,
            'filename' => $filename,
            'meta' => $meta
        ];
    }


    /**
     * Constrói um feed RSS a partir dos dados fornecidos.
     * @param array $potd Dados da imagem do dia.
     * @return string O feed RSS gerado.
     */
    private function buildRss($potd)
    {
        $rss = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" ?><rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"></rss>');
        $rss->addChild('channel');
        $rss->channel->addChild('title', 'WikiPT - POTD');
        $rss->channel->addChild('link', 'https://pt.wikipedia.org/');
        $rss->channel->addChild('description', 'Wikipédia em português');
        $atom_link = $rss->channel->addChild('atom:atom:link');
        $atom_link->addAttribute('href', 'https://alberobot.toolforge.org/rsspotd.php');
        $atom_link->addAttribute('rel', 'self');
        $atom_link->addAttribute('type', 'application/rss+xml');

        foreach ($potd as $potd_item) {
            $item = $rss->channel->addChild('item');
            $item->addChild('title', htmlspecialchars($potd_item["title"]));
            $item->addChild('link', htmlspecialchars($potd_item["link"]));
            $item->addChild('pubDate', htmlspecialchars($potd_item["timestamp"]));
            $item->addChild('guid', 'https://pt.wikipedia.org/w/index.php?diff=' . $potd_item["guid"]);
            $item->addChild('description', htmlspecialchars($potd_item["description"]));
            $enclosure = $item->addChild('enclosure');
            $enclosure->addAttribute('url', htmlspecialchars($potd_item["image_url"]));
            $enclosure->addAttribute('length', htmlspecialchars($potd_item["image_length"]));
            $enclosure->addAttribute('type', htmlspecialchars($potd_item["image_type"]));
        }

        return $rss->asXML();
    }


    /**
    * Executa a geração do feed RSS com as informações do "Imagem do Dia" da Wikipédia em português.
    * @return string Retorna o conteúdo do feed RSS gerado.
    */
    public function run()
    {
        $potd = $this->fetchPotdData();
        $items = [];

        foreach ($potd as $thisDay) {
            $item = $this->buildRssItem($thisDay);
            $items[] = $item;
        }

        return $this->buildRss($items);
    }
}

//Executa script
$potdRss = new PotdRss();
echo $potdRss->run();
