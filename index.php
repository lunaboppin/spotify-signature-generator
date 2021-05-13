<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>arnux - spotify signature</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Montserrat:400,400i,700,700i,600,600i">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/baguettebox.js/1.10.0/baguetteBox.min.css">
    <link rel="stylesheet" href="assets/css/smoothproducts.css">
    <script src="https://cdn.lr-ingest.io/LogRocket.min.js" crossorigin="anonymous"></script>
    <script>window.LogRocket && window.LogRocket.init('vvsocr/arnux');</script>
</head>

<script>
    if(window.history.replaceState){
      window.history.replaceState( null, null, window.location.href );
    }
    var slider = document.getElementById("blurRange");
    var output = document.getElementById("blurAmount");
    output.innerHTML = slider.value;

    slider.oninput = function() {
      output.innerHTML = this.value;
    }
</script>

<body>
<?php
require 'vendor/autoload.php';
use Ramsey\Uuid\Uuid;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$session = new SpotifyWebAPI\Session(
    $_ENV["SPOTIFY_CLIENTID"],
    $_ENV["SPOTIFY_CLIENTSECRET"],
    $_ENV["SPOTIFY_REDIRECTURI"]
);

$api = new SpotifyWebAPI\SpotifyWebAPI();

$host = $_ENV["MYSQL_HOST"];
$user = $_ENV["MYSQL_USERNAME"];
$pass = $_ENV["MYSQL_PASSWORD"];
$data = $_ENV["MYSQL_DATABASE"];
$link_spotify = mysqli_connect($host, $user, $pass, $data) or $error = TRUE;

session_start();

function IntToYesNo($int){
    if($int == "1"){
        return "Yes";
    } else {
        return "No";
    }
}

