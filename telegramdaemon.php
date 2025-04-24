<pre><?php
require_once __DIR__ . '/bin/globals.php';
require_once __DIR__ . '/WikiAphpi/main.php';

while (true) {
    $offset = file_get_contents(__DIR__ . '/telegram_offset.inc');
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
        echo "Processing event... ${offset}\n";
        if (isset($event["chat_member"]["new_chat_member"])) {

            if ($event["chat_member"]["chat"]["id"] != "-1001169425230") {
                echo "Ignoring chat...\n";
                continue;
            }

            if ($event["chat_member"]["new_chat_member"]["user"]["is_bot"]) {
                echo "Ignoring bot...\n";
                continue;
            }

            if ($event["chat_member"]["new_chat_member"]["status"] != "member") {
                echo "Ignoring non-member...\n";
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

            $query = "SELECT * FROM `verifications` WHERE `t_id` = '$new_user_id'";
            $result = mysqli_query($con, $query);
            if (mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $w_id = $row['w_id'];

                if ($w_id != null) {
                    echo "User already verified...\n";
                    continue;
                }
            } else {
                $query = "INSERT IGNORE INTO `verifications` (`t_id`, `t_username`) VALUES ('$new_user_id', '$new_user')";
                mysqli_query($con, $query);
                mysqli_close($con);
                echo "User added to database...\n";
            }

            # Get user status on Telegram
            $params = [
                'chat_id' => $event["chat_member"]["chat"]["id"],
                'user_id' => $new_user_id
            ];
            $content = file_get_contents("https://api.telegram.org/bot${TelegramVerifyToken}/getChatMember?" . http_build_query($params));
            $status = json_decode($content, true)["result"]["status"];

            if ($status == "member") {
                $params = [
                    'chat_id' => $event["chat_member"]["chat"]["id"],
                    'user_id' => $new_user_id,
                    "permissions" => [
                        "can_send_messages" => false,
                        "can_send_audios" => false,
                        "can_send_documents" => false,
                        "can_send_photos" => false,
                        "can_send_videos" => false,
                        "can_send_video_notes" => false,
                        "can_send_voice_notes" => false,
                        "can_send_polls" => false,
                        "can_send_other_messages" => false,
                        "can_add_web_page_previews" => false,
                        "can_change_info" => false,
                        "can_invite_users" => false,
                        "can_pin_messages" => false,
                        "can_manage_topics" => false,
                    ]
                ];
                $content = file_get_contents("https://api.telegram.org/bot${TelegramVerifyToken}/restrictChatMember?" . http_build_query($params));
                $content = json_decode($content, true);
                if ($content["ok"]) {
                    echo "Restricted ${new_user_id}...\n";
                } else {
                    echo "Failed to restrict ${new_user_id}...\n";
                }
            }
        }
        echo "Event processed...\n\n\n";
    }
    echo "Offset: $offset\n";
    file_put_contents("telegram_offset.inc", $offset);

    // Delay for a fifth of a second
    usleep(200000); // 200,000 microseconds = 0.2 seconds
}
