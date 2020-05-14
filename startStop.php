<?php

require_once 'connect.php';

if (!isset($_GET["password"])) {
   echo "Missing password parameter";
   exit;
}
if (!isset($_GET["action"])) {
   echo "Missing action parameter";
   exit;
}

if ($_GET["password"] != $config->password) {
   echo "Wrong password";
}

if ($_GET["action"] == "start") {
   rename($config->hiddenFolder, $config->publicFolder);
} else if ($_GET["action"] == "stop") {
   rename($config->publicFolder, $config->hiddenFolder);
}