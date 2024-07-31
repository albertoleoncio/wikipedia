<pre><?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';
require_once "tpar/twitteroauth/autoload.php";
use Abraham\TwitterOAuth\TwitterOAuth;
date_default_timezone_set('UTC');

class SabiaQue extends WikiAphpiLogged
{

    /**
     * Purges the "Wikipédia:Sabia que/Frequência" page to clear cached data.
     * @throws Exception if the purge API call fails or the page is not purged.
     */
    private function purgeFrequencyPage()
    {
        $purgeParams = [
            'action' => 'purge',
            'format' => 'php',
            'titles' => 'Wikipédia:Sabia que/Frequência',
            'forcerecursivelinkupdate' => '1'
        ];

        // Execute API
        $purge = $this->do($purgeParams);

        // Check if the page was purged successfully
        if (!isset($purge["purge"]['0']["purged"])) {
            throw new ContentRetrievalException($purge);
        }
    }

    /**
     * Retrieve the current value of the "Wikipédia:Sabia que/Frequência" template, which determines
     * the minimum interval (in seconds) between two consecutive "Sabia que" facts in the Portuguese
     * Wikipedia main page.
     * @throws InvalidArgumentException If the retrieved value is not numeric or is less than 43200 seconds (12 hours).
     * @return int The value of the "Wikipédia:Sabia que/Frequência" template.
     */
    private function getFrequency()
    {
        $frequencyParams = [
            'action'    => 'expandtemplates',
            'format'    => 'php',
            'prop'      => 'wikitext',
            'text'      => '{{Wikipédia:Sabia que/Frequência}}'
        ];
        $frequency = $this->see($frequencyParams)["expandtemplates"]["wikitext"];
        if (!is_numeric($frequency) || $frequency < 43200) {
            throw new InvalidArgumentException("'Wikipédia:Sabia que/Frequência' possui valor não numérico ou menor que 43200. Bloqueio de segurança.");
        }
        return $frequency;
    }

    /**
     * Retrieves the timestamp of the last contribution made by the "SabiaQueBot" user.
     * @return int The timestamp of the last published contribution, in Unix time format.
     */
    private function getLastPublishedTime()
    {
        $lastPublishedParams = [
            'action'    => 'query',
            'format'    => 'php',
            'list'      => 'usercontribs',
            'uclimit'   => '1',
            'ucuser'    => 'SabiaQueBot',
            'ucprop'    => 'timestamp'
        ];
        $lastPublishedTimestamp = $this->see($lastPublishedParams)['query']['usercontribs']['0']['timestamp'];
        return date("U", strtotime($lastPublishedTimestamp));
    }

    /**
     * Check if the deadline for publishing a new Sabia Que fact has been met.
     * @return int Seconds until deadline.
     */
    private function isDeadlineMet()
    {
        $this->purgeFrequencyPage();
        $frequency = $this->getFrequency();
        $lastPublishedTime = $this->getLastPublishedTime();
        $deadline = $lastPublishedTime + $frequency - time();
        if ($deadline < 0) {
            return 0;
        } else {
            return $deadline;
        }
    }

    /**
     * Retrieves the creator of a Wikipedia article.
     * @param string $article The name of the article to retrieve the creator from.
     * @return string|null The username of the article creator, or null if the creator is a bot, a (b)locked user, or an IP address.
     */
    private function retrieveArticleCreator($article)
    {
        $creatorParams = [
            'action'    => 'query',
            'format'    => 'php',
            'prop'      => 'revisions',
            'titles'    => $article,
            'rvlimit'   => '1',
            'rvprop'    => 'user',
            'rvdir'     => 'newer'
        ];
        $creator = $this->see($creatorParams)['query']['pages'];
        $creator = reset($creator);
        $creator = $creator['revisions']['0']['user'];

        // Check if user is a IP
        if (filter_var($creator, FILTER_VALIDATE_IP)) {
            return null;
        }

        // Check if username begins with "imported>" (imported user)
        if (strpos($creator, "imported>") === 0) {
            return null;
        }

        // Verify if user is not a bot, a blocked or a locked user
        $userParams = [
            'action'    => 'query',
            'format'    => 'php',
            'list'      => 'users',
	        'meta'      => 'globaluserinfo',
            'usprop'    => 'groups|blockinfo',
            'ususers'   => $creator,
            'guiuser'   => $creator
        ];
        $get = $this->see($userParams)['query'];
        $local = $get['users']['0'];
        $global = $get['globaluserinfo'];
        if (
            isset($local['blockid']) ||
            in_array('bot', $local['groups']) ||
            isset($global['locked'])
        ) {
            return null;
        }

        return $creator;
    }

