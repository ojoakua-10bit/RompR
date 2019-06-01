<?php
define('ROMPR_IS_LOADING', true);
include("includes/vars.php");

//
// Check to see if this is a mobile browser
//
if ($skin === null) {
   debuglog("Detecting window size to decide which skin to use....","INIT",4);
   include('checkwindowsize.php');
   exit(0);
}

debuglog("Using skin : ".$skin,"INIT",6);

if (!is_dir('skins/'.$skin)) {
    print '<h3>Skin '.htmlspecialchars($skin).' does not exist!</h3>';
    exit(0);
}

$skinrequires = array();
if (file_exists('skins/'.$skin.'/skin.requires')) {
    debuglog("Loading Skin Requirements File","INIT",9);
    $requires = file('skins/'.$skin.'/skin.requires');
    foreach ($requires as $r) {
        if (substr($r,0,1) != '#') {
            $skinrequires[] = $r;
        }
    }
}

// Workaround bug where this wasn't initialised to a value, meaning an error could be thrown
// on the first inclusion of connection.php
if ($prefs['player_backend'] == '') {
    $prefs['player_backend'] = 'mpd';
}

include("includes/functions.php");
include("international.php");
set_version_string();
include("skins/".$skin."/ui_elements.php");

//
// See if there are any POST values from the setup screen
//

if (array_key_exists('mpd_host', $_POST)) {
    foreach (array('cleanalbumimages', 'do_not_show_prefs') as $p) {
        if (array_key_exists($p, $_POST)) {
            $_POST[$p] = true;
        } else {
            $_POST[$p] = false;
        }
    }
    foreach ($_POST as $i => $value) {
        debuglog("Setting Pref ".$i." to ".$value,"INIT", 3);
        $prefs[$i] = $value;
    }
    setcookie('currenthost',$prefs['currenthost'],time()+365*24*60*60*10,'/');

    $mopidy_slave = false;
    if (property_exists($prefs['multihosts']->{$prefs['currenthost']}, 'mopidy_slave')) {
        $mopidy_slave = $prefs['multihosts']->{$prefs['currenthost']}->mopidy_slave;
    }
    $prefs['multihosts']->{$prefs['currenthost']} = (object) array(
            'host' => $prefs['mpd_host'],
            'port' => $prefs['mpd_port'],
            'password' => $prefs['mpd_password'],
            'socket' => $prefs['unix_socket'],
            'mopidy_slave' => $mopidy_slave,
            'radioparams' => (object) array (
                "radiomode" => "",
                "radioparam" => "",
                "radiomaster" => "",
                "radioconsume" => 0
            )
    );

    $logger->setLevel($prefs['debug_enabled']);
    savePrefs();
}

debuglog($_SERVER['SCRIPT_FILENAME'],"INIT",9);
debuglog($_SERVER['PHP_SELF'],"INIT",9);

//
// Has the user asked for the setup screen?
//

if (array_key_exists('setup', $_REQUEST)) {
    $title = get_int_text("setup_request");
    include("setupscreen.php");
    exit();
}

include("player/mpd/connection.php");
if (!$is_connected) {
    debuglog("MPD Connection Failed","INIT",1);
    $title = get_int_text("setup_connectfail");
    include("setupscreen.php");
    exit();
} else {
    $mpd_status = do_mpd_command("status", true);
    if (array_key_exists('error', $mpd_status)) {
        debuglog("MPD Password Failed or other status failure","INIT",1);
        close_mpd();
        $title = get_int_text("setup_connecterror").$mpd_status['error'];
        include("setupscreen.php");
        exit();
    }
}

//
// Probe to see which type of Player we're using
//
probe_player_type();
setcookie('player_backend',$prefs['player_backend'],time()+365*24*60*60*10,'/');

if ($prefs['unix_socket'] != '') {
    // If we're connected by a local socket we can read the music directory
    $arse = do_mpd_command('config', true);
    if (array_key_exists('music_directory', $arse)) {
        set_music_directory($arse['music_directory']);
    }
}

