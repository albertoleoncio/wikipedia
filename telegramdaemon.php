<?php
require_once './bin/globals.php';
require_once 'WikiAphpi/main.php';

$offset = file_get_contents("telegram_offset.inc");
if (!is_numeric($offset)) {
    $offset = 0;
}

$params = [
    'timeout' => 10,
    'allowed_updates' => '["chat_member"]',
    'offset' => $offset
];

$content = file_get_contents("https://api.telegram.org/bot${TelegramVerifyToken}/getUpdates?" . http_build_query($params));
$update = json_decode($content, true)["result"];

foreach ($update as $event) {
    $offset = $event["update_id"];
    if (isset($event["chat_member"]["new_chat_member"])) {

        if ($event["chat_member"]["chat"]["id"] != "-1001169425230") {
            continue;
        }

        if ($event["chat_member"]["new_chat_member"]["user"]["is_bot"]) {
            continue;
        }

        if ($event["chat_member"]["new_chat_member"]["status"] != "member") {
            continue;
        }

        $new_user = $event["chat_member"]["new_chat_member"]["user"]["username"] ?? '';
        $new_user_id = $event["chat_member"]["new_chat_member"]["user"]["id"];

        echo "Processing ${new_user_id}...\n";

        $ts_pw = posix_getpwuid(posix_getuid());
        $ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
        $con = mysqli_connect(
            'tools.db.svc.eqiad.wmflabs',
            $ts_mycnf['user'],
            $ts_mycnf['password'],
            $ts_mycnf['user']."__telegram"
        );
        $query = "INSERT IGNORE INTO `verifications` (`t_id`, `t_username`) VALUES ('$new_user_id', '$new_user')";
        mysqli_query($con, $query);
        mysqli_close($con);
        
    }
}
echo "Offset: $offset\n";
file_put_contents("telegram_offset.inc", $offset);