    /**
     * Extracts the nomination text from a proposition using a regular expression.
     * @param string $proposition The proposition to extract the text from.
     * @param string $regex The regular expression pattern to use for matching the text.
     * @return string The extracted nomination text.
     * @throws Exception if the text is not found in the proposition.
     */
    private function regexNewNomination($proposition, $regex)
    {
        // Match the regular expression pattern against the proposition
        preg_match_all($regex, $proposition, $result);

        // Combine the matched text groups into a single string
        $text = $result['1']['0'] ?? '';
        $text .= $result['2']['0'] ?? '';

        // If no text is found, throw an exception
        if (empty($text)) {
            throw new InvalidArgumentException("Texto não encontrado: [{$regex}]");
        }

        // Return the extracted nomination text
        return ltrim($text, "…. ");
    }

    /**
     * Extracts information about a new nomination from the approved's proposition list.
     * @param string $page The page name containing the new nomination.
     * @return array An array containing the following elements:
     *               - string $new The new fact being nominated.
     *               - string $article The Wikipedia article where the new fact refers to.
     *               - string $nominator The user who made the nomination.
     *               - string $proposition The full text of the nomination proposition.
     */
    private function extractNewNominationInfo($page)
    {
        //Get the contents from the first section of the page excluding header
        $proposition = $this->get($page, '1');

        $new = $this->regexNewNomination(
            $proposition,
            '/\| ?texto *= ?([^\n]*)/'
        );
        $article = $this->regexNewNomination(
            $proposition,
            '/\'\'\'\[\[([^\]\|\#]*)|\[\[([^\|\]]*)\|\'\'\'[^\]\']*\'\'\'\]\]/'
        );
        $nominator = $this->regexNewNomination(
            $proposition,
            '/\* \'\'\'Proponente\'\'\' – [^\[]*\[\[[^:]*:([^|]*)/'
        );
        return [$new, $article, $nominator, $proposition];
    }


    /**
     * Compiles a new Sabia Que nomination into an HTML template and returns the old and new versions of the template.
     * @param string $new The new nomination text.
     * @param string $page The page containing the Sabia Que HTML template.
     * @return array An array containing the old and new versions of the Sabia Que HTML template.
     */
    private function compileTemplate($new, $page)
    {
        // Get the current Sabia Que template.
        $html = $this->get($page);

        // Split the template into an array of lines.
        $html = explode("\n…", $html);

        // Add the new Sabia Que nomination text with timestamp to the template.
        array_splice($html, 1, 0, " ".rtrim($new)."<!--".strtolower(utf8_encode(strftime('%B de %Y')))."-->\n");

        // Split the last line of the template at the end tag and get the oldest Sabia Que fact.
        $ultima = explode("<!-- FIM", $html[count($html)-1]);
        $old = ltrim($ultima[0]);

        // Remove the oldest Sabia Que fact from the last line of the template.
        $html[count($html)-1] = "<!-- FIM".$ultima[1];

        // Append the last two lines of the template into one line.
        $html[count($html)-2] = $html[count($html)-2]."\n".$html[count($html)-1];

        // Remove the oldest Sabia Que fact from the template.
        array_pop($html);

        // Join the lines of the template into a single string.
        $html = implode("\n…", $html);

        // Return the old and new versions of the Sabia Que template.
        return [$old, $html];
    }


