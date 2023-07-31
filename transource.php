<?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';
date_default_timezone_set('UTC');

/**
 * A class that extends WikiAphpiUnlogged to provide source translation functionality.
 *
 * This class, SourceTranslator, is a subclass of WikiAphpiUnlogged and is designed to handle source translation tasks.
 * It offers methods to retrieve and parse wikitext code using the MediaWiki API, generate wikitext for a range of pages
 * with given parameters, and help source translation tasks.
 *
 * @see WikiAphpiUnlogged
 */
class SourceTranslator extends WikiAphpiUnlogged
{

    /**
     * Retrieves the parsed wikitext from the provided code.
     *
     * This method parses the given wikitext code using the MediaWiki API and returns the resulting parsed wikitext.
     * The method only retains the post-expand includeonly and ignores other content. Additionally, occurrences of the
     * {{nop}} template are replaced with a new line character.
     *
     * @param string $code The wikitext code to be parsed.
     *
     * @return string The parsed wikitext after processing.
     */
    private function getParsedCode($code)
    {
        $apiParams = [
            "action"        => "parse",
            "format"        => "php",
            "text"          => $code,
            "prop"          => "wikitext",
            "onlypst"       => 1,
            "contentmodel"  => "wikitext",
            "formatversion" => "2"
        ];

        $parsedResult = $this->see($apiParams)["parse"]["text"];

        // Replace {{nop}} occurrences with a new line character
        return preg_replace('/{{nop}}/i', "\n", $parsedResult);
    }


    /**
     * Generates wikitext for a range of pages with given parameters.
     *
     * This method takes an index, a start page number, and an end page number as input and generates wikitext for a range
     * of pages by substituting the provided index and page numbers into the "{{subst:Page:$index/$i}}" template. The method
     * then returns the parsed wikitext after processing.
     *
     * @param string $index The index used in the source.
     * @param int    $from  The start page number of the range.
     * @param int    $to    The end page number of the range.
     *
     * @return string|false The parsed wikitext for the range of pages, or false if invalid input is provided.
     */
    public function run($index, $from, $to)
    {
        // Check if $from and $to are numeric
        if (!is_numeric($from) || !is_numeric($to)) {
            return false;
        }

        // Check if $from is less than or equal to $to
        if ($from > $to) {
            return false;
        }

        // Generate wikitext for the range of pages
        $pages = '';
        for ($i = $from; $i <= $to; $i++) {
            $pages .= "{{subst:Page:$index/$i}}";
        }

        // Return the parsed wikitext after processing
        return $this->getParsedCode($pages);
    }


}

// Input sanitization and validation
$getIndex = filter_input(INPUT_GET, 'index', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$getFrom = filter_input(INPUT_GET, 'from', FILTER_VALIDATE_INT);
$getTo = filter_input(INPUT_GET, 'to', FILTER_VALIDATE_INT);

$getWiki = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$baseUrl = "https://{$getWiki}.wikisource.org/";
if (@file_get_contents($baseUrl) === false) {
    // Fallback to the default URL
    $baseUrl = "https://wikisource.org/";
}

// Create an instance of the SourceTranslator class
$api = new SourceTranslator("$baseUrl/w/api.php");

// Execute the SourceTranslator's run method and get the output
$echo = $api->run($getIndex, $getFrom, $getTo);

?><!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <title>Tradução Wikisource</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="./tpar/w3.css">
    </head>
    <body>
        <div class="w3-container" id="menu">
            <div class="w3-content" style="max-width:800px">
                <h5 class="w3-center w3-padding-48"><span class="w3-tag w3-wide">TRADUÇÃO WIKISOURCE</span></h5>
                <div class="w3-row-padding w3-center w3-margin-top">
                    <div class="w3-half">
                        <form method="get">
                            <div class="w3-container w3-padding-48 w3-card">
                                <p class="w3-center w3-wide">CÓDIGO DO IDIOMA</p>
                                <p class="w3-text-grey"><input class="w3-input w3-padding-16 w3-border" value="<?=$getWiki??''?>" type="text" name="lang"></p><br>
                                <p class="w3-center w3-wide">NOME DO FICHEIRO</p>
                                <p class="w3-text-grey"><input class="w3-input w3-padding-16 w3-border" value="<?=$getIndex??''?>" type="text" name="index"></p><br>
                                <div class="w3-half">
                                    <p class="w3-center w3-wide">PÁGINA<br>INICIAL</p>
                                    <p class="w3-text-grey"><input class="w3-input w3-padding-16 w3-border" value="<?=$getFrom??''?>" type="number" step="1" name="from"></p><br>
                                </div>
                                <div class="w3-half">
                                    <p class="w3-center w3-wide">PÁGINA<br>FINAL</p>
                                    <p class="w3-text-grey"><input class="w3-input w3-padding-16 w3-border" value="<?=$getTo??''?>" type="number" step="1" name="to"></p><br>
                                </div>
                                <button class="w3-button w3-block w3-black" type="submit">Converter</button>
                            </div>
                        </form>
                    </div>
                    <div class="w3-half">
                        <div class="w3-container w3-padding-48 w3-card">
                            <?php if (!$echo): ?>
                                <p>Preencha o formulário ao lado</p>
                            <?php else: ?>
                                <p>
                                    <textarea class="w3-input w3-padding-16 w3-border"
                                    rows="4" cols="50"><?=$echo??''?></textarea>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <hr>
        <?php include_once('footer.html'); ?>
    </body>
</html>
