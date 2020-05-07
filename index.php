<script
  src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0="   crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.4/clipboard.min.js"></script>
  <script>
  // Format d'un message :
  // @orgas Exercice 123456, notre réponse est : LAREPONSE
  $(function() {
     var clipboard = new ClipboardJS('#copyButton');
     
     clipboard.on('success', function(e) {
        console.info('Action:', e.action);
        console.info('Text:', e.text);
        console.info('Trigger:', e.trigger);
        e.clearSelection();
     });

     clipboard.on('error', function(e) {
        console.error('Action:', e.action);
        console.error('Trigger:', e.trigger);
     });

     $('#message').bind('input', function() {
       var words = $(this).val().split(/[ ,:;.]+/);
       var answer = words[words.length - 1].trim();
       var code = 0;
       for (var iWord = 0; iWord < words.length; iWord++) {
         var word = words[iWord];
         if ($.isNumeric(word) && word.length > 3) {
            code = word;
            break;
         }
       }
       $("#code").val(code);
       $("#answer").val(answer);
       var validAnswers = /^[A-Za-z0-9]+$/;
       if (validAnswers.test(answer)) {
          $("#answer").css('background-color','#80FF80');
       } else {
          $("#answer").css('background-color','#FF8080');
       }          
      });
  });
  </script>
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

echo "<h1>Finale du concours Alkindi 2020</h1>";

echo "<h2>Soumettre une réponse</h2>";

echo "<p>Message complet : <input type='text' id='message' style='width:800px'></p>";
echo "<form action='index.php' method='post'>";
echo "<p>Indicatif à 5 chiffres : <input type='number' name='code' id='code'></p>";
echo "<p>Réponse proposée : <input type='text' name='answer' id='answer'></p>";
echo "<p>Pénalités : <input type='checkbox' name='noPenalty'> annuler une pénalité de 5 minutes</p>";
echo "<button type='submit'>Valider</button>";

function getChecksum($equipe, $exo) {
   global $config;
    $tmp = (136 * $equipe + 81 * $exo) % 216;
    $tmp = (55297 * $tmp) % 98712;
    // remplacer salt par une autre valeur si on veut
    $tmp = ($tmp + 27 * 8 * $config->salt) % 98712;
    //assert tmp%27 == equipe
    //assert tmp%8 == exo
    $tmp = "".$tmp;
    while (strlen($tmp) < 5) {
        $tmp = '0'.$tmp;
    }
    return $tmp;
}

function getRecordFromCode($code) {
   global $config, $db;
   if ($code == "") {
      return null;
   }
   $extract = ($code - $config->salt * 27 * 8 + 98712) % 98712;
   $teamID = $extract % 27;
   $question = $extract % 8;
   $query = "SELECT ID, teamID, expectedAnswer, question FROM teams_questions WHERE question = :question AND teamID = :teamID";
   $stmt = $db->prepare($query);
   $stmt->execute(array("question" => $question, "teamID" => $teamID));
   $row = $stmt->fetchObject();
   if (($row == null) || ($extract % 457 != 0)) {
      echo "<input id='copyTarget' value=\"L'indicatif de sujet ".$code." est invalide !\" />";
      return null;
   } else {
      return $row;
   }
}

