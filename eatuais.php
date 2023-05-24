<pre><?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';
require_once "tpar/twitteroauth/autoload.php";
use Abraham\TwitterOAuth\TwitterOAuth;
date_default_timezone_set('UTC');

class EventosAtuais extends WikiAphpiLogged
{

    /**
     * Parses a proposal section and returns an array with the relevant information.
     *
     * @param string $sectionText The text of the proposal section to be parsed.
     * @return array Returns an array with the following keys:
     *  - 'concordo': the number of approvals in the section.
     *  - 'discordo': the number of rejections in the section.
     *  - 'elapsed': the time elapsed (in seconds) since the section was posted.
     *  - 'image': the URL of the image associated with the section (if any).
     *  - 'bot': the bot flag of the section ('s' for pending sections).
     *  - 'texto': the main text of the section.
     *  - 'article': the name of the article associated with the section.
     * @throws Exception If any of the required keys is not set in the parsed array.
     */
    private function proposalParser($sectionText)
    {
        $section_sanitized = preg_replace('/ *<!--(.*?)-->/', '', $sectionText);
        preg_match_all('/{{[Cc]oncordo}}/',         $section_sanitized, $parsedSection['concordo']);
        preg_match_all('/{{[Dd]iscordo}}/',         $section_sanitized, $parsedSection['discordo']);
        preg_match_all('/\| *timestamp *= *(\d*)/', $section_sanitized, $parsedSection['timestamp']);
        preg_match_all('/\| *imagem *= *([^\n<]*)/',$section_sanitized, $parsedSection['image']);
        preg_match_all('/\| *bot *= *(\w)/',        $section_sanitized, $parsedSection['bot']);
        preg_match_all('/\| *texto *= *([^\n<]*)/', $section_sanitized, $parsedSection['texto']);
        preg_match_all('/\| *artigo *= *([^\n<]*)/',$section_sanitized, $parsedSection['article']);

        // Check if all necessary variables are set
        if (!isset($parsedSection['concordo']["0"]) ||
            !isset($parsedSection['discordo']["0"]) ||
            !isset($parsedSection['timestamp']["1"]["0"]) ||
            !isset($parsedSection['bot']["1"]["0"]) ||
            !isset($parsedSection['texto']["1"]["0"]) ||
            !isset($parsedSection['article']["1"]["0"])) {
            throw new UnexpectedValueException('One or more necessary variables are not set.');
        }

        return [
            'concordo'  => count($parsedSection['concordo']["0"]),
            'discordo'  => count($parsedSection['discordo']["0"]),
            'elapsed'   => time() - $parsedSection['timestamp']["1"]["0"],
            'image'     => trim($parsedSection['image']["1"]["0"]) ?? '',
            'bot'       => trim($parsedSection['bot']["1"]["0"]),
            'texto'     => trim($parsedSection['texto']["1"]["0"]),
            'article'   => trim($parsedSection['article']["1"]["0"])
        ];
    }

    /**
     * Analyzes a proposal section and returns APPROVED if it is approved,
     * SKIP if it is not yet eligible for approval, or REJECTED if it is rejected.
     *
     * @param array $parsedSection An array containing parsed data of the proposal section
     * @return string Returns APPROVED if the proposal is approved, SKIP if it is not eligible for approval, or REJECTED if it is rejected
     */
    private function proposalAnalyser($parsedSection)
    {
        $discordo = $parsedSection['discordo'];
        $concordo = $parsedSection['concordo'];
        $elapsed = $parsedSection['elapsed'];

        // If the section was not pending, return SKIP
        if ($parsedSection['bot'] !== "s") {
            return 'SKIP';
        }

        // If the section was posted less than 2 hours ago, return SKIP
        if ($parsedSection['elapsed'] < 7200) {
            echo "<b><2</b>";
            return 'SKIP';
        }

        // Determine the minimum number of approvals based on elapsed time
        $minConcordo = 0;
        $rejectionAllowed = false;
        switch (true) {
            case $elapsed < 14400:
                echo "<b>2~4</b>";
                $minConcordo = 5;
                break;
            case $elapsed < 21600:
                echo "<b>4~6</b>";
                $minConcordo = 3;
                break;
            case $elapsed < 28800:
                echo "<b>6~8</b>";
                $minConcordo = 1;
                break;
            default:
                echo "<b>>8</b>";
                $minConcordo = ceil(($concordo + $discordo) * 0.75);
                $rejectionAllowed = true;
        }

        // Check if the number of approvals meets the minimum requirement
        if ($discordo > 0 && !$rejectionAllowed) {
            return 'SKIP';
        } elseif ($concordo < $minConcordo && !$rejectionAllowed) {
            return 'SKIP';
        } elseif ($concordo >= $minConcordo) {
            return 'APPROVED';
        } else {
            return 'REJECTED';
        }
    }

