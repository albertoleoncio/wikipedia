<pre><?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';
require_once './tpar/twitteroauth/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;

class Ead extends WikiAphpiLogged
{

    /**
    * Get the current featured article on the Portuguese Wikipedia.
    * @throws Exception if the API response does not contain the expected data
    * @return string the title of the current featured article
    */
    private function getCurrentArticle()
    {
        $queryParams = [
            'action' => 'expandtemplates',
            'format' => 'php',
            'text' => '{{Em destaque/listagem|{{Em destaque/contador}}}}',
            'prop' => 'wikitext'
        ];
        $response = $this->see($queryParams);
        $currentArticle = $response['expandtemplates']['wikitext'] ?? false;
        if (!$currentArticle) {
            throw new ContentRetrievalException($response);
        }
        return $currentArticle;
    }

    /**
     * Retrieves the content of a log page containing the title of the last article published.
     * @param string $logPage The title of the log page to retrieve.
     * @return string The content of the log page.
     */
    private function getLastPublishedArticle($logPage)
    {
        return $this->get($logPage);
    }

    /**
     * Posts a tweet announcing that the given article is a featured article on Wikipedia.
     * @param string $currentArticle The title of the featured article.
     * @param array $tokens An array containing the Twitter API consumer key, consumer secret, access token, and access token secret.
     * @return int The ID of the posted tweet on success.
     */
    private function doTweet($currentArticle, $tokens)
    {
        $twitter_status  = "{$currentArticle} é um artigo de destaque na Wikipédia!\n\n";
        $twitter_status .= "Isso significa que ele foi identificado como um dos melhores artigos produzidos pela comunidade da Wikipédia.\n\n";
        $twitter_status .= "O que achou? Ainda tem como melhorar?\n";
        $twitter_status .= "https://pt.wikipedia.org/wiki/".rawurlencode($currentArticle);
        $twitter_conn = new TwitterOAuth(...$tokens);
        $post_tweets = $twitter_conn->post("statuses/update", ["status" => $twitter_status]);
        return $post_tweets->id;
    }

    /**
     * Update the log page with the current featured article.
     * @param string $currentArticle The title of the current featured article.
     * @param string $logPage The title of the log page.
     * @return bool Returns true on success or false on failure.
     */
    private function doLog($currentArticle, $logPage)
    {
        return $this->edit($currentArticle, 0, true, "bot: Atualizando EAD", $logPage);
    }

    /**
     * Runs the script by retrieving the current featured article from Wikipedia,
     * checking whether it is different from the last published one by reading
     * a log page, and publishing a tweet and updating the log page if necessary.
     * @param array $tokens An array containing the Twitter API access token, access token secret,
     *                      consumer key, and consumer secret, in this order.
     * @return array An array containing the ID of the tweet posted and the title of the featured article
     *               (or the log page if the article has not changed since the last run).
     */
    public function run($tokens)
    {
        $logPage = 'Usuário(a):AlbeROBOT/EAD';
        $lastArticle = $this->getLastPublishedArticle($logPage);
        $currentArticle = $this->getCurrentArticle();

        if ($currentArticle == $lastArticle) {
            return [$currentArticle, $lastArticle];
        } else {
            return [
                $this->doLog($currentArticle, $logPage),
                $this->doTweet($currentArticle, $tokens)
            ];
        }

    }
}

//Run script
$tokens = [
    $twitter_consumer_key,
    $twitter_consumer_secret,
    $twitter_access_token,
    $twitter_access_token_secret
];
$api = new Ead('https://pt.wikipedia.org/w/api.php', $username, $password);
print_r($api->run($tokens));