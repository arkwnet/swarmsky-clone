<?php
include_once("config.php");
if (isset($_POST["checkin"]) == false) {
  exit();
}
$push = json_decode($_POST["checkin"], true);
$checkin = json_decode(file_get_contents("https://api.foursquare.com/v2/checkins/" . $push["id"] . "?oauth_token=" . $FOURSQUARE_ACCESS_TOKEN . "&v=20231010"), true);
if (isset($checkin["response"]["checkin"]["shares"]["twitter"])) {
  if ($checkin["response"]["checkin"]["shares"]["twitter"] == true) {
    $output = "";
    // スポット名
    $venue_name = "";
    if (false !== strpos($checkin["response"]["checkin"]["venue"]["name"], "(") && false !== strpos($checkin["response"]["checkin"]["venue"]["name"], ")")) {
      $venue_name_split = explode("(", $checkin["response"]["checkin"]["venue"]["name"]);
      if (preg_match("/[ぁ-ん]+|[ァ-ヴー]+|[一-龠]/u", $venue_name_split[0])) {
        $venue_name = $checkin["response"]["checkin"]["venue"]["name"];
      } else {
        preg_match("{\((.*)\)}", $checkin["response"]["checkin"]["venue"]["name"], $venue_name_match);
        $venue_name = $venue_name_match[1];
      }
    } else {
      $venue_name = $checkin["response"]["checkin"]["venue"]["name"];
    }
    // スポットの自治体
    $venue_place = "";
    if (isset($checkin["response"]["checkin"]["venue"]["location"]["formattedAddress"][1])) {
      $venue_place = $checkin["response"]["checkin"]["venue"]["location"]["formattedAddress"][1];
    } else {
      $venue_place = $checkin["response"]["checkin"]["venue"]["location"]["formattedAddress"][0];
    }
    // 投稿文生成
    if (isset($checkin["response"]["checkin"]["shout"])) {
      $output = $checkin["response"]["checkin"]["shout"] . " (@ " . $venue_name . " in " . $venue_place . ") [swarmapp](" . $checkin["response"]["checkin"]["checkinShortUrl"] . ")";
    } else {
      $output = "I'm at " . $venue_name . " in ". $venue_place . " [swarmapp](" . $checkin["response"]["checkin"]["checkinShortUrl"] . ")";
    }
    // Misskey APIでポスト
    if ($output != "") {
      $params = [
        "i" => $MISSKEY_API_TOKEN,
        "text" => $output
      ];
      $params_json = json_encode($params);
      $headers = [
        "Content-Type: application/json",
        "Accept-Charset: UTF-8",
      ];
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://" . $MISSKEY_HOST . "/api/notes/create");
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $params_json);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      $result = curl_exec($ch);
      curl_close($ch);
    }
  }
}