function handleTeamSubmission() {
   global $db,$config;
   echo "<h2>Résultat</h2>";
   $code = trim($_POST["code"]);
   $answer = trim($_POST["answer"]);
   $row = getRecordFromCode($code);
   $penaltySeconds = 0;
   if (isset($_POST["noPenalty"])) {
      $penaltySeconds = -300;
   }
   if ($row != null) {
      $teamID = $row->teamID;
      $question = $row->question;
      $isValid = 0;
      if ($row->expectedAnswer == $answer) {
         $isValid = 1;
      } else {
         $penaltySeconds += 300;
      }
      $querySolved = "SELECT count(*) as nb FROM attempts WHERE questionID = :questionID AND isValid = 1";
      $stmt = $db->prepare($querySolved);
      $stmt->execute(array("questionID" => $row->ID));
      $rdup = $stmt->fetchObject();
      if ($rdup->nb > 0) {
         echo "<input id='copyTarget' value='Votre équipe avait déjà réussi ce sujet !' />";
      }
      else {
         $queryDuplicate = "SELECT count(*) as nb FROM attempts WHERE questionID = :questionID AND answer = :answer";
         $stmt = $db->prepare($queryDuplicate);
         $stmt->execute(array("questionID" => $row->ID, "answer" => $answer));
         echo "<p><b>Question : ".$row->question."</b></p>";
         $rdup = $stmt->fetchObject();
         if ($rdup->nb > 0) {
            echo "<p>Cette réponse avait déjà été soumise.</b></p>";
         } else {
            $query2 = "INSERT INTO attempts (questionID, answerTime, answer, isValid, penaltySeconds) VALUES
            (:questionID, NOW(), :answer, :isValid, :penaltySeconds)";
            $stmt = $db->prepare($query2);
            $stmt->execute(array("questionID" => $row->ID, "answer" => $answer, "isValid" => $isValid, "penaltySeconds" => $penaltySeconds));
         }
         echo "<p>Équipe : ".$teamID."</p>";
         if ($isValid) {
            $message = "";
            $query4 = "SELECT question,startTime FROM teams_questions WHERE teamID = :teamID AND question = :question";
            $stmt = $db->prepare($query4);
            $stmt->execute(array("question" => ($question + 1), "teamID" => $teamID));
            $teamLetter = chr(ord('A')- 1 + $teamID);
            if ($row = $stmt->fetchObject()) {
               $checksum = getChecksum($teamID, $row->question);
               $taskLink = $config->taskUrkPrefix.$row->question."/".$teamLetter."_".$checksum.".html";
               if ($row->startTime == "") {
                  $message = "Lien pour le sujet ".$row->question." : ".$taskLink;
               } else {
                  $message = "Sujet ".$row->question." déjà transmis : ".$taskLink;
               }
            }
            $query3 = "UPDATE teams_questions SET startTime = NOW() WHERE teamID = :teamID AND question = :question AND startTime IS NULL";
            $stmt = $db->prepare($query3);
            $stmt->execute(array("question" => ($question + 1), "teamID" => $teamID));
            echo "<input id='copyTarget' value=\"C'est validé. Bravo ! ".$message."\" />";
         } else {
            echo "<input id='copyTarget' value='La réponse ".$answer." est invalide' /><p>Réponse attendue : ".$row->expectedAnswer."</b></p>";
         }
      }
   }
   echo "<p><button type='button' id='copyButton' data-clipboard-action='cut' data-clipboard-target='#copyTarget'>Copier dans le presse-papier</button></p>";
}

function handleTeamsStarting() {
   global $db,$_POST;
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
}

function getMaxQuestion() {
   global $db;
   $query = "SELECT count(DISTINCT question) as nb FROM teams_questions";
   $stmt = $db->prepare($query);
   $stmt->execute(array());
   $row = $stmt->fetchObject();
   return $row->nb;
}

function getTeamsStatus() {
   global $db;
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
   return $teams;
}

function displayTeamsStatus() {
   echo "<h2>Équipes</h2>";

   echo "<table class='borders' cellspacing=0>";
   echo "<tr style='font-weight:bold'><td>Départ</td><td>Nom de l'équipe</td><td>Score</td><td>Temps</td><td>Détail temps</td>";
   $maxQuestion = getMaxQuestion();
   for ($question = 1; $question <= $maxQuestion; $question++) {
      echo "<td>Question ".$question."</td>";
   }
   echo "</tr>";

   $teams = getTeamsStatus();
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
}

if (isset($_POST["code"])) {
   handleTeamSubmission();
}
handleTeamsStarting();
displayTeamsStatus();

