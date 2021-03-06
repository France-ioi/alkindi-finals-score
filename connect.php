<?php

require_once 'LoggedPDO.php';
require_once 'config_local.php';

function connect_pdo($config) {
   // computing timezone difference with gmt:
   // http://www.sitepoint.com/synchronize-php-mysql-timezone-configuration/
   $now = new DateTime();
   $mins = $now->getOffset() / 60;
   $sgn = ($mins < 0 ? -1 : 1);
   $mins = abs($mins);
   $hrs = floor($mins / 60);
   $mins -= $hrs * 60;
   $offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);
   try {
      $pdo_options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
      $pdo_options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8";
      $connexionString = "mysql:host=".$config->db->mysql->host.";dbname=".$config->db->mysql->database.";charset=utf8;";
      if ($config->db->mysql->logged) {
         $db = new LoggedPDO($connexionString, $config->db->mysql->user, $config->db->mysql->password, $pdo_options);
      } else {
         $db = new PDO($connexionString, $config->db->mysql->user, $config->db->mysql->password, $pdo_options);
      }
      $db->exec("SET time_zone='".$offset."';");
   } catch (Exception $e) {
      die("Erreur : " . $e->getMessage());
   }
   return $db;
}

$db = connect_pdo($config);
