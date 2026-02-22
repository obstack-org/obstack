<?php

while (ob_get_level()) {
    ob_end_flush();
}

ini_set('zlib.output_compression', 0);
ini_set('output_buffering', 'off');
ini_set('implicit_flush', 1);

ob_implicit_flush(true);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

function msg($msg) {
    echo $msg . "<br>\n";
    echo str_repeat(' ', 4096);
    flush();
}

if (!$_SESSION['sessman']['sa'] && !isset($_SESSION["obsinit"])) {
    die();
}

if (isset($_SESSION['sessman'])) { unset($_SESSION['sessman']); }
if (isset($_SESSION['obsinit'])) { unset($_SESSION['obsinit']); }

usleep(400000);
msg('<br>Updating database...<br>');
sleep(2);

require_once 'class_dbctl.php';

dbctl::run($db);

msg('<br>Done.<br>');
usleep(500000);
msg('<input class="btn" type="submit" value="Continue" style="width:120px; height:35px;" onclick="window.location.reload();"');