    /**
     * Compiles the text to be added to the article talk page.
     * @param string $article The name of the article.
     * @param string $new The new Sabia Que nomination text.
     * @return array An array with the compiled text and the name of the talk page.
     */
    private function compileArticleTalk($article, $new)
    {
        // Get the redirect (if any) for the article name
        $redirectParams = [
            'action'    => 'query',
            'format'    => 'php',
            'titles'    => $article,
            'redirects' => 1
        ];
        $page_renamed = $this->see($redirectParams);
        if (isset($page_renamed["query"]["redirects"])) {
            $article = $page_renamed["query"]["redirects"][0]["to"];
        }

        // Get the article talk page
        $timestamp = utf8_encode(strftime('%d de %B de %Y'));
        $page = "Discussão:{$article}";
        if ($this->isPageCreated($page)) {
            $html = $this->get($page, 0);
        } else {
            $html = '';
        }

        // Checks if the template already exists. If yes, insert a new template at the end of the section. If not...
        if (!strpos($html, "SabiaQueDiscussão")) {
            $html .= "\n\n";
            $html .= "{{SabiaQueDiscussão\n";
            $html .= "|data1    = {$timestamp}\n";
            $html .= "|entrada1 = … {$new}\n";
            $html .= "}}";
        } else {

            //From the maximum number (10), check which is the highest number found.
            $n = 10;
            while ($n > 0 && !strpos($html, "entrada{$n}")) {
                $n--;
            }

            //If n = 0, it means that the most recent entry has no number (in this case, the next entry is 2).
            //In other cases, the next entry is the found number +1.
            if ($n == 0) {
                $n = 2;
            } else {
                $n++;
            }

            //Insert the fact in the template
            $newHtml  = "{{SabiaQueDiscussão\n";
            $newHtml .= "|data{$n}    = {$timestamp}\n";
            $newHtml .= "|entrada{$n} = … {$new}";
            $html = str_replace(
                "{{SabiaQueDiscussão",
                $newHtml,
                $html
            );
        }

        return [$html, $page];
    }


    /**
     * Compiles the recent section of the main page with a new nomination.
     * @param string $old The text of the nomination being replaced.
     * @param string $page The title of the main page.
     * @return array An array containing the new text for the recent section and the index of the section in the page's HTML.
     */
    private function compileRecent($old, $page)
    {
        // Get the sections of the main page
        $html = $this->getSections($page);

        // Create a map of the sections, using the month name as the key
        $map = array();
        foreach ($html as $i => $section) {
            preg_match_all('/=* ?([^= ]*?) =*\n/', $section, $monthNameMap);
            if (isset($monthNameMap['1']['0'])) {
                $map[$monthNameMap['1']['0']] = $i;
            }
        }

        // Split the old nomination text from the publication date
        $recente = explode("<!--", $old);

        // Get the name of the month for the nomination
        $monthName = ucfirst(explode(' ', trim($recente['1']))['0']);
        $oldFact = $recente['0'];

        // Check if a section with the month name already exists and update it, or create a new section for the month
        if (array_key_exists($monthName, $map)) {
            $html[$map[$monthName]] = preg_replace(
                '/==\n/',
                "==\n*… {$oldFact}\n",
                $html[$map[$monthName]]
            );
            $section = $map[$monthName];
        } else {
            $html['0'] .= "\n==== {$monthName} ====\n";
            $html['0'] .= "*… {$oldFact}\n";
            $section = 0;
        }

        // Return the new text and the index of the section
        return [$html[$section], $section];
    }



