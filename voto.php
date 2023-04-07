<?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';
date_default_timezone_set('UTC');

/**
 * Class Suffrage
 * This class verify a user's right to vote on a given time.
 */
class Suffrage extends WikiAphpiUnlogged
{

    /**
     * Processes the date and time provided by the user and returns a timestamp string in ISO 8601 format.
     *
     * This function accepts two optional parameters, $getDate and $getTime, which represent the date and time provided
     * by the user, respectively. If both $getDate and $getTime are provided, this function returns a timestamp string
     * representing that date and time. If only $getDate is provided, the function returns a timestamp string representing
     * the last second of that day. If only $getTime is provided, the function returns a timestamp string representing the
     * current date and the time provided, adjusted for the UTC timezone and if the time provided is after the current UTC 
     * time, the function will return a timestamp string representing the previous day with the time provided. If neither 
     * $getDate nor $getTime are provided, the function returns a timestamp string representing the current UTC date and time.
     *
     * @param string|null $getDate The date provided by the user, in YYYY-MM-DD format.
     * @param string|null $getTime The time provided by the user, in HH:MM:SS format.
     * @return string A timestamp string in ISO 8601 format, e.g. "2023-04-07T19:12:34Z".
     */
    private function processTimestamp($getDate, $getTime)
    {
        if (!empty($getDate) && !empty($getTime)) {
            $date = date("o-m-d", strtotime($getDate));
            $time = date("H:i:s", strtotime($getTime));
        } elseif (!empty($getDate) && empty($getTime)) {
            $date = date("o-m-d", strtotime($getDate));
            $time = "23:59:59";
        } elseif (empty($getDate) && !empty($getTime)) {
            $time = date("H:i:s", strtotime($getTime));
            if ($time < date("H:i:s")) {
                $date = date("o-m-d");
            } else {
                $date = date("o-m-d", strtotime("$getDate -1 day"));
            }
        } else {
            $date = date("o-m-d");
            $time = date("H:i:s");
        }

        return "{$date}T{$time}Z";
    }

    /**
     * Counts the number of edits on the main domain made by a given user starting from a given timestamp.
     *
     * @param string $user The username of the user whose edits will be counted.
     * @param string $timestamp The timestamp from which to start counting edits, in ISO 8601 format.
     * @return int|false The number of edits on the main domain made by the user, or false if there was an error.
     */
    private function countUserEdits($user, $timestamp)
    {
        $params = [
            'action'        => 'query',
            'format'        => 'php',
            'list'          => 'usercontribs',
            'uclimit'       => '301',
            'ucnamespace'   => '0',
            'ucuser'        => $user,
            'ucstart'       => $timestamp
        ];
        return count($this->see($params)['query']['usercontribs']) ?? false;
    }

    /**
     * Retrieves the timestamp of a given user's first edit on Wikipedia.
     *
     * @param string $user The username of the user whose first edit timestamp will be retrieved.
     * @return string|false The timestamp of the user's first edit, in ISO 8601 format, or false if there was an error.
     */
    private function checkFirstEditTimestamp($user)
    {
        $params = [
            'action'    => 'query',
            'format'    => 'json',
            'list'      => 'usercontribs',
            'uclimit'   => '1',
            'ucuser'    => $user,
            'ucdir'     => 'newer',
            'ucprop'    => 'timestamp'
        ];
        return $this->see($params)['query']['usercontribs']['0']['timestamp'] ?? false;
    }

    /**
     * Verifies if a user is eligible to vote based on their edit history and a given date and time.
     * @param int $getUser The ID of the user to verify.
     * @param string|null $getDate (Optional) The date in Y-m-d format to check the user's edit history, or null to use the current date.
     * @param string|null $getTime (Optional) The time in H:i:s format to check the user's edit history, or null to use the current time.
     * @return array An array with three elements:
     *  - The number of edits made by the user on or before the specified timestamp.
     *  - A string representing the user's eligibility status. Possible values are:
     *    - 'DORMANT' if the user has never made an edit, including the main domain.
     *    - 'IMMATURE' if the user's first edit was less than 90 days ago.
     *    - 'INACTIVE' if the user have not made at least 300 edits.
     *    - 'VALID' if the user has made at least 300 edits in the last 365 days and user's first edit was more than 90 days ago.
     *  - A string representing the timestamp used for the verification, in ISO 8601 format adjusted for the UTC timezone.
     */
    public function verifySuffrage($getUser, $getDate, $getTime)
    {
        $timestamp = $this->processTimestamp($getDate, $getTime);
        $count = $this->countUserEdits($getUser, $timestamp);
        $first = $this->checkFirstEditTimestamp($getUser);

        if (!$first || !$count) {
            return [0, 'DORMANT', $timestamp];
        }

        if ($first > strtotime("$timestamp -90 days")) {
            return [0, 'IMMATURE', $timestamp];
        }

        if ($count < 300) {
            return [$count, 'INACTIVE', $timestamp];
        }

        return [$count, 'VALID', $timestamp];
    }
}

