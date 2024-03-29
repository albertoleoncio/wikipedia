<?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';

class BlockList extends WikiAphpiUnlogged
{
    private $mysqli;

    /**
     * Constructs a new instance of the class.
     *
     * @param mixed $endpoint The initial endpoint for the object.
     * @throws mysqli_sql_exception if there is an error establishing the database connection.
     */
    public function __construct($endpoint)
    {
        parent::__construct($endpoint);
        $ts_pw = posix_getpwuid(posix_getuid());
        $ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
        $this->mysqli = new mysqli(
            'tools.db.svc.eqiad.wmflabs', 
            $ts_mycnf['user'], 
            $ts_mycnf['password'], 
            $ts_mycnf['user']."__blcontext"
        );
    }

    /**
     * Synchronizes the blocklist with the content of MediaWiki:Spam-blacklist.
     *
     * This method performs the following steps:
     *   1. Retrieves the content of MediaWiki:Spam-blacklist using the `get()` method.
     *   2. Splits the content into individual lines.
     *   3. Iterates over each line.
     *   4. Parses the line using the `parseLine()` method.
     *   5. Continues to the next line if the parsed line is empty or a comment or already exists on the database.
     *   6. Calls the `insertBlocklistEntry()` method to insert the parsed line into the blocklist.
     */
    public function sync()
    {
        $blocklist = $this->get('MediaWiki:Spam-blacklist');
        $lines = explode("\n", $blocklist);

        $existingRegexList = $this->getExistingRegexList();

        foreach ($lines as $line) {
            $parsedLine = $this->parseLine($line);

            if (!$parsedLine) {
                continue;
            }

            if (in_array($parsedLine['0'], $existingRegexList)) {
                continue;
            }

            $this->insertBlocklistEntry($parsedLine);
        }

        echo "\nSync done!\n";
    }

    /**
     * Performs additions to the blocklist database.
     *
     * This method performs the following steps:
     *   1. Retrieves the next regex from the database.
     *      - Throws a `ContentRetrievalException` if no regex is found.
     *   3. Uses the `blame()` method to get revision information for the regex.
     *      - Reloads the page if no revision is found.
     *   5. Retrieves the user, timestamp, and comment from the revision data.
     *   6. Calls the `updateRegexData()` method to update the database with the revision details.
     *   7. Outputs the regex and the number of rows inserted.
     *   8. Reloads the page.
     */
    public function additions() 
    {
        // Retrieve regex from the database
        $regex = $this->getNextRegex();
        if ($regex === false) {
            echo "Additions done!";
            return;
        }

        // Get revision information
        $revision = $this->blame($regex);
        if (!$revision) {
            $this->reloadPage();
        }
        
        $revisionData = $this->getRevisionData($revision);
        $user = $revisionData["user"];
        $timestamp = $revisionData["timestamp"];
        $summary = $revisionData["comment"];

        // Update the database with the revision details
        $result = $this->updateRegexData($regex, $revision, $user, $timestamp, $summary);
            
        // Output the results
        echo "\n$regex\n";
        printf("%d Row inserted.\n", $result);
        $this->reloadPage();
    }

    /**
     * Retrieves the results from the database.
     * Note: The regex field is processed to replace certain characters for better readability.
     *
     * @return array The array of results from the database.
     */
    public function results()
    {
        $query = "SELECT regex, add_diff, add_user, add_timestamp, add_summary, comment FROM ptwiki_sbl";
        $stmt = $this->mysqli->prepare($query);

        if (!$stmt) {
            throw new ContentRetrievalException("Erro na consulta SQL");
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $list = [];

        while ($row = $result->fetch_assoc()) {
            $row['regex'] = strtr($row['regex'], ['\b' => '', '\\' => '', '/' => '/<wbr>']);
            $list[] = $row;
        }

        $stmt->close();
        return $list;
    }

    /**
     * Inserts a blocklist entry into the database.
     *
     * @param array $parsedLine The parsed line containing regex, comment, and com_diff.
     * @throws UnexpectedValueException if there is an error in the prepare statement.
     */
    private function insertBlocklistEntry($parsedLine)
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO ptwiki_sbl (regex, comment, com_diff) VALUES (?, ?, ?)"
        );

