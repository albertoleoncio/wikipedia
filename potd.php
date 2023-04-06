<pre><?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';
require_once './tpar/twitteroauth/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;

class Potd extends WikiAphpiLogged
{

    /**
     * Verifica se a última POTD postada corresponde a POTD atual da Wikipédia.
     * @return string|false Título da imagem do dia pendente (Ex: 1 de janeiro de 2020) ou falso
     */
    private function verifyPendingPost()
    {
        $logPage = 'User:AlbeROBOT/POTD';

        $atual_params = [
            'action'  => 'expandtemplates',
            'format'  => 'php',
            'prop'    => 'wikitext',
            'text'    => "{{CURRENTDAY}} de {{CURRENTMONTHNAME}} de {{CURRENTYEAR}}"
        ];
        $atual = $this->see($atual_params)['expandtemplates']['wikitext'];
        $ultimo = $this->get($logPage);
        if ($atual == $ultimo) {
            return false;
        }
        $this->edit($atual, null, true, "bot: Atualizando POTD", $logPage);
        return $atual;
    }

    /**
     * Busca o conteúdo textual da página de "Imagem em destaque" da Wikipédia
     * e extrai o nome do arquivo de imagem a partir do texto.
     * @param string $dayTitle Título da imagem do dia (Ex: 1 de janeiro de 2020).
     * @return string O nome do arquivo de imagem.
     */
    private function fetchFilename($dayTitle)
    {
        $text_params = [
            'action' => 'parse',
            'format' => 'php',
            'page'   => 'Wikipédia:Imagem_em_destaque/' . $dayTitle
        ];
        $content = $this->see($text_params);
        $text = $content["parse"]["text"]["*"] ?? false;
        if (!$text) {
            throw new ContentRetrievalException($content);
        }
        preg_match_all('/(?<=<a href="\/wiki\/Ficheiro:)[^"]*/', $text, $regex);
        $filename = urldecode($regex["0"]["0"]);
        if (!$filename) {
            throw new ContentRetrievalException($regex);
        }
        return $filename;
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
        $api = $this->see($api_params);
        $meta = $api["query"]["pages"]["-1"]["imageinfo"]["0"]["extmetadata"] ?? false;
        if (!$meta) {
            throw new ContentRetrievalException($filename);
        }
        return $meta;
    }

    /**
     * Recupera um arquivo de acordo com seu nome na wiki e salva localmente.
     * @param string $filename O nome do arquivo a ser baixado e salvo.
     * @return string O endereço local do arquivo.
     * @throws Exception Se o arquivo não puder ser salvo.
    */
    private function fetchAndSaveFile($filename)
    {
        preg_match_all('/(?<=\.)\w*?$/', $filename, $extension);
        $file = file_get_contents("https://pt.wikipedia.org/wiki/Especial:Redirecionar/file/$filename?width=1000");
        $path = './potd.'.$extension['0']['0'];
        if (file_put_contents($path, $file) === false) {
            throw new Exception("Não foi possível salvar o arquivo");
        }
        return $path;
    }

    /**
     * Prepara a imagem para ser postada no Twitter
     * @param string $pendingPost O título do post que contém a imagem
     * @return array Um array contendo o endereço local do arquivo da imagem e seus metadados
     */
    private function prepareImage($pendingPost)
    {
        $imageFilename = $this->fetchFilename($pendingPost);
        $imagePath = $this->fetchAndSaveFile($imageFilename);
        $imageMeta = $this->fetchImageMeta($imageFilename);
        return [$imagePath, $imageMeta];
    }

    /**
     * Monta os textos para serem postados no Twitter
     * @param string $pendingPost O título do post que contém a imagem
     * @param array $imageMeta Os metadados da imagem
     * @return array Um array contendo o status e a resposta para o tweet
     */
    private function composeTweets($pendingPost, $imageMeta)
    {
        $artistName = strip_tags($imageMeta['Artist']['value']);
        $licenseShortName = strip_tags($imageMeta['LicenseShortName']['value']);
        $licenseUrl = strip_tags($imageMeta['LicenseUrl']['value'] ?? '');

        $twitterStatus = "Imagem do dia em $pendingPost. Veja mais informações em https://pt.wikipedia.org/wiki/WP:Imagem_em_destaque/" . rawurlencode($pendingPost);
        $twitterReply = "Autor: $artistName (Licença: $licenseShortName - $licenseUrl)";

        return [$twitterStatus, $twitterReply];
    }

    /**
     * Posta a imagem no Twitter
     * @param array $tokens Um array contendo as chaves e tokens de acesso à API do Twitter
     * @param string $twitterStatus O status do tweet a ser postado
     * @param string $twitterReply A resposta para o tweet a ser postado
     * @param string $imagePath O endereço local do arquivo da imagem a ser postada
     * @return array Um array contendo o ID do status e o ID da resposta para o tweet
     */
    private function postToTwitter($tokens, $twitterStatus, $twitterReply, $imagePath)
    {
        $twitter = new TwitterOAuth(...$tokens);

        $media  = $twitter->upload('media/upload', ['media' => $imagePath]);
        $status = $twitter->post('statuses/update', ['status' => $twitterStatus, 'media_ids' => $media->media_id_string]);
        $reply  = $twitter->post('statuses/update', ['status' => $twitterReply, 'in_reply_to_status_id' => $status->id]);

        return [$status->id, $reply->id];
    }

    /**
     * Busca se há postagem pendente e publica sua imagem relacionada no Twitter com informações sobre o autor e a licença.
     * @param array $tokens Um array com os tokens da API do Twitter.
     * @return array|null Um array com os IDs do tweet criado e sua resposta, ou null se não houver postagem pendente.
     */
    public function run($tokens)
    {
        $pendingPost = $this->verifyPendingPost();
        if (!$pendingPost) {
            return null;
        }

        list($imagePath, $imageMeta) = $this->prepareImage($pendingPost);
        list($twitterStatus, $twitterReply) = $this->composeTweets($pendingPost, $imageMeta);
        $twitter = $this->postToTwitter($tokens, $twitterStatus, $twitterReply, $imagePath);

        unlink($imagePath);
        return $twitter;
    }
}

//Executa script
$tokens = [
    $twitter_consumer_key,
    $twitter_consumer_secret,
    $twitter_access_token,
    $twitter_access_token_secret
];
$api = new Potd('https://pt.wikipedia.org/w/api.php', $username, $password);
print_r($api->run($tokens));
echo "\nExecução finalizada";