    /**
     * Modifies a template page with a new approved event.
     *
     * @param string $page The name of the template page to modify.
     * @param string $texto The text of the new approved event.
     * @param string $image The name of the image to be included in the template.
     * @param string $article The name of the article related to the event.
     * @return array An array containing the modified code of the template and the oldest event.
     * @throws UnexpectedValueException If the main template is formatted incorrectly.
     */
    private function compileTemplate($page, $texto, $image, $article)
    {
        // Get the code of the main template page
        $code = $this->get($page);

        // Split the code into its sections
        $code_sections = explode("\n<!-- % -->\n", $code);

        // Check that the main template page is formatted correctly
        if (count($code_sections) != 3) {
            throw new UnexpectedValueException("Predefinição principal formatada incorretamente!");
        }

        // Remove the ''(imagem)'' marker from the text section
        $code_sections["1"] = preg_replace('/\(\'\'[^\']*?\'\'\) |\'\'\([^\)]*\)\'\' /', '', $code_sections["1"]);

        // Split the text section into individual events
        $code_events = explode("\n", $code_sections["1"]);

        // Insert the new event at the beginning of the list
        array_unshift($code_events, "*<!-- ".utf8_encode(strftime('%e de %B de %Y', time()))." --> ".$texto);

        // Remove the oldest event from the list
        $oldest = end($code_events);
        array_pop($code_events);

        // Reassemble the code with the updated event list
        $code_sections["1"] = implode("\n", $code_events);
        $code = implode("\n<!-- % -->\n", $code_sections);

        // Add the image to the template
        $params = [
            'action' => 'query',
            'format' => 'php',
            'prop'   => 'info',
            'titles' => "File:$image"
        ];
        $imageInfo = end($this->see($params)["query"]["pages"]);
        if (isset($imageInfo["known"])) {
            $code = preg_replace(
                '/<imagemap>[^>]*?<\/imagemap>/',
                "<imagemap>\nFicheiro:$image|125x175px|borda|direita\ndefault [[$article]]\n</imagemap>",
                $code
            );
        } else {
            $code = preg_replace(
                '/<imagemap>[^>]*?<\/imagemap>/',
                "<imagemap>\nFicheiro:Globe-with-clock-2.svg|125x175px|borda|direita\ndefault [[$article]]\n</imagemap>",
                $code
            );
        }

        // Return the updated code and the most recent event
        return [$code, $oldest];
    }

    /**
     * Modifies the recent's event page by adding an old event.
     *
     * @param string $page The name of the page to modify.
     * @param string $last The code of the old event to add.
     * @return string The modified code of the page.
     */
    private function compileRecent($page, $last)
    {
        // Remove comments from the last event
        $removeComments = preg_replace('/<!--+ *|(?<=-)-+>/', '', $last);

        // Insert the last event at the top of the page code
        $code = preg_replace('/-->/', "-->\n$removeComments", $this->get($page));

        return $code;
    }

    /**
     * Modifies a article's talk page with a new event note.
     *
     * @param string $page The name of the article related to the event.
     * @param string $diff The RevID of the event's publication.
     * @return array An array containing the modified code of the talk page and the name of the talk page.
     */
    private function compileArticleTalk($page, $diff)
    {
        $talkPage = "Discussão:".$this->resolveRedirect($page);
        if ($this->isPageCreated($talkPage)) {
            $html = $this->get($talkPage, 0);
        } else {
            $html = '{{PD}}';
        }
        $html .= "\n{{EvRdiscussão|data1=".utf8_encode(strftime('%e de %B de %Y', time()))."|oldid1=$diff}}";
        return [$html, $talkPage];
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
        $tweet = preg_replace(
            '/\'|\[\[[^\|\]]*\||\]|\[\[/',
            '',
            preg_replace(
                '/ *<!--(.*?)--> */',
                '',
                $text
            )
        );
        $tweet .= "\n\nEsse é um evento recente ou em curso que está sendo acompanhado por nossas voluntárias e voluntários. Veja mais detalhes no link: https://pt.wikipedia.org/w/index.php?title=";
        $tweet .= rawurlencode($article);

        $twitter_conn = new TwitterOAuth(...$tokens);
        $post = $twitter_conn->post(
            "statuses/update",
            ["status" => $tweet]
        );

        return [$post->id];
    }