    /**
     * Compiles the content and page name for a congratulatory message to the nominator/creator
     * of a newly approved article to be added to their user talk page.
     * @param string $user The user name.
     * @param string $article The article of the newly approved fact.
     * @param string $new The content of the newly approved fact.
     * @return array An array with the compiled message content and page name.
     */
    private function compileUserTalkPage($user, $article, $new, $creator = false)
    {
        // Define the page name.
        $page = "Usuário Discussão:".$user;

        // Get the redirect (if any) for the user name
        $redirectParams = [
            'action'    => 'query',
            'format'    => 'php',
            'titles'    => $page,
            'redirects' => 1
        ];
        $page_renamed = $this->see($redirectParams);
        if (isset($page_renamed["query"]["redirects"])) {
            $page = $page_renamed["query"]["redirects"][0]["to"];
        }

        // Compile the congratulatory message content.
        $html  = "{{subst:ParabénsSQ\n";
        $html .= "|artigo=''[[{$article}]]''\n";
        $html .= "|data=".utf8_encode(strftime('%d de %B de %Y'))."\n";
        $html .= "|curiosidade=…{$new}\n";
        $html .= "|arquivo=".utf8_encode(strftime('%Y/%m'))."\n";
        if ($creator) {
            $html .= "|criador=sim\n";
        }
        $html .= "}} --~~~~";

        // Return the compiled message content and page name.
        return [$html, $page];
    }

    /**
     * Composes the archive section for the current month with the given proposition.
     * @param string $proposition The proposition to be included in the archive.
     * @return array An array containing the composed HTML and the name of the archive page.
     */
    private function composeArchive($proposition)
    {
        $page = "Wikipédia:Sabia que/Propostas/Arquivo/".utf8_encode(strftime('%Y/%m'));
        $html = "\n\n$proposition{{ADC|sim|".utf8_encode(strftime('%d de %B de %Y'))."|~~~}}";
        return [$html, $page];
    }

    /**
     * Posts a tweet on Twitter using the TwitterOAuth library.
     * @param string $text The text of the tweet.
     * @param string $article The name of the article to include in the tweet.
     * @param array $tokens An array with keys and tokens of the Twitter API
     * @return array An array containing the tweet text and the ID of the posted tweet.
     */
    private function doTweet($text, $article, $tokens)
    {
        if ($article == "LZ 129 Hindenburg") return;
        $tweet  = "Você sabia que...\n\n…";
        $tweet .= preg_replace(
            '/[\[\]\']/',
            '',
            preg_replace(
                '/\[\[[^\|\]]*\|([^\]]*)\]\]/',
                '$1',
                $text
            )
        );
        $tweet .= "\n\nLeia mais na Wikipédia: https://pt.wikipedia.org/wiki/".rawurlencode($article);

        /*$twitter_conn = new TwitterOAuth(...$tokens);
        $post = $twitter_conn->post(
            "statuses/update",
            ["status" => $tweet]
        );*/

        return [$tweet, true /*$post->id*/];
    }