close_mpd();

//
// See if we can use the SQL backend
//

include( "backends/sql/connect.php");
if (array_key_exists('collection_type', $prefs)) {
    connect_to_database();
} else {
    probe_database();
    include("backends/sql/".$prefs['collection_type']."/specifics.php");
}
if (!$mysqlc) {
    sql_init_fail("No Database Connection Was Possible");
}

savePrefs();

list($result, $message) = check_sql_tables();
if ($result == false) {
    sql_init_fail($message);
}

$collection_type = get_collection_type();

if (array_key_exists('theme', $_REQUEST) && file_exists('themes/'.$_REQUEST['theme'].'.css')) {
    debuglog("Setting theme from request to ".$_REQUEST['theme'],"INIT",5);
    $prefs['usertheme'] = $_REQUEST['theme'].'.css';
}

//
// Do some initialisation and cleanup of the Apache backend
//
include ("includes/firstrun.php");
debuglog("Last Last.FM Sync Time is ".$prefs['last_lastfm_synctime'].", ".date('r', $prefs['last_lastfm_synctime']),"INIT", 6);
debuglog("Initialisation done. Let's Boogie!", "INIT",9);
debuglog("******++++++======------******------======++++++******","CREATING PAGE",3);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
<title>RompЯ</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Expires" content="0" />
<link rel="shortcut icon" sizes="196x196" href="newimages/favicon-196.png" />
<link rel="shortcut icon" sizes="128x128" href="newimages/favicon-128.png" />
<link rel="shortcut icon" sizes="64x64" href="newimages/favicon-64.png" />
<link rel="shortcut icon" sizes="48x48" href="newimages/favicon-48.png" />
<link rel="shortcut icon" sizes="16x16" href="newimages/favicon.ico" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=0" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="mobile-web-app-capable" content="yes" />
<?php
print '<script type="application/json" name="translations">'."\n".json_encode($translations)."\n</script>\n";
$safeprefs = array();
foreach ($prefs as $p => $v) {
    if (!in_array($p, $private_prefs)) {
        $safeprefs[$p] = $v;
    }
}
print '<script type="application/json" name="prefs">'."\n".json_encode($safeprefs)."\n</script>\n";
print '<link rel="stylesheet" type="text/css" href="css/layout-january.css?version='.time().'" />'."\n";
print '<link rel="stylesheet" type="text/css" href="skins/'.$skin.'/skin.css?version='.time().'" />'."\n";
foreach ($skinrequires as $s) {
    $s = trim($s);
    $ext = strtolower(pathinfo($s, PATHINFO_EXTENSION));
    if ($ext == "css") {
        debuglog("Including Skin Requirement ".$s,"INIT",6);
        print '<link rel="stylesheet" type="text/css" href="'.$s.'?version='.time().'" />'."\n";
    }
}
?>
<link rel="stylesheet" id="theme" type="text/css" />
<link rel="stylesheet" id="fontsize" type="text/css" />
<link rel="stylesheet" id="fontfamily" type="text/css" />
<link rel="stylesheet" id="icontheme-theme" type="text/css" />
<link rel="stylesheet" id="icontheme-adjustments" type="text/css" />
<link rel="stylesheet" id="albumcoversize" type="text/css" />
<?php
debuglog("Reconfiguring the Forward Deflector Array","INIT",6);
$scripts = array(
    "jquery/jquery-3.3.1.min.js",
    "jquery/jquery-migrate-3.0.1.js",
    "ui/functions.js",
    "ui/prefs.js",
    "ui/language.js",
    "jquery/jquery-ui.min-19.1.18.js",
    "jshash-2.2/md5-min.js",
    "jquery/imagesloaded.pkgd.min.js",
    "jquery/masonry.pkgd.min.js",
    "includes/globals.js",
    "ui/widgets.js",
    "ui/uihelper.js",
    "skins/".$skin."/skin.js",
    "player/mpd/controller.js",
    "ui/collectionhelper.js",
    "player/player.js",
    "ui/playlist.js",
    "ui/readyhandlers.js",
    "ui/debug.js",
    "ui/uifunctions.js",
    "ui/metahandlers.js",
    "ui/clickfunctions.js",
    "ui/lastfm.js",
    "ui/nowplaying.js",
    "ui/infobar2.js",
    "ui/coverscraper.js",
    "ui/favefinder.js",
    "ui/podcasts.js",
    "browser/info.js"
);
foreach ($scripts as $i) {
    debuglog("Loading ".$i,"INIT",7);
    print '<script type="text/javascript" src="'.$i.'?version='.$version_string.'"></script>'."\n";
}
$inc = glob("streamplugins/*.js");
foreach($inc as $i) {
    debuglog("Loading ".$i,"INIT",7);
    print '<script type="text/javascript" src="'.$i.'?version='.$version_string.'"></script>'."\n";
}

