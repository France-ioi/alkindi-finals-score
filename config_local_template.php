<?php

global $config;
$config = (object) array();
$config->db = (object) array();
$config->db->mysql = (object) array();

$config->db->mysql->host = '127.0.0.1';
$config->db->mysql->database = 'alkindi_finale';
$config->db->mysql->user = 'root';
$config->db->mysql->password = '';
$config->db->mysql->logged = false;

$config->salt = 0;
$config->taskUrkPrefix = "https://concours-alkindi.fr/finale2020/";

