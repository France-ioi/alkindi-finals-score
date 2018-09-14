
<style>
.borders tr td {
   border: solid black 1px;
}
</style>
<?php

require_once 'connect.php';

echo "<h1>Finale du concours Alkindi 2018</h1>";

echo "<h2>Soumettre une réponse</h2>";

echo "<form action='index.php' method='post'>";
echo "<p>Code à 5 chiffres : <input type='number' name='code'></p>";
echo "<p>Réponse proposée : <input type='text' name='answer'></p>";
echo "<p>Pénalités : <input type='checkbox' name='noPenalty'> annuler une pénalité de 5 minutes</p>";
echo "<button type='submit'>Valider</button>";

if (isset($_POST["code"])) {
   echo "<h2>Résultat</h2>";
   $code = trim($_POST["code"]);
   if ($code != "") {
      $answer = trim($_POST["answer"]);
      $penaltySeconds = 0;
      if (isset($_POST["noPenalty"])) {
         $penaltySeconds = -300;
      }
      $teamID = $code % 27;
      $question = $code % 8;
      $query = "SELECT ID, expectedAnswer, question FROM teams_questions WHERE question = :question AND teamID = :teamID";
      $stmt = $db->prepare($query);
      $stmt->execute(array("question" => $question, "teamID" => $teamID));
      if (($row = $stmt->fetchObject()) && ($code % 457 == 0)) {
         $isValid = 0;
         if ($row->expectedAnswer == $answer) {
            $isValid = 1;
         } else {
            $penaltySeconds += 300;
         }
         $queryDuplicate = "SELECT count(*) as nb FROM attempts WHERE questionID = :questionID AND answer = :answer";
         $stmt = $db->prepare($queryDuplicate);
         $stmt->execute(array("questionID" => $row->ID, "answer" => $answer));
         echo "<p><b>Question : ".$row->question."</b></p>";
         $rdup = $stmt->fetchObject();
         if ($rdup->nb > 0) {
            echo "<p style='color:red'><b>Réponse déjà soumise.</b></p>";
         } else {
            $query2 = "INSERT INTO attempts (questionID, answerTime, answer, isValid, penaltySeconds) VALUES
            (:questionID, NOW(), :answer, :isValid, :penaltySeconds)";
            $stmt = $db->prepare($query2);
            $stmt->execute(array("questionID" => $row->ID, "answer" => $answer, "isValid" => $isValid, "penaltySeconds" => $penaltySeconds));
         }
         echo "<p>Équipe : ".$teamID."</p>";
         if ($isValid) {
            $query3 = "UPDATE teams_questions SET startTime = NOW() WHERE teamID = :teamID AND question = :question AND startTime IS NULL";
            $stmt = $db->prepare($query3);
            $stmt->execute(array("question" => ($question + 1), "teamID" => $teamID));
            echo "<p><b style='background-color:#8F8'>Réponse correcte. Bravo !</b></p>";
         } else {
            echo "<p><b style='background-color:#F88'>Réponse ".$answer." invalide. Réponse attendue : ".$row->expectedAnswer."</b></p>";
         }
      } else {
         echo "<p><b>Le code de sujet ".$code." est invalide !</b></p>";
      }
   }
}

$query = "SELECT teams.ID FROM teams";
$stmt = $db->prepare($query);
$stmt->execute(array());

$nbQuestions = 10;
$started_teams = array();
$started_questions = array();
while ($row = $stmt->fetchObject()) {
   if (isset($_POST["start_team_".$row->ID])) {
      $started_teams[] = $row->ID;
   }
   for ($question = 1; $question <= $nbQuestions; $question++) {
      if (isset($_POST["start_question_".$row->ID."_".$question])) {
         $started_questions[] = array("teamID" => $row->ID, "question" => $question);
      }
   }
}

if (count($started_teams) > 0) {
   $query = "UPDATE teams SET startTime=NOW() WHERE ID IN (".implode(",", $started_teams).")";
   $stmt = $db->prepare($query);
   $stmt->execute(array());
   $query = "UPDATE teams_questions SET startTime = NOW() WHERE teamID IN (".implode(",", $started_teams).") AND question = '1'";
   $stmt = $db->prepare($query);
   $stmt->execute(array());      
}

if (count($started_questions) > 0) {
   echo "<h2>Démarrage de questions :</h2>" ;
   $query = "SELECT teams.name, teams_questions.teamID, teams_questions.ID as questionID, TIME_TO_SEC(TIMEDIFF(NOW(), teams_questions.startTime)) as timeStartedSeconds, MAX(attempts.isValid) as isValid FROM
      teams_questions
      JOIN teams ON teams_questions.teamID = teams.ID
      LEFT JOIN attempts ON attempts.questionID = teams_questions.ID
      WHERE teams_questions.question = (:question - 1) AND teams_questions.teamID = :teamID
      GROUP BY teams_questions.ID
      ";
   $stmt = $db->prepare($query);
   foreach ($started_questions as $startedQuestion) {
      $stmt->execute($startedQuestion);
      if ($row = $stmt->fetchObject()) {
         if (($row->timeStartedSeconds < 30*60) && ($row->isValid == 0)) {
            echo "<p style='color:red;font-weight:bold'>Erreur : l'équipe ".$row->name." a commencé la question ".($startedQuestion["question"] - 1)." il y a ".floor($row->timeStartedSeconds / 60)." minutes seulement. Elle ne peut pas démarrer la suivante.";
         } else {
            $queryStartQuestion = "UPDATE teams_questions SET startTime = NOW() WHERE teamID = :teamID AND question = :question AND startTime IS NULL";
            $stmt2 = $db->prepare($queryStartQuestion);
            $stmt2->execute($startedQuestion);
            echo "<p>OK : l'équipe ".$row->name." peut commencer la question ".$startedQuestion["question"]."</p>";
         }
      } else {
         echo "<p>Erreur: pas de question précédente.".json_encode($startedQuestion)."</p>";
      }
   }
}