    /**
     * Marks a nomination as declined.
     *
     * @param string $text The code of the nomination page.
     * @param int $section The section to edit on the nomination page.
     * @param string $nominationPage The name of the nomination page.
     * @return void
     */
    private function declineNomination($text, $section, $nominationPage)
    {
        $nominationCode = preg_replace('/\| *bot *= *\w/', "|bot = r |em = ".time(), $text);
        $summary = "bot: Marcando proposta como recusada";
        $this->edit($nominationCode, $section, FALSE, $summary, $nominationPage);
    }

    /**
     * Marks a nomination as approved and edits the relevant pages
     *
     * @param string $text The text of the nomination to approve.
     * @param int $section The section number of the nomination to approve.
     * @param string $nominationPage The page on which the nomination appears.
     * @param array $parser An array containing parsed information about the nomination.
     * @param array $tokens An array with keys and tokens of the Twitter API
     */
    private function approveNomination($text, $section, $nominationPage, $parser, $tokens)
    {

        // Define the page names for various templates and logs
        $templatePage = "Predefinição:Eventos atuais";
        $recentPage = "Predefinição:Ea-notícias";
        $logPage = "User:EventosAtuaisBot/log";

        // Define edit summaries for each edit made by the bot
        $nominationSummary = "bot: (1/4) Marcando proposta como publicada";
        $templateSummary = "bot: (2/4) Publicando nova proposta";
        $recentSummary = "bot: (3/4) Inserido proposta recente";
        $articleTalkSummary = "bot: (4/4) Inserindo EvRdiscussão";
        $logSummary = "bot: (log) Registrando último evento aprovado";

        // Update the nomination page by marking it as approved
        $nominationCode = preg_replace('/\| *bot *= *\w/', "|bot = p |em = ".time(), $text);
        $this->edit($nominationCode, $section, FALSE, $nominationSummary, $nominationPage);

        // Publish the new event in a template
        list($templateCode, $oldEvent) = $this->compileTemplate($templatePage, $parser['texto'], $parser['image'], $parser['article']);
        $dif = $this->edit($templateCode, NULL, FALSE, $templateSummary, $templatePage);

        // Add the new event to a list of recent events
        $recentCode = $this->compileRecent($recentPage, $oldEvent);
        $this->edit($recentCode, NULL, FALSE, $recentSummary, $recentPage);

        // Add a discussion section about the new event to the relevant article talk page
        list($articleTalkCode,  $articleTalkPage) = $this->compileArticleTalk($parser['article'], $dif);
        $this->edit($articleTalkCode, 0, FALSE, $articleTalkSummary, $articleTalkPage);

        //Post on Twitter
        $tweet = $this->doTweet($parser['texto'], $parser['article'], $tokens);

        // Log the new event in a bot log
        $this->edit($parser['texto'], NULL, FALSE, $logSummary, $logPage);
    }

    /**
     * Runs the main loop that processes each proposal in the nominations page.
     * For each proposal, it checks its status and acts accordingly by either
     * rejecting or approving it.
     *
     * @param array $tokens An array with keys and tokens of the Twitter API
     */
    public function run($tokens)
    {
        $nominationPage = "Wikipédia:Eventos atuais/Propostas";

        $sectionText = $this->getSections($nominationPage);

        foreach ($sectionText as $section => $text) {

            if ($section == "0") {
                continue;
            }

            $parser = $this->proposalParser($text);
            echo "\n{$parser['article']}: ".bcdiv($parser['elapsed'], 60, 0)." minutos / {$parser['concordo']} concordos / {$parser['discordo']} discordos => ";

            $proposalStatus = $this->proposalAnalyser($parser);
            echo " - $proposalStatus";

            if ($proposalStatus === 'SKIP') {
                continue;
            }

            if($proposalStatus === 'REJECTED') {
                $this->declineNomination($text, $section, $nominationPage);
            }

            if($proposalStatus === 'APPROVED') {
                $this->approveNomination($text, $section, $nominationPage, $parser, $tokens);
            }
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
$api = new EventosAtuais('https://pt.wikipedia.org/w/api.php',$usernameEA, $passwordEA);
$api->run($tokens);