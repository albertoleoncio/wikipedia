<?php
header('Content-type: application/xml');

/////////
//
// IMAGENS
//
/////////

function busca_imagem ($article)
{
    //Busca imagem
    $address = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&redirects=1&format=php&prop=pageimages&piprop=name&titles=".$article);
    $address = end(unserialize($address)["query"]["pages"]);

    //Retorna caso imagem exista imagem principal no artigo
    if (!isset($address['pageimage'])) return null;

    //Busca metadados da imagem
    $meta = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&prop=imageinfo&iiprop=extmetadata&titles=Ficheiro:".rawurlencode($address['pageimage']));
    $meta = unserialize($meta)["query"]["pages"];

    //Retorna caso ID da imagem é diferente de -1, o que indica que se trata de URC
    if (!isset($meta["-1"])) return null;

    //Isola array de metadados da imagem
    $meta = $meta["-1"]["imageinfo"]["0"]["extmetadata"];

    //Extrai dados da imagem
    $ch = curl_init('https://pt.wikipedia.org/wiki/Especial:Redirecionar/file/'.$address['pageimage'].'?width=1000');
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $response   = curl_exec($ch);
    $location   = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $size           = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    $type           = curl_getinfo($ch, CURLINFO_CONTENT_TYPE );
    curl_close($ch);

    //Monta resposta
    if (!isset($meta["Artist"]["value"])) $meta["Artist"]["value"] = "Desconhecido";
    return array(
        "image_about"   => "Para saber mais sobre o tema, basta acessar o link na bio e o artigo estará na nossa página principal!\n\n\n\nSobre a imagem:\nAutor: ".trim(strip_tags($meta["Artist"]["value"])).".\nLicença ".trim(strip_tags($meta["LicenseShortName"]["value"])).".\nPara mais informações sobre essa imagem, entre no endereço da bio e pesquise por Imagem:".$address['pageimage'],
        "image_url"         => $location,
        "image_lenght"  => $size,
        "image_type"        => $type
    );
}

/////////
//
// EAD
//
/////////

//Gera lista recente via API
$ead_api = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&prop=revisions&titles=Usu%C3%A1rio(a)%3AAlbeROBOT%2FEAD&rvprop=timestamp%7Ccontent%7Cids&rvslots=main&rvlimit=1");
$ead_api = unserialize($ead_api)["query"]["pages"]["6375156"]["revisions"];

//Processa cada item
foreach ($ead_api as $article) {
    $ead[] = array(
        "title"         => $article["slots"]["main"]["*"],
        "description"   => $article["slots"]["main"]["*"]." é um artigo de destaque na Wikipédia!\n\nIsso significa que foi identificado como um dos melhores artigos produzidos pela comunidade da Wikipédia.\n\nO que achou? Ainda tem como melhorar?\n\n#wikipedia #ptwikipedia #ptwiki #conhecimentolivre #artigodedestaque",
        "instagram" => busca_imagem(rawurlencode($article["slots"]["main"]["*"])),
        "link"          => "https://pt.wikipedia.org/w/index.php?title=".rawurlencode($article["slots"]["main"]["*"]),
        "timestamp"     => date('D, d M Y H:i:s O',strtotime($article["timestamp"])),
        "guid"          => $article["revid"]
    );
}


/////////
//
// SABIA QUE
//
/////////

$sq_api = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&prop=revisions&titles=Usu%C3%A1rio(a)%3ASabiaQueBot%2Flog&rvprop=timestamp%7Ccontent%7Cids&rvslots=main&rvlimit=1");
$sq_api = unserialize($sq_api)["query"]["pages"]["6731563"]["revisions"];
foreach ($sq_api as $prop) {
    preg_match_all('/…[^\n]*/', $prop["slots"]["main"]["*"], $content);
    preg_match_all('/https:.*/', $prop["slots"]["main"]["*"], $address);
    preg_match_all('/(?<=wiki\/).*/', $prop["slots"]["main"]["*"], $title);
    if ($title["0"]["0"] == 'LZ%20129%20Hindenburg') continue;
    $sq[] = array(
        "title"         => $title["0"]["0"],
        "description"   => "Você sabia que...\n\n".$content["0"]["0"]."\n\n#wikipedia #ptwikipedia #ptwiki #conhecimentolivre #wikicuriosidade #sabiaque",
        "instagram" => busca_imagem($title["0"]["0"]),
        "link"          => str_replace('https://pt.wikipedia.org/wiki/', 'https://pt.wikipedia.org/w/index.php?title=', $address["0"]["0"]),
        "timestamp"     => date('D, d M Y H:i:s O',strtotime($prop["timestamp"])),
        "guid"          => $prop["revid"]
    );
}