/**
 * This code block instantiates the necessary class and runs the `verifySuffrage()` method
 * of the `Suffrage` class with the provided parameters.
 *
 * If the `user` parameter is not set, the code block returns `false`.
 */
$getUser = filter_input(INPUT_GET, 'user', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$getDate = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$getTime = filter_input(INPUT_GET, 'time', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if ($getUser) {
    $api = new Suffrage('https://pt.wikipedia.org/w/api.php');
    $echo = $api->verifySuffrage($getUser, $getDate, $getTime);
} else {
    $echo = false;
}
?><!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <title>Direito a voto</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="./tpar/w3.css">
    </head>
    <body>
        <div class="w3-container" id="menu">
            <div class="w3-content" style="max-width:800px">
                <h5 class="w3-center w3-padding-48"><span class="w3-tag w3-wide">DIREITO A VOTO</span></h5>
                <div class="w3-row-padding w3-center w3-margin-top">
                    <div class="w3-half">
                        <form action="/voto.php" method="get">
                            <div class="w3-container w3-padding-48 w3-card">
                                <p class="w3-center w3-wide">USUÁRIO</p>
                                <p class="w3-text-grey"><input class="w3-input w3-padding-16 w3-border" value="<?=$getUser??''?>" type="text" name="user"></p><br>
                                <div class="w3-half">
                                    <p class="w3-center w3-wide">DATA</p>
                                    <p class="w3-text-grey"><input class="w3-input w3-padding-16 w3-border" value="<?=$getDate??''?>" type="date" name="date"></p><br>
                                </div>
                                <div class="w3-half">
                                    <p class="w3-center w3-wide">HORA</p>
                                    <p class="w3-text-grey"><input class="w3-input w3-padding-16 w3-border" value="<?=$getTime??''?>" type="time" step="1" max="23:59:59" name="time"></p><br>
                                </div>
                                <small>Por favor insira a data e hora do começo da votação como Tempo Universal Coordenado (UTC).</small>
                                <button class="w3-button w3-block w3-black" type="submit">Verificar</button>
                            </div>
                        </form>
                    </div>
                    <div class="w3-half">
                        <div class="w3-container w3-padding-48 w3-card">
                            <?php if (!$echo): ?>
                                <p>Preencha o formulário ao lado</p>
                            <?php else: ?>
                                <div class='w3-light-grey'>
                                    <div 
                                    class='w3-container w3-<?=($echo['0']>=300)?'green':'red'?> w3-center' 
                                    style='width:<?=floor($echo['0'] / 3)?>%'
                                    ><?=floor($echo['0'] / 3)?>%</div>
                                </div>
                                <p>
                                    <?php if($echo['1'] === 'DORMANT'): ?>
                                        Sem edições<br>(Não possui direito ao voto)
                                    <?php elseif($echo['1'] === 'IMMATURE'): ?>
                                        Não atingiu idade mínima<br>(Não possui direito ao voto)
                                    <?php elseif($echo['1'] === 'INACTIVE'): ?>
                                        Total de edições: <?=$echo['0']?><br>(Não possui direito ao voto)
                                    <?php else: ?>
                                        Total de edições: >=300<br>(Possui direito ao voto)
                                    <?php endif; ?>
                                </p>
                                <p>Usuário: <?=$getUser?><br>Em <?=$echo['2']?> UTC</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <hr>
        <a href="https://wikitech.wikimedia.org/wiki/Portal:Toolforge"><img src="https://tools-static.wmflabs.org/toolforge/banners/Powered-by-Toolforge-button.png" alt="Powered by Wikimedia Toolforge"></a>
    </body>
</html>