if (isset($_GET['code']) && !isset($_SESSION["uuid"])) {
    $session->requestAccessToken($_GET['code']);
    $api->setAccessToken($session->getAccessToken());

    $uuid = Uuid::uuid5(Uuid::NAMESPACE_URL, $api->me()->email);
    $uuidshort = substr($uuid, 0, 8);
    $_SESSION["uuid"] = $uuid;
    $sql = "INSERT INTO spotify(token, refreshtoken, email, uuid, uuidshort) VALUES('".$session->getAccessToken()."', '".$session->getRefreshToken()."', '".$api->me()->email."', '".$uuid."', '".$uuidshort."') ON DUPLICATE KEY UPDATE refreshtoken = '".$session->getAccessToken()."', refreshtoken = '".$session->getRefreshToken()."', email = '".$api->me()->email."'";
    $result = $link_spotify->query($sql);
    $_SESSION["acpactive"] = false;
    header("Location: https://spotify.arnux.net");
} elseif(isset($_SESSION["uuid"])) {
    $sql = "SELECT * FROM spotify WHERE uuid = '".$_SESSION["uuid"]."' LIMIT 1";
    $result = $link_spotify->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $token = $row["token"];
            $refreshtoken = $row["refreshtoken"];
            $email = $row["email"];
            $uuidshort = $row["uuidshort"];
            $displayname = $row["displayname"];
            $bgBlurCurrent =  isset($row["bgBlur"]) ? $row["bgBlur"] : 25;
            $_SESSION['admin'] = $row["admin"];

            if ($token) {
                $session->setAccessToken($token);
                $session->setRefreshToken($refreshtoken);
            } else {
                // Or request a new access token
                $session->refreshAccessToken($refreshtoken);
            }
            $newAccessToken = $session->getAccessToken();
            $newRefreshToken = $session->getRefreshToken();
            if($newAccessToken !== $token || $newRefreshToken !== $refreshtoken){
                $sql2 = "UPDATE spotify SET token = '".$newAccessToken."', refreshtoken = '".$newRefreshToken."' WHERE uuid = '".mysqli_real_escape_string($link_spotify, $_SESSION["uuid"])."'";
                $result2 = $link_spotify->query($sql2);
            }
            function is_admin(){
                if($_SESSION['admin'] == "1"){
                    return true;
                } else {
                    return false;
                }
            }
            if(isset($_POST['displayname'])){
                if($_POST['displayname'] == "" || $_POST['displayname'] == " "){
                    session_start();
                    $_SESSION['error'] = "invalid";
                    header("Location: https://spotify.arnux.net");
                    return;
                }
                $displaytemp = mysqli_real_escape_string($link_spotify, $_POST['displayname']);
                $sql3 = "UPDATE spotify SET displayname = '".mysqli_real_escape_string($link_spotify, $displaytemp)."' WHERE uuid = '".$_SESSION["uuid"]."'";
                $result3 = $link_spotify->query($sql3);
                $displayname = $_POST['displayname'];
            }
            if(isset($_POST['changename'])){
                $sql3 = "UPDATE spotify SET displayname = NULL WHERE uuid = '".mysqli_real_escape_string($link_spotify, $_SESSION["uuid"])."'";
                $result3 = $link_spotify->query($sql3);
                $displayname = NULL;
            }
            if(isset($_POST["logout"])){
                session_destroy();
                header("Location: https://spotify.arnux.net");
                exit();
            }
            if(isset($_POST["acp"])){
                $_SESSION["acpactive"] = true;
                header("Location: https://spotify.arnux.net");
                die();
            }
            if(isset($_POST["exitacp"])){
                $_SESSION["acpactive"] = false;
                header("Location: https://spotify.arnux.net");
                die();
            }
            if(isset($_POST["admin_displayname"])){
                $name = $_POST["admin_displayname"];
                $uuid = $_POST["admin_uuid"];
                $admin = $_POST["admin_isadmin"];
                $sql3 = "UPDATE spotify SET displayname = '".$name."', admin = '".$admin."' WHERE uuid = '".mysqli_real_escape_string($link_spotify, $uuid)."'";
                $result3 = $link_spotify->query($sql3);
            }
            if(isset($_POST["deleteacc3"])){
                $sql3 = "DELETE FROM spotify WHERE uuid = '".$_POST["deleteacc3"]."'";
                $result3 = $link_spotify->query($sql3);
                header("Location: https://spotify.arnux.net");
                exit();
            }
            if(isset($_POST["admin_delacc"])){
                $name = $_POST["admin_delacc"];
                echo '<main class="page registration-page">
                    <section class="clean-block clean-form dark">
                        <div class="container">
                            <div class="block-heading">
                                <h2 class="text-info">arnux.net</h2>
                                <p>Spotify Signature delete account</p>
                            </div>
                            <form action="" method="POST">
                                <h2>Are you sure you want to delete this account? ('.$name.')</h2>
                                <br><button class="btn btn-danger btn-block" type="submit" name="deleteacc3" id="deleteacc3" value="'.$name.'">Delete Account</button>
                                <button class="btn btn-link btn-block" type="submit" name="goback" id="goback">Go back</button>
                            </form>
                        </div>
                    </section>
                </main>';
                die();
            }
            if(isset($_POST["admin_editacc"])){
                $sql = "SELECT * FROM spotify WHERE uuidshort = '".$_POST["admin_editacc"]."' LIMIT 1";
                $result = $link_spotify->query($sql);
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $name = $row["displayname"];
                        $admin = $row["admin"];
                        $uuid = $row["uuid"];
                    }
                }
                echo '<main class="page registration-page">
                    <section class="clean-block clean-form dark">
                        <div class="container">
                            <div class="block-heading">
                                <h2 class="text-info">arnux.net</h2>
                                <p>Spotify Signature Edit Account</p>
                            </div>
                            <form action="" method="POST">
                                <h2>Editing user \''.$_POST["admin_editacc"].'\'</h2>
                                <span textfor="displayname">Display Name</span>
                                <input class="form-control item" type="text" id="admin_displayname" name="admin_displayname" maxlength="45" value="'.$name.'"><br>
                                <span textfor="displayname">Admin</span>
                                <input class="form-control item" type="text" id="admin_isadmin" name="admin_isadmin" value="'.$admin.'"><br>
                                <input type="hidden" name="admin_uuid" value="'.$uuid.'">
                                <button class="btn btn-primary btn-block" type="submit">Submit</button>
                                <button class="btn btn-link btn-block" type="submit" name="goback" id="goback">Go back</button>
                            </form>
                        </div>
                    </section>
                </main>';
                die();
            }
            if($_SESSION["acpactive"] == true){
                echo '<main class="page registration-page">
                    <section class="clean-block clean-form dark">
                        <div class="container">
                            <div class="block-heading">
                                <h2 class="text-info">arnux.net</h2>
                                <img width="64px" height="55px" src="https://i.chimame.co.uk/p760j71c37.png"></img><p>Spotify Signature AdminCP</p>
                            </div>
                            <form action="" method="POST">
                            <table class="table">
                                  <thead>
                                    <tr>
                                      <th scope="col">UUID</th>
                                      <th scope="col">Display Name</th>
                                      <th scope="col">Email</th>
                                      <th scope="col">Admin</th>
                                      <th scope="col">Edit</th>
                                    </tr>
                                  </thead>
                                  <tbody>';
                                  $sql = "SELECT * FROM spotify";
                                  $result = $link_spotify->query($sql);
                                      if ($result->num_rows > 0) {
                                          while ($row = $result->fetch_assoc()) {
                                            echo '<tr>
                                              <th scope="row">'.$row["uuidshort"].'</th>
                                              <td>'.$row["displayname"].'</td>
                                              <td>'.$row["email"].'</td>
                                              <td>'.IntToYesNo($row["admin"]).'</td>
                                              <td><button style="padding: 0; border: none; background: none;" type="submit" name="admin_editacc" value="'.$row["uuidshort"].'" id="admin_editacc"><img src="assets/img/icons/edit.png" width="32" height="32"></button></img><button style="padding: 0; border: none; background: none;" type="submit" name="admin_delacc" id="admin_delacc" value="'.$row["uuid"].'"><img src="assets/img/icons/delete.png" width="32" height="32"></img></button></td>
                                            </tr>';
                                        }
                                    }
                                  echo '</tbody>
                                </table>
                                <button class="btn btn-link btn-block" type="submit" name="exitacp" id="exitacp">Exit AdminCP</button>
                            </form>
                        </div>
                    </section>
                </main>';
                die();
            }
            if(isset($_POST["deleteacc2"])){
                $sql3 = "DELETE FROM spotify WHERE uuid = '".$_SESSION["uuid"]."'";
                $result3 = $link_spotify->query($sql3);
                session_destroy();
                header("Location: https://spotify.arnux.net");
                exit();
            }
            if(isset($_POST["rembgn"])){
                $sql = "SELECT * FROM spotify WHERE uuid = '".$_SESSION["uuid"]."'";
                $result = $link_spotify->query($sql);
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        unlink("/var/www/spotify.arnux.net/storage/".$row["bgStr"]);
                    }
                }
                $sql3 = "UPDATE spotify SET bg = '0', bgStr = '' WHERE uuid = '".$_SESSION["uuid"]."'";
                $result3 = $link_spotify->query($sql3);
            }
            if(isset($_POST["setbgbtn"])){
                $errors = array();
                var_dump($_FILES['bgupload1']['tmp_name']);
                if(file_exists($_FILES['bgupload1']['tmp_name']) || is_uploaded_file($_FILES['bgupload1']['tmp_name'])){
                    $file_name = $_SESSION["uuid"];
                    $file_size = $_FILES['bgupload1']['size'];
                    $file_tmp = $_FILES['bgupload1']['tmp_name'];
                    $file_type = $_FILES['bgupload1']['type'];
                    $file_ext = strtolower(end(explode('.',$_FILES['bgupload1']['name'])));
                    $extensions = array("jpeg","jpg","png","gif");
                    if(in_array($file_ext, $extensions) === false){
                        $errors[] = "extension not allowed, please choose a JPEG, PNG or GIF file.";
                    }
                    list($width, $height) = getimagesize($file_tmp);
                    if($width > "500" || $height > "150"){
                        $errors[] = 'Image width and height must be exactly 500x150';
                    }
                    if($file_size > 2097152){
                        $errors[] = 'File size must be exactly 2 MB';
                    }
                    $blur = $_POST["blurRange"];

                    if(empty($errors) == true){
                        move_uploaded_file($file_tmp,"storage/".$file_name.".".$file_ext);
                        $sql3 = "UPDATE spotify SET bg = '1', bgStr = '".$file_name.".".$file_ext."', bgBlur = '".mysqli_real_escape_string($link_spotify, intval($blur))."' WHERE uuid = '".$_SESSION["uuid"]."'";
                        $result3 = $link_spotify->query($sql3);
                        echo "Success";
                    }else{
                        print_r($errors);
                    }
                } else {
                    $blur = $_POST["blurRange"];
                    $sql3 = "UPDATE spotify SET bgBlur = '".mysqli_real_escape_string($link_spotify, intval($blur))."' WHERE uuid = '".$_SESSION["uuid"]."'";
                    $result3 = $link_spotify->query($sql3);
                    echo "Success";
                }
            }
            if(isset($_POST["deleteacc"])){
                echo '<main class="page registration-page">
                    <section class="clean-block clean-form dark">
                        <div class="container">
                            <div class="block-heading">
                                <h2 class="text-info">arnux.net</h2>
                                <p>Spotify Signature delete account</p>
                            </div>
                            <form action="" method="POST">
                                <h2>Are you sure you want to delete your account?</h2>
                                <br><button class="btn btn-danger btn-block" type="submit" name="deleteacc2" id="deleteacc2">Delete Account</button>
                                <button class="btn btn-link btn-block" type="submit" name="goback" id="goback">Go back</button>
                            </form>
                        </div>
                    </section>
                </main>';
                die();
            }
            if(isset($_POST["setbground"])){
                echo '<main class="page registration-page">
                    <section class="clean-block clean-form dark">
                        <div class="container">
                            <div class="block-heading">
                                <h2 class="text-info">arnux.net</h2>
                                <p>Spotify Signature Set Background</p>
                            </div>
                            <form action="" method="POST" enctype="multipart/form-data">
                                <h2>Change your background</h2><br>
                                <label for="bgupload1">Size must be exactly: 500x150</label>
                                <input type="file" class="form-control-file" id="bgupload1" name="bgupload1">
                                <br><label for="blurRange">Blur amount:</label>
                                <br><input type="range" min="0" max="100" value="'.$bgBlurCurrent.'" class="slider" id="blurRange" name="blurRange" style="width: 920px;">
                                <p>Value: <span id="blurAmount"></span></p>
                                <br><button class="btn btn-primary btn-block" type="submit" name="setbgbtn" id="setbgbtn">Set Background</button>
                                <button class="btn btn-danger btn-block" type="submit" name="rembgn" id="rembgn">Remove Background</button>
                                <button class="btn btn-link btn-block" type="submit" name="goback" id="goback">Go back</button>
                            </form>
                        </div>
                    </section>
                </main>';
                die();
            }
            if(!isset($displayname)){
                session_start();
                if(isset($_SESSION['error'])){
                    $error = $_SESSION['error'];
                    if($error == "invalid"){
                        echo '<div class="alert alert-danger" role="alert">
                          You must provide a username!
                        </div>';
                    }
                    $_SESSION['error'] = "";
                }
                echo '<main class="page registration-page">
                    <section class="clean-block clean-form dark">
                        <div class="container">
                            <div class="block-heading">
                                <h2 class="text-info">arnux.net</h2>
                                <p>Spotify Signature display name</p>
                            </div>
                            <form action="" method="POST">
                                <h2>Please enter a username</h2>
                                <label for="displayname">Display name:</label><br>
                                <input class="form-control item" type="text" id="displayname" name="displayname" maxlength="45"><br>
                                <button class="btn btn-primary btn-block" type="submit">Submit</button>
                            </form>
                        </div>
                    </section>
                </main>';
            } else {
                if ($dbtoken) {
                    $session->setAccessToken($token);
                    $session->setRefreshToken($refreshtoken);
                } else {
                    // Or request a new access token
                    $session->refreshAccessToken($refreshtoken);
                }
                $api->setSession($session);
                $artist = isset($api->getMyCurrentTrack()->item->album->artists[0]->name) ? $api->getMyCurrentTrack()->item->album->artists[0]->name : '';
                $songName = isset($api->getMyCurrentTrack()->item->name) ? $api->getMyCurrentTrack()->item->name : 'Paused';
                $albumCover = isset($api->getMyCurrentTrack()->item->album->images[2]->url) ? $api->getMyCurrentTrack()->item->album->images[2]->url : '/var/www/spotify.arnux.net/none.png';
                $regpage = '<main class="page registration-page">
                    <section class="clean-block clean-form dark">
                        <div class="container">
                            <div class="block-heading">
                                <h2 class="text-info">arnux.net</h2>
                                <p>Spotify Signature</p>
                            </div>
                            <form action="" method="POST">
                                <center><h2>Your signature</h2>
                                <img src="https://spotify.arnux.net/img.php?uid='.$uuidshort.'" width="630"></img><br><br>'.htmlspecialchars('<img src="https://spotify.arnux.net/img.php?uid='.$uuidshort.'"></img>').'<br><br>
                                [img]https://spotify.arnux.net/img.php?uid='.$uuidshort.'[/img]<br><br>
                                You are listening to:<br>
                                '.$songName.'<br>
                                by '.$artist.'
                                </center>
                                <br><br><button class="btn btn-danger btn-block" type="submit" name="deleteacc" id="deleteacc">Delete Account</button>
                                <button class="btn btn-primary btn-block" type="submit" name="logout" id="logout">Logout</button>
                                <button class="btn btn-primary btn-block" type="submit" name="setbground" id="setbground">Change Background</button>
                                <button class="btn btn-link btn-block" type="submit" name="changename" id="changename">Change display name</button>';
                                if(is_admin()){
                                    $regpage .= '<button class="btn btn-link btn-block" type="submit" name="acp" id="acp">Go to AdminCP</button>';
                                }
                            $regpage .= '</form>
                        </div>
                    </section>
                </main>';
                echo $regpage;
            }
        }
    }
} else {
    $options = [
        'scope' => [
            'user-read-email',
            'user-read-playback-state',
            'user-read-currently-playing'
        ],
    ];

    echo '<main class="page registration-page">
        <section class="clean-block clean-form dark">
            <div class="container">
                <div class="block-heading">
                    <h2 class="text-info">arnux.net</h2>
                    <p>Login</p>
                </div>
                <form action="" method="POST">
                    <center><a class="btn btn-primary" href="'.$session->getAuthorizeUrl($options).'" role="button">Login with Spotify</a>
                    <br><br><h6>We only need your email and playback history for this service to work.</h6></center>
                </form>
            </div>
        </section>
    </main>';
    die();
}
?>
<script src="assets/js/jquery.min.js"></script>
<script src="assets/bootstrap/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/baguettebox.js/1.10.0/baguetteBox.min.js"></script>
<script src="assets/js/smoothproducts.min.js"></script>
<script src="assets/js/theme.js"></script>
</body>

</html>