debuglog("Including skins/".$skin.'/skinvars.php',"LAYOUT",7);
include('skins/'.$skin.'/skinvars.php');
include('includes/globals.php');

$inc = glob("browser/helpers/*.js");
foreach($inc as $i) {
    debuglog("Including Browser Helper ".$i,"INIT",7);
    print '<script type="text/javascript" src="'.$i.'?version='.$version_string.'"></script>'."\n";
}
$inc = glob("browser/plugins/*.js");
ksort($inc);
foreach($inc as $i) {
    debuglog("Including Info Panel Plugin ".$i,"INIT",7);
    print '<script type="text/javascript" src="'.$i.'?version='.$version_string.'"></script>'."\n";
}
if ($use_smartradio) {
    $inc = glob("radios/*.js");
    ksort($inc);
    foreach($inc as $i) {
        debuglog("Including Smart Radio Plugin ".$i,"INIT",7);
        print '<script type="text/javascript" src="'.$i.'?version='.$version_string.'"></script>'."\n";
    }
}
if ($use_plugins) {
    $inc = glob("plugins/*.js");
    foreach($inc as $i) {
        debuglog("Including Plugin ".$i,"INIT",7);
        print '<script type="text/javascript" src="'.$i.'?version='.$version_string.'"></script>'."\n";
    }
    if ($prefs['load_plugins_at_loadtime']) {
        $inc = glob("plugins/code/*.js");
        foreach($inc as $i) {
            debuglog("DEVELOPMENT MODE : Including Plugin ".$i,"INIT",2);
            print '<script type="text/javascript" src="'.$i.'?version='.$version_string.'"></script>'."\n";
        }
    }
}
foreach ($skinrequires as $s) {
    $s = trim($s);
    $ext = strtolower(pathinfo($s, PATHINFO_EXTENSION));
    if ($ext == "js") {
        debuglog("Including Skin Requirement ".$s,"INIT",7);
        print '<script type="text/javascript" src="'.$s.'?version='.$version_string.'"></script>'."\n";
    }
}
?>

</head>

<?php
debuglog("Including skins/".$skin.'/skin.php',"LAYOUT",7);
include('skins/'.$skin.'/skin.php');
?>

<audio id="mpdstream" preload="none">
    <source src="/static/gajelas/stream.ogg" type="audio/ogg">
</audio>

<script>
let state = 0;
let audio = $("#mpdstream").get(0);
setInterval(function() {
        if (audio.paused) {
                reload();
        }
        attachControl();
}, 1000);

function reload() {
        audio.load();
        audio.play();
}

$('.stop-button').get(0).onclick = function() { audio.pause(); console.log('stop'); }

$('.play-button').get(0).onclick = function() { setTimeout(reload, 1000); console.log('play'); }

function attachControl() {
        $(".playid").on("click", function() { setTimeout(reload, 1000); console.log('play'); });
}

</script>

</body>
</html>
<?php
debuglog("******++++++======------******------======++++++******","INIT FINISHED",2);
?>