/////////
//
// EVENTOS ATUAIS
//
/////////

$ea_api = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&prop=revisions&titles=Usu%C3%A1rio(a)%3AEventosAtuaisBot%2Flog&rvprop=timestamp%7Ccontent%7Cids&rvslots=main&rvlimit=1");
$ea_api = unserialize($ea_api)["query"]["pages"]["6740244"]["revisions"];
foreach ($ea_api as $event) {
    $content = preg_replace('/ *<!--(.*?)--> */', '', $ea_api["0"]["slots"]["main"]["*"]);
    preg_match_all('/\'\'\'\[\[([^\]\|\#]*)|\[\[([^\|]*)\|\'\'\'/', $content, $title);
    $text = preg_replace('/\'|\[\[[^\|\]]*\||\]|\[\[/', '', $content);
    $ea[] = array(
        "title"         => $title["1"]["0"],
        "description"   => $text."\n\nEsse é um evento recente ou em curso que está sendo acompanhado por nossas voluntárias e voluntários. Veja mais detalhes no link.\n\n#wikipedia #ptwikipedia #ptwiki #conhecimentolivre #eventosatuais",
        "instagram" => busca_imagem(rawurlencode($title["1"]["0"])),
        "link"          => "https://pt.wikipedia.org/w/index.php?title=".rawurlencode($title["1"]["0"]),
        "timestamp"     => date('D, d M Y H:i:s O',strtotime($event["timestamp"])),
        "guid"          => $event["revid"]
    );
}

/////////
//
// MAIS VISITADO
//
/////////

if (date("N") == 7) {

    // Get list from API
    $list = [];
    for ($i=-1; $i > -8 ; $i--) { 
        $day = date("Y/m/d", strtotime("$i day"));
        $json_all = file_get_contents("https://wikimedia.org/api/rest_v1/metrics/pageviews/top/pt.wikipedia.org/all-access/$day");
        $api_all = json_decode($json_all, true)["items"]["0"]["articles"];

        foreach ($api_all as $page) {
            $title = $page['article'];
            if (strpos($title, ":") !== false) {
                continue;
            }
            if (!isset($list[$title])) {
                $list[$title] = intval($page['views']);
            } else {
                $list[$title] += intval($page['views']);
            }
        }
    }

    //Remove false positives
    $fp = [
        "AMBEV",
        "Instagram",
        "Facebook",
        "Estados_Unidos",
        "YouTube",
        "Cleópatra",
        "Canal_Brasil",
        "Sony_Channel",
        "XXx",
        "ChatGPT",
    ];
    foreach ($fp as $key) {
        unset($list[$key]);
    }

    //Get values and construct the output
    arsort($list);

  $top_articles = array_slice($list, 0, 3, true);

  $first_key = key($top_articles);
  $first_value = number_format(current($top_articles), 0, ',', '.');
  next($top_articles);

  $second_key = key($top_articles);
  $second_value = number_format(current($top_articles), 0, ',', '.');
  next($top_articles);

  $third_key = key($top_articles);
  $third_value = number_format(current($top_articles), 0, ',', '.');

  $first_key_display = str_replace("_", " ", $first_key);
  $second_key_display = str_replace("_", " ", $second_key);
  $third_key_display = str_replace("_", " ", $third_key);

  $mv[] = array(
    "title"         => $first_key,
    "description"   => "$first_key_display foi o artigo mais visto na Wikipédia em português na semana passada. Foi visto $first_value vezes.\nOutros artigos de destaque da semana foram: $second_key_display ($second_value) e $third_key_display ($third_value)\n\n#wikipedia #maisvistos #conhecimentolivre",
    "instagram"     =>  busca_imagem(rawurlencode($first_key_display)) ?? 
                        busca_imagem(rawurlencode($second_key_display)) ?? 
                        busca_imagem(rawurlencode($third_key_display)),
    "link"          => "https://pt.wikipedia.org/w/index.php?title=".rawurlencode($first_key_display),
    "timestamp"     => date('D, d M Y H:i:s O',strtotime("midnight")),
    "guid"          => strtotime("midnight")+1
    );
}