$query = "SELECT count(DISTINCT question) as nb FROM teams_questions";
$stmt = $db->prepare($query);
$stmt->execute(array());
$row = $stmt->fetchObject();
$maxQuestion = $row->nb;

$query = "SELECT teams.ID, teams.name, teams.startTime, teams_questions.question, MAX(TIMEDIFF(attempts.answerTime, teams_questions.startTime)) as answerTime,
MAX(TIMEDIFF(NOW(), teams_questions.startTime)) as workTime,
 MAX(TIME_TO_SEC(TIMEDIFF(attempts.answerTime, teams_questions.startTime))) as answerTimeSeconds,
teams_questions.startTime as questionStartTime,
MAX(attempts.isValid) as isValid,
SUM(attempts.penaltySeconds) as penaltySeconds,
count(attempts.ID) as nbAttempts
FROM teams
JOIN teams_questions ON teams_questions.teamID = teams.ID
LEFT JOIN attempts ON teams_questions.ID = attempts.questionID
GROUP BY teams_questions.ID
ORDER BY teams.startTime ASC, teams.name ASC, attempts.answerTime";

$stmt = $db->prepare($query);
$stmt->execute(array());

$teams = array();
$lastTeamID = 0;
while ($row = $stmt->fetchObject()) {
   if ($row->ID != $lastTeamID) {
      $teams[$row->ID] = $row;
      $teams[$row->ID]->questions = array();
      $teams[$row->ID]->score = 0;
      $teams[$row->ID]->lastAnswerTime = NULL;
      $teams[$row->ID]->totalPenaltySeconds = 0;
      $teams[$row->ID]->totalTimeSeconds = 0;
      $lastQuestion = 0;
   }
   if ($row->question != null) {
      $teams[$row->ID]->questions[$row->question] = $row;
      if ($row->answerTime != null) {
         if ($row->isValid) {
            $teams[$row->ID]->score++;
            $teams[$row->ID]->lastAnswerTime = $row->answerTime;
            $teams[$row->ID]->totalPenaltySeconds += intval($row->penaltySeconds);
            $teams[$row->ID]->totalTimeSeconds += intval($row->answerTimeSeconds);
         }
      }
      $lastQuestion = $row->question;
   }
   $lastTeamID = $row->ID;
}

echo "<h2>Équipes</h2>";

echo "<table class='borders' cellspacing=0>";
echo "<tr style='font-weight:bold'><td>Départ</td><td>Nom de l'équipe</td><td>Score</td><td>Temps</td><td>Détail temps</td>";
for ($question = 1; $question <= $maxQuestion; $question++) {
   echo "<td>Question ".$question."</td>";
}
echo "</tr>";

foreach ($teams as $ID => $team) {
   echo "<tr><td>";
   if ($team->startTime == NULL) {
      echo "<input type='checkbox' name='start_team_".$ID."' />";
   } else {
      echo $team->startTime;
   }
   echo "</td><td>".$ID." - ".$team->name."</td>";
   echo "<td>".$team->score."</td>";
   $seconds = $team->totalTimeSeconds + $team->totalPenaltySeconds;
   $hours = floor($seconds / 3600);
   $minutes = floor(($seconds % 3600) / 60);
   $seconds = $seconds % 60;
   echo "<td style='text-align:right'>".sprintf("%02dh%02dm%02ds", $hours, $minutes, $seconds)."</td>";
   echo "<td>".$team->totalTimeSeconds." + ".$team->totalPenaltySeconds."</td>";
   $lastQuestion = -1;
   for ($question = 1; $question <= $maxQuestion; $question++) {
      echo "<td style='width:30px'>";
      if (isset($team->questions[$question]) && ($team->questions[$question]->questionStartTime != NULL)) {
         $data = $team->questions[$question];
         if ($data->isValid) {
            $color = '#8F8';
         } else if ($data->nbAttempts > 0) {
            $color = '#F88';
         } else {
            $color = '#888';
         }
         echo "<div title='".$data->answerTime."-".$data->workTime."' style='background-color:".$color."'>";
         echo $data->nbAttempts." ";
         if (($data->isValid) || ($data->nbAttempts > 0)) {
            if ($data->isValid) {
               echo "OK";
            } else {
               echo "Non";
            }
            echo " ".$data->penaltySeconds."s";
         }
         $lastQuestion = intval($question);
         echo "</div>";
      } else if ($question == $lastQuestion + 1) {
         echo "<div style='width:100px'><input type='checkbox' name='start_question_".$ID."_".$question."' />&nbsp;démarrer</div>";
      } else {
         echo "&nbsp;";
      }
      echo "</td>";
   }
   echo "</tr>";
}
echo "</table>";

echo "<p><button type='submit'>Démarrer équipes</button></p>";

