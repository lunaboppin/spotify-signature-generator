<?php
require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV["MYSQL_HOST"];
$user = $_ENV["MYSQL_USERNAME"];
$pass = $_ENV["MYSQL_PASSWORD"];
$data = $_ENV["MYSQL_DATABASE"];
$link_spotify = mysqli_connect($host, $user, $pass, $data) or $error = TRUE;

if(!isset($_GET['uid'])){
    die("No uuid set!");
}

$sql = "SELECT * FROM spotify WHERE uuidshort = '".$_GET['uid']."' LIMIT 1";
$result = $link_spotify->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dbtoken = $row["token"];
        $refreshtoken = $row["refreshtoken"];
        $displayname = $row["displayname"];
        $uuid = $row["uuid"];
        $bg = $row["bg"];
        $bgStr = $row["bgStr"];
        $bgBlur = $row["bgBlur"];
    }
}

function isKanji($str) {
    return preg_match('/[\x{4E00}-\x{9FBF}]/u', $str) > 0;
}

function isHiragana($str) {
    return preg_match('/[\x{3040}-\x{309F}]/u', $str) > 0;
}

function isKatakana($str) {
    return preg_match('/[\x{30A0}-\x{30FF}]/u', $str) > 0;
}

function isJapanese($str) {
    return isKanji($str) || isHiragana($str) || isKatakana($str);
}

$session = new SpotifyWebAPI\Session(
    $_ENV["SPOTIFY_CLIENTID"],
    $_ENV["SPOTIFY_CLIENTSECRET"],
    $_ENV["SPOTIFY_REDIRECTURI"]
);


$options = [
    'auto_refresh' => true,
];

// Use previously requested tokens fetched from somewhere. A database for example.
if ($dbtoken) {
    $session->setAccessToken($dbtoken);
    $session->setRefreshToken($refreshtoken);
} else {
    // Or request a new access token
    $session->refreshAccessToken($refreshtoken);
}

$options = [
    'auto_refresh' => true,
];

$api = new SpotifyWebAPI\SpotifyWebAPI($options, $session);

// You can also call setSession on an existing SpotifyWebAPI instance
$api->setSession($session);

$artist = isset($api->getMyCurrentTrack()->item->album->artists[0]->name) ? $api->getMyCurrentTrack()->item->album->artists[0]->name : '';
$songName = isset($api->getMyCurrentTrack()->item->name) ? $api->getMyCurrentTrack()->item->name : 'Paused';
$albumCover = isset($api->getMyCurrentTrack()->item->album->images[2]->url) ? $api->getMyCurrentTrack()->item->album->images[2]->url : '/var/www/spotify.arnux.net/none.png';
$link = isset($api->getMyCurrentTrack()->item->album->external_urls->spotify) ? $api->getMyCurrentTrack()->item->album->external_urls->spotify : 'https://spotify.arnux.net/';

if(isset($_GET["goto"])){
    header("Location: ".$link);
    die($link);
}

use Intervention\Image\ImageManagerStatic as Image;

$img = Image::canvas(500, 150, '#212121');
if($bg == "1"){
    $img->insert("/var/www/spotify.arnux.net/storage/".$bgStr, 'top-left', 0, 0)->blur($bgBlur);
}
$img->insert($albumCover, 'bottom-right', 10, 7);
$img->insert("/var/www/spotify.arnux.net/spotify.png", 'top-right', 10, 10);

if(!isJapanese($artist)){
    $textLength = 40;
} else {
    $textLength = 60;
}
if(!isJapanese($songName)){
    $textLengthName = 40;
} else {
    $textLengthName = 60;
}

$artistEllipsed = strlen($artist) > $textLength ? substr($artist,0,$textLength)."..." : $artist;
$songEllipsed = strlen($songName) > $textLengthName ? substr($songName,0,$textLengthName)."..." : $songName;

$img->text($displayname, 10, 12, function($font) {
    $font->file("/var/www/spotify.arnux.net/SawarabiGothic-Regular.ttf");
    $font->size(18);
    $font->color('#fdf6e3');
    $font->align('left');
    $font->valign('top');
    $font->angle(0);
});

$img->text($artistEllipsed, 10, 96, function($font) {
    $font->file("/var/www/spotify.arnux.net/SawarabiGothic-Regular.ttf");
    $font->size(20);
    $font->color('#fdf6e3');
    $font->align('left');
    $font->valign('top');
    $font->angle(0);
});

$img->text($songEllipsed, 10, 120, function($font) {
    $font->file("/var/www/spotify.arnux.net/SawarabiGothic-Regular.ttf");
    $font->size(20);
    $font->color('#fdf6e3');
    $font->align('left');
    $font->valign('top');
    $font->angle(0);
});

header('Content-Type: image/png');
echo $img->encode('png');

// Remember to grab the tokens afterwards, they might have been updated
$newAccessToken = $session->getAccessToken();
$newRefreshToken = $session->getRefreshToken(); // Sometimes, a new refresh token will be returned

if($newAccessToken !== $dbtoken || $newRefreshToken !== $refreshtoken){
    $sql2 = "UPDATE spotify SET token = '".$newAccessToken."', refreshtoken = '".$newRefreshToken."' WHERE uuidshort = '".$_GET['uid']."'";
    $result2 = $link_spotify->query($sql2);
}
?>
