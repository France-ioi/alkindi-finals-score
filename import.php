<?php

require_once 'connect.php';

echo "<h1>Finale du concours Alkindi 2018</h1>";

echo "<h2>Import</h2>";

$query = "INSERT IGNORE INTO teams (ID, name, startTime) SELECT teamID, teamName, NULL FROM import_data";
$stmt = $db->prepare($query);
$stmt->execute([]);

for ($question = 1; $question <= 7; $question++) {
   echo "Import question ".$question."<br/>";
   $query = "INSERT INTO teams_questions (teamID, question, expectedAnswer, startTime) SELECT teamID, ".$question.", answer".$question.", NULL FROM import_data";
   $stmt = $db->prepare($query);
   $stmt->execute(array());
}
