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
    $dom = new DOMDocument("1.0", "UTF-8");
    $html = file_get_contents("https://ja.foursquare.com/v/" . $checkin["response"]["checkin"]["venue"]["id"]);
    @$dom->loadHTML($html);
    $xpath = new DOMXpath($dom);
    // スポット名
    $venue_name = $xpath->query('//div[@class="venueName"]')->item(0)->nodeValue;
    // スポットの自治体
    $venue_place = "";
    $venue_locality = $xpath->query('//span[@itemprop="addressLocality"]')->item(0)->nodeValue;
    $venue_region = $xpath->query('//span[@itemprop="addressRegion"]')->item(0)->nodeValue;
    if ($venue_locality != "") {
      $venue_place = $venue_locality . ", " . $venue_region;
    } else {
      $venue_place = $venue_region;
    }
    // バックアップシステム
    if (empty($venue_name) || empty($venue_place)) {
      // スポット名
      if (strpos($checkin["response"]["checkin"]["venue"]["name"], "(") !== false && strpos($checkin["response"]["checkin"]["venue"]["name"], ")") !== false) {
        $venue_name_split = explode("(", $checkin["response"]["checkin"]["venue"]["name"]);
        if (preg_match("/[ぁ-ん]+|[ァ-ヴー]+|[一-龠]/u", $venue_name_split[0]) || preg_match("/[0-9]/", $venue_name_split[1])) {
          $venue_name = $checkin["response"]["checkin"]["venue"]["name"];
        } else {
          preg_match("{\((.*)\)}", $checkin["response"]["checkin"]["venue"]["name"], $venue_name_match);
          $venue_name = $venue_name_match[1];
        }
      } else {
        $venue_name = $checkin["response"]["checkin"]["venue"]["name"];
      }
      // スポットの自治体
      if (isset($checkin["response"]["checkin"]["venue"]["location"]["formattedAddress"][1]) && strpos($checkin["response"]["checkin"]["venue"]["location"]["formattedAddress"][1], "-") === false) {
        $venue_place = $checkin["response"]["checkin"]["venue"]["location"]["formattedAddress"][1];
      } else {
        $venue_place = $checkin["response"]["checkin"]["venue"]["location"]["formattedAddress"][0];
      }
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