        if (!$stmt) {
            throw new UnexpectedValueException("Error in prepare statement:".$this->mysqli->error);
        }

        list($regex, $comment, $com_diff) = $parsedLine;

        $stmt->bind_param('ssi', $regex, $comment, $com_diff);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo "\n$regex\n";
            printf("%d Row inserted.\n", $stmt->affected_rows);
        } else {
            echo "\n$regex\nSkipped.\n";
        }

        $stmt->close();
    }

    /**
     * Retrieves the existing regex list from the database.
     *
     * @return array The array of existing regex patterns.
     */
    private function getExistingRegexList()
    {
        $query = $this->mysqli->query("SELECT regex FROM ptwiki_sbl;");
        $existingRegexList = [];

        while ($row = $query->fetch_assoc()) {
            $existingRegexList[] = $row['regex'];
        }

        return $existingRegexList;
    }

    /**
     * Retrieves the next regex pattern from the database that requires processing.
     *
     * @return string|false The next regex pattern to process, or false if no pattern is found.
     */
    private function getNextRegex()
    {
        $query = $this->mysqli->query("SELECT regex, old_regex, add_timestamp FROM ptwiki_sbl WHERE add_user IS NULL LIMIT 1;");
        $row = $query->fetch_assoc();
        return $row['old_regex'] ?? $row['regex'] ?? false;
    }

    /**
     * Updates the regex data in the database for a specific regex pattern.
     *
     * @param string $regex      The regex pattern to update.
     * @param int    $revision   The new revision number associated with the regex pattern.
     * @param string $user       The user who made the update.
     * @param string $timestamp  The timestamp of the update.
     * @param string $summary    The summary of the update.
     * @return int The number of affected rows as a result of the update operation.
     */
    private function updateRegexData($regex, $revision, $user, $timestamp, $summary)
    {
        $stmt = $this->mysqli->prepare(
            "UPDATE ptwiki_sbl 
            SET 
                add_diff = ?, 
                add_user = ?,
                add_timestamp = ?, 
                add_summary = ?
            WHERE 
                old_regex = ? OR 
                regex = ?
            ;"
        );
        $stmt->bind_param('isssss', $revision, $user, $timestamp, $summary, $regex, $regex);
        $stmt->execute();

        return $stmt->affected_rows;
    }

    /**
     * Reloads the current page using JavaScript.
     */
    private function reloadPage()
    {
        echo "<script>location.reload();</script>";
    }

    /**
     * Retrieves the revision data for a given revision ID.
     *
     * @param int $rev The revision ID for which to retrieve the revision data.
     * @return array|null The revision data for the given revision ID, or null if the data is not found.
     */
    private function getRevisionData($rev) 
    {
        $params = [
            "action"        => "query",
            "format"        => "php",
            "prop"          => "revisions",
            "revids"        => $rev,
            "formatversion" => "2",
            "rvprop"        => "comment|user|timestamp"
        ];

        return $this->see($params)["query"]["pages"]["0"]["revisions"]["0"] ?? null;
    }

    /**
     * Retrieves the revision number for a given needle using the WikiBlame.
     *
     * @param string $needle The search term (needle) to retrieve the revision number for.
     * @return int|false The revision number for the given needle, or false if the count is not found or an error occurred.
     */
    private function blame($needle)
    {
        $params = [
            'user_lang'      => 'pt',
            'lang'           => 'pt',
            'project'        => 'wikipedia',
            'tld'            => 'org',
            'article'        => 'MediaWiki:Spam-blacklist',
            'needle'         => $needle,
            'skipversions'   => '0',
            'ignorefirst'    => '0',
            'limit'          => random_int(700, 4000),
            'searchmethod'   => 'int',
            'order'          => 'desc',
            'force_wikitags' => 'on',
            'user'           => '',
        ];

        $url = 'https://blame.toolforge.org/wikiblame.php?'.http_build_query($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AlbeROBOT/1.0');
        $get = curl_exec($ch);
        curl_close($ch);

        preg_match('/\d*(?="><b>aqui<)/', $get, $output);

        return $output['0'] ?? false;
    }

    /**
     * Parses a line of th blocklist and extracts the regex, comment, and diff (if present).
     *
     * @param string $line The line of configuration to parse.
     * @return array|false An array containing the parsed regex, comment, and diff (if present), or false if the line is empty or a comment.
     */
    private function parseLine($line)
    {
        $check = trim($line);

        // Check if the line is empty or a comment
        if (empty($check) || $check[0] === '#') {
            return false;
        }

        // Split the line by '#' delimiter
        $lineParts = explode('#', $line);

        if (count($lineParts) == 1) {
            // Only the regex part is present
            return [trim($lineParts[0]), null, null];
        } elseif (count($lineParts) == 2) {
            $tail = trim($lineParts[1]);

            if (is_numeric($tail)) {
                // Regex and diff are present
                return [trim($lineParts[0]), null, $tail];
            } else {
                // Regex and comment are present
                return [trim($lineParts[0]), $tail, null];
            }
        } else {
            // Multiple '#' in the line, join them back with '#'
            $regex = trim(array_shift($lineParts));
            $comment = '#' . implode('#', $lineParts);

            return [$regex, $comment, null];
        }
    }
}

