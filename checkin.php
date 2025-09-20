<?php
include_once("config.php");
if (isset($_POST["checkin"]) == false) {
  exit();
}
$push = json_decode($_POST["checkin"], true);
$checkin = json_decode(file_get_contents("https://api.foursquare.com/v2/checkins/" . $push["id"] . "?oauth_token=" . $FOURSQUARE_ACCESS_TOKEN . "&v=20231010"), true);
// スポット名と拒否リストを照合
$venue_name = $push["venue"]["name"];
for ($i = 0; $i < count($DENY_LIST); $i++) {
  if($DENY_LIST[$i] != "" && strpos($venue_name, $DENY_LIST[$i]) !== false){
    exit();
  }
}
// スポットの自治体
$venue_place = "";
$venue_city = $push["venue"]["location"]["city"];
$venue_state = $push["venue"]["location"]["state"];
if ($venue_city != "") {
  $venue_place = $venue_city . ", " . $venue_state;
} else {
  $venue_place = $venue_state;
}
// 共有URL
if (isset($checkin["response"]["checkin"]["checkinShortUrl"])) {
  $url = $checkin["response"]["checkin"]["checkinShortUrl"];
} else {
  $url = "https://app.foursquare.com/share/checkin/" . $push["id"] . "?s=" . $push["id"] . "&lang=ja";
}
// 投稿文生成
if (isset($push["shout"])) {
  $output = $push["shout"] . " (@ " . $venue_name . " in " . $venue_place . ") [swarmapp](" . $url . ")";
} else {
  $output = "I'm at " . $venue_name . " in ". $venue_place . " [swarmapp](" . $url . ")";
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