    /**
     * Runs the "Sabia que" publication process.
     * If the deadline has not been met, returns early.
     * This method extracts the new fact information from the "Wikipédia:Sabia que/Propostas/Aprovadas" page,
     * compiles the necessary code for the "Sabia que" template, the article talk page, the recent archive, the nominator's talk page,
     * and the "Sabia que" archive, and edits the respective pages with the compiled code.
     *
     * @var $approvedNominationPage: a string representing the page name where approved nominations are stored
     * @var $templatePage: a string representing the page name where the Sabia que template is located
     * @var $recentPage: a string representing the page name where the recent Sabia que facts are stored after publication at the template
     * @var $logPage: a string representing the page name where the recent Sabia que tweet are stored after publication
     * @var $newFact: a string representing the newly approved Sabia que fact
     * @var $article: a string representing the article name associated with the new Sabia que fact
     * @var $nominator: a string representing the username of the person who nominated the new Sabia que fact
     * @var $nomination: a string representing the entire text of the nomination
     * @var $oldFact: a string representing the old Sabia que fact from the template that will be placed in the recent Sabia que page
     * @var $templateCode: a string representing the updated Sabia que template code with the new fact inserted and old fact removed
     * @var $articleTalkCode: a string representing the code to be inserted into the talk page of the article associated with the new Sabia que fact
     * @var $articleTalkPage: a string representing the talk page of the article associated with the new Sabia que fact
     * @var $recentCode: a int representing the updated code for the recent Sabia que facts page's section with the new fact inserted
     * @var $recentSection: a string representing the section name in the recent Sabia que facts page where the new fact will be edited
     * @var $nominatorMessage: a string representing the message to be sent to the user who nominated the new Sabia que fact
     * @var $nominatorTalkPage: a string representing the talk page of the user who nominated the new Sabia que fact
     * @var $archiveCode: a string representing the code to be inserted into the Sabia que archive page with the new fact and its nomination details
     * @var $archivePage: a string representing the page name where the Sabia que archive is located
     * @var $tweet: a string representing the published tweet about the new Sabia que fact
     * @var $tweetID: a int representing the published tweet ID
     *
     * @param array $tokens An array with keys and tokens of the Twitter API
     * @return void
    */
    public function run($tokens)
    {
        $deadline = $this->isDeadlineMet();
        if ($deadline !== 0) {
            echo "$deadline segundos até o prazo de publicação";
            return;
        }

        $approvedNominationPage = "Wikipédia:Sabia que/Propostas/Aprovadas";
        $templatePage = "Predefinição:Sabia que";
        $recentPage = "Wikipédia:Sabia que/Arquivo/Recentes";
        $logPage = "User:SabiaQueBot/log";

        list($newFact, $article, $nominator, $nomination) = $this->extractNewNominationInfo($approvedNominationPage);

        list($oldFact,          $templateCode)      = $this->compileTemplate(           $newFact,   $templatePage);
        list($articleTalkCode,  $articleTalkPage)   = $this->compileArticleTalk(        $article,   $newFact);
        list($recentCode,       $recentSection)     = $this->compileRecent(             $oldFact,   $recentPage);
        list($nominatorMessage, $nominatorTalkPage) = $this->compileUserTalkPage(       $nominator, $article, $newFact);
        list($archiveCode,      $archivePage)       = $this->composeArchive(            $nomination);
        list($tweet,            $tweetID)           = $this->doTweet(                   $newFact,   $article, $tokens);

        $this->edit($templateCode,      null,           false, "bot: (1/6) Inserindo SabiaQue", $templatePage);
        $this->edit($articleTalkCode,   0,              false, "bot: (2/6) Inserindo SabiaQueDiscussão", $articleTalkPage);
        $this->edit($recentCode,        $recentSection, false, "bot: (3/6) Inserindo Arquivo/Recentes", $recentPage);
        $this->edit($archiveCode,       'append',       false, "bot: (4/6) Inserindo Propostas/Arquivo", $archivePage);
        $this->edit('',                 1,              false, "bot: (5/6) Arquivando proposição publicada", $approvedNominationPage);
        $this->edit($nominatorMessage,  'append',       false, "bot: (6/6) Inserindo ParabénsSQ", $nominatorTalkPage);
        $this->edit($tweet,             null,           false, "bot: (log) Registro de última proposição inserida", $logPage);

        $creator = $this->retrieveArticleCreator($article);
        if ($creator) {
            list($creatorMessage, $creatorTalkPage) = $this->compileUserTalkPage($creator, $article, $newFact, true);
            $this->edit($creatorMessage, 'append', false, "bot: (xtr) Inserindo ParabénsSQ para criador do artigo", $creatorTalkPage);
        }
    }
}

$tokens = [
    $twitter_consumer_key,
    $twitter_consumer_secret,
    $twitter_access_token,
    $twitter_access_token_secret
];
$api = new SabiaQue('https://pt.wikipedia.org/w/api.php', $usernameSQ, $passwordSQ);
$api->run($tokens);