$api = new BlockList('https://pt.wikipedia.org/w/api.php');
$api->sync();
$api->additions();
$lines = $api->results();
?>

<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <title>SBL</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="./tpar/w3.css">
        <link rel="stylesheet" href="https://tools-static.wmflabs.org/cdnjs/ajax/libs/datatables/1.10.21/css/jquery.dataTables.min.css" />
        <link rel="stylesheet" href="https://tools-static.wmflabs.org/cdnjs/ajax/libs/datatables.net-buttons-dt/2.3.6/buttons.dataTables.min.css" />
        <link rel="stylesheet" href="https://tools-static.wmflabs.org/cdnjs/ajax/libs/datatables.net-responsive-dt/2.4.1/responsive.dataTables.min.css" />
        <script src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
        <script src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/datatables.net/2.1.1/jquery.dataTables.min.js"></script>
        <script src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/datatables.net-responsive/2.4.1/dataTables.responsive.min.js"></script>
        <script src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/datatables.net-buttons/2.3.6/js/dataTables.buttons.min.js"></script>
        <script src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/datatables.net-buttons/2.3.6/js/buttons.colVis.min.js"></script>
        <style>
        .loader {
            border: 16px solid #f3f3f3;
            border-radius: 50%;
            border-top: 16px solid #000000;
            width: 120px;
            height: 120px;
            margin: auto;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        #myTable {
            display: none;
        }
        </style>
    </head>
    <body>
        <div class="w3-container" id="menu">
            <div class="w3-content" style="max-width:800px">
                <h5 class="w3-center w3-padding-48"><span class="w3-tag w3-wide">Spam Block List</span></h5>
                <div class="w3-row-padding w3-center w3-margin-top">
                    <div class="w3-container w3-padding-48 w3-card w3-small" id="main">
                        <div class="loader"></div>
                        <table id="myTable" class="display responsive" style="width:100%">
                            <thead>
                                <tr>
                                    <th>URL</th>
                                    <th>Diff</th>
                                    <th>Autor</th>
                                    <th>Timestamp</th>
                                    <th>Sumário</th>
                                    <th>Comentário</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lines as $line): ?>
                                    <tr>
                                        <td><?=$line['regex']?></td>
                                        <td><a href="https://pt.wikipedia.org/w/index.php?diff=<?=$line['add_diff']?>"><?=$line['add_diff']?></a></td>
                                        <td><a href="https://pt.wikipedia.org/w/index.php?title=Special:Contribs&limit=500&end=<?=substr($line['add_timestamp'],0,10)?>&start=<?=substr($line['add_timestamp'],0,10)?>&target=<?=$line['add_user']?>"><?=$line['add_user']?></a></td>
                                        <td><?=str_replace('T', '<br>', $line['add_timestamp'])?></td>
                                        <td><?=$line['add_summary']?></td>
                                        <td><?=$line['comment']?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <script type="text/javascript">
                            $(document).ready( function () {
                                $('.loader').hide();
                                $('#myTable').show();
                                $('#myTable').DataTable( {
                                    responsive: true
                                } );
                            } );
                        </script>
                    </div>
                </div>
            </div>
        </div>
        <hr>
        <?php include_once('footer.html'); ?>
    </body>
</html>