//print_r($ead);
//print_r($sq);
?>
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <atom:link href="https://alberobot.toolforge.org/rss.php" rel="self" type="application/rss+xml" />
  <title>WikiPT</title>
  <link>https://pt.wikipedia.org/</link>
  <description>Wikipédia em português</description><?php

  foreach ($ea as $ea_item) {
    echo("\n  <item>");
    echo("\n    <title>".$ea_item["title"]."</title>");
    echo("\n    <link>".$ea_item["link"]."</link>");
    echo("\n    <pubDate>".$ea_item["timestamp"]."</pubDate>");
    echo("\n    <guid>https://pt.wikipedia.org/w/index.php?diff=".$ea_item["guid"]."</guid>");
    echo("\n    <description>".$ea_item["description"]."</description>");
    if (!is_null($ea_item["instagram"])) {
        echo("\n    <instagram>".$ea_item["instagram"]["image_about"]."</instagram>");
        echo("\n    <enclosure url=\"".$ea_item["instagram"]["image_url"]."\" length=\"".$ea_item["instagram"]["image_lenght"]."\" type=\"".$ea_item["instagram"]["image_type"]."\" />");
    }
    echo("\n  </item>");
  }

  foreach ($ead as $ead_item) {
    echo("\n  <item>");
    echo("\n    <title>".$ead_item["title"]."</title>");
    echo("\n    <link>".$ead_item["link"]."</link>");
    echo("\n    <pubDate>".$ead_item["timestamp"]."</pubDate>");
    echo("\n    <guid>https://pt.wikipedia.org/w/index.php?diff=".$ead_item["guid"]."</guid>");
    echo("\n    <description>".$ead_item["description"]."</description>");
    if (!is_null($ead_item["instagram"])) {
        echo("\n    <instagram>".$ead_item["instagram"]["image_about"]."</instagram>");
        echo("\n    <enclosure url=\"".$ead_item["instagram"]["image_url"]."\" length=\"".$ead_item["instagram"]["image_lenght"]."\" type=\"".$ead_item["instagram"]["image_type"]."\" />");
    }
    echo("\n  </item>");
  }

  foreach ($sq as $sq_item) {
    echo("\n  <item>");
    echo("\n    <title>".$sq_item["title"]."</title>");
    echo("\n    <link>".$sq_item["link"]."</link>");
    echo("\n    <pubDate>".$sq_item["timestamp"]."</pubDate>");
    echo("\n    <guid>https://pt.wikipedia.org/w/index.php?diff=".$sq_item["guid"]."</guid>");
    echo("\n    <description>".$sq_item["description"]."</description>");
    if (!is_null($sq_item["instagram"])) {
        echo("\n    <instagram>".$sq_item["instagram"]["image_about"]."</instagram>");
        echo("\n    <enclosure url=\"".$sq_item["instagram"]["image_url"]."\" length=\"".$sq_item["instagram"]["image_lenght"]."\" type=\"".$sq_item["instagram"]["image_type"]."\" />");
    }
    echo("\n  </item>");
  }

  foreach ($mv ?? [] as $mv_item) {
    echo("\n  <item>");
    echo("\n    <title>".$mv_item["title"]."</title>");
    echo("\n    <link>".$mv_item["link"]."</link>");
    echo("\n    <pubDate>".$mv_item["timestamp"]."</pubDate>");
    echo("\n    <guid>https://pt.wikipedia.org/w/index.php?title=".$mv_item["guid"]."</guid>");
    echo("\n    <description>".$mv_item["description"]."</description>");
    if (!is_null($mv_item["instagram"])) {
        echo("\n    <instagram>".$mv_item["instagram"]["image_about"]."</instagram>");
        echo("\n    <enclosure url=\"".$mv_item["instagram"]["image_url"]."\" length=\"".$mv_item["instagram"]["image_lenght"]."\" type=\"".$mv_item["instagram"]["image_type"]."\" />");
    }
    echo("\n  </item>");
  }
  ?>
</channel>
</rss>