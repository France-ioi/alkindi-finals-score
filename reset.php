<script
  src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0="   crossorigin="anonymous"></script>
<style>
.borders tr td {
   border: solid black 1px;
}
#copyTarget {
   width: 800px;
}
</style>
<?php

require_once 'connect.php';

if (!isset($_GET["password"])) {
   echo "password missing";
   exit;
}
if ($_GET["password"] != $config->password) {
   echo "wrong password";
   exit;
}

$query = "INSERT INTO archive_attempts SELECT * FROM attempts";
$stmt = $db->prepare($query);
$stmt->execute(array());

$query = "DELETE FROM attempts";
$stmt = $db->prepare($query);
$stmt->execute(array());

$query = "INSERT INTO archive_teams SELECT * FROM teams";
$stmt = $db->prepare($query);
$stmt->execute(array());

$query = "UPDATE teams SET startTime = NULL";
$stmt = $db->prepare($query);
$stmt->execute(array());

$query = "INSERT INTO archive_teams_questions SELECT * FROM teams_questions";
$stmt = $db->prepare($query);
$stmt->execute(array());

$query = "UPDATE teams_questions SET startTime = NULL;";
$stmt = $db->prepare($query);
$stmt->execute(array());

echo "done";