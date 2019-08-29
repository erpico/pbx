<?php

namespace Erpico;

class Ext_checklist {
  private $container;
  private $db;
  private $auth;  

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getExt_checklist_list($filter, $pos, $count = 20, $onlycount = 0) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");
    
    // Here need to add permission checkers and filters

    if ($onlycount) {
      $res = $this->db->query("SELECT COUNT(*) FROM checklist_questions");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    if (isset($filter['user_id']) && isset($filter['date']) && $filter['user_id'] !=0 && $filter['date'] != 0) {
      $sql = "select A.id, B.id AS id_answ, B.`comment`, question,answer,checklist_id, block, weight from checklist_questions AS A LEFT JOIN checklist_answers AS B ON B.question_id = A.id AND user_id ='".addslashes($filter['user_id'])."' and date='".addslashes($filter['date'])."'";

      if ($filter['list'] != 0) {
          $sql .= " WHERE checklist_id = '".addslashes($filter['list'])."'";
      } else {
          $sql .= " WHERE answer >= 0";
      }
      $sql .= " ORDER BY A.id";
    } else {
      $sql = "SELECT id, question,checklist_id,block,weight FROM checklist_questions WHERE checklist_id = '".addslashes($filter['checklist_id'])."' ORDER BY id";
    }

  /* добавить вывод комментария и id Записи*/
  //var_dump($sql);

    $arr = [];
    $res = $this->db->query($sql);
    if (!$res) die(print_r($this->db->errorInfo()));
    if (!isset($filter['noempty'])) {
      while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
          if (!$row['id']) continue;

          $arr[] = [
              "id" => $row['id'],
              "question" => $row['question'],
              "answer" => isset($row['answer']) ? $row['answer'] : null,
              "checklist" => $row['checklist_id'],
              "block" => $row['block'],
              "weight" => $row['weight'],
          ];
      }

      for ($i = 1; $i < 99; $i++) {
          $arr[] = [ "id" => $i*-1 ];
      }
    } else {
      $block = 0;
      while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
          if (!$row['id']) continue;
          $elem = [
              "id" => $row['id'],
              "question" => $row['question'],
              "answer" => isset($row['answer']) ? $row['answer'] : null,
              "checklist" => $row['checklist_id'],
              "block" => $row['block'],
              "weight" => $row['weight']   ,
              "comment" => $row["comment"],
              "id_answ" => $row["id_answ"]
          ];
          if ($row['block']) {
              if (is_array($block)) $arr[] = $block;
              $block = $elem;
              $block['$row'] = "question";
              $block["open"] = true;
              $block["data"] = [];
          } else {
              if (is_array($block)) $block["data"][] = $elem;
              else $arr[] = $elem;
          }
      }
      if (is_array($block)) $arr[] = $block;
    }

    return $arr;
  }

  public function checklist_save($filter) {
    // Here need to add permission checkers and filters

    $data = json_decode(file_get_contents('php://input'), true);
    foreach ($data as $elem) {
      if (isset($elem['question']) && strlen($elem['question'])) {
          $id = intval($elem['id']);
          if ($id > 0) {
              $sql = "UPDATE checklist_questions SET ";
          } else {
              $sql = "INSERT INTO checklist_questions SET ";
          }
          $sql .= "question = '";
          $sql .= addslashes($elem['question'])."'";
//          $sql .= "', block = '".addslashes($elem['block'])."', weight = '".addslashes($elem['weight'])."'";
          $sql .= ", checklist_id = '";
          $sql .= addslashes($filter['save']);
          $sql .= "'";

          if ($id > 0) {
              $sql .= "WHERE id = '$id' LIMIT 1 ";
          }
          $this->db->query($sql) or die ("$sql: ".print_r($this->db->errorInfo()));

      } else if (isset($elem['question']) && strlen($elem['question']) == 0) {
          $id = intval($elem['id']);
          if ($id > 0) {
              $sql = "DELETE FROM checklist_questions WHERE id = '$id' LIMIT 1";
              $this->db->query($sql) or die ("$sql: ".print_r($this->db->errorInfo()));
          }
      }
    }

    return "OK";
  }


  public function getExt_checklist_fill($filter, $pos, $count = 20, $onlycount = 0) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    // Here need to add permission checkers and filters

    $data = json_decode(file_get_contents('php://input'), true);
    $date = "Now()";
    if (isset($filter['date'])) {
      $date = "'".addslashes($filter['date'])."'";
    }
    foreach ($data as $elem) {
      if (isset($elem['id']) && isset($elem['user_id']) && isset($elem['answer'])) {
          $question_id = intval($elem['id']);
          $user_id = intval($elem['user_id']);
          $answer = intval($elem['answer']);
          $comment = $elem['comment'];
          if (!$question_id || !$user_id) continue;
          $sql = "REPLACE INTO checklist_answers SET user_id = $user_id, question_id = $question_id, answer = $answer, `date` = $date, answered = Now() , `comment` = '$comment'";

          $this->db->query($sql) or die ("$sql: ".print_r($this->db->errorInfo()));
      }
    }
    return "OK";
  }


  public function checklist_change($filter) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    // Here need to add permission checkers and filters

    $data = json_decode(file_get_contents('php://input'), true);
    $date = "Now()";
    if (isset($filter['date'])) {
      $date = "'".addslashes($filter['date'])."'";
    }
    foreach ($data as $elem) {
      if (isset($elem['id'])  && isset($elem['answer'])) {
          $question_id = $elem['id'] + 0;
          $user_id = $elem['user_id'] + 0;
          $answer = $elem['answer'] + 0;
          $comment = $elem['comment'];
          $id = $elem['id_answ'] + 0;
          $sql = "UPDATE checklist_answers SET  question_id = $question_id, answer = $answer, `comment` = '$comment', `date` = $date WHERE id = $id";
          //var_dump($sql);
          if($elem['id_answ'] == null){
              $sql = "REPLACE INTO checklist_answers SET user_id = $user_id, question_id = $question_id, answer = $answer, `date` = $date, answered = Now() , `comment` = '$comment'";
          }
          $this->db->query($sql) or die ("$sql: ".print_r($this->db->errorInfo()));
      }
    }

    return "OK";
  }


  public function checklist_delete_answer() {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    $data = json_decode(file_get_contents('php://input'), true);
    foreach ($data as $elem) {
      //проходим фором по массиву for($i = 0; $i <0 count(date); i++
      $id = intval($elem['id_answ']);
      $sql = "DELETE FROM checklist_answers WHERE id=$id";
      $this->db->query($sql) or die ("$sql: ".print_r($this->db->errorInfo()));
    }
    return "OK";
  }


  public function getExt_checklist_table($start, $finish) {

    $sql = "SELECT user_id, `date`, SUM(answer) AS total, acl_user.fullname AS user_name, count(answer) AS cnt FROM checklist_answers LEFT JOIN acl_user ON (acl_user.id = checklist_answers.user_id) WHERE `date` >= '".addslashes($start)."' AND `date` <= '".addslashes($finish)."' AND answer >= 0 GROUP BY `date`,user_id ORDER BY answered";
    $arr = [];
    $res = $this->db->query($sql);
    if (!$res) die(print_r($this->db->errorInfo()));
    while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
      $row['percent'] = round((($row['total'])/($row['cnt']*5))*100,2)."%";
      $row['date_formatted'] = strftime("%a, %d.%m.%Y", strtotime($row['date']));
      $arr[] = $row;
    }
    return $arr;
  }


  public function fetchTree($start, $finish, $parent = 0, $open = 0) {
    
    // Here need to add permission checkers and filters

    if (strlen($parent)) {
      list($id,$type) = explode(":", $parent);      
    }

    if (!isset($type)) {
      // Groups list
      $sql = "SELECT CLB.id, CLB.name, SUM(answer) AS total, count(answer) AS cnt 
        FROM checklists AS CLA LEFT JOIN checklist_questions ON checklist_questions.checklist_id = CLA.id 
        LEFT JOIN checklist_answers ON checklist_answers.question_id = checklist_questions.id 
        LEFT JOIN checklists AS CLB ON (CLB.id = CLA.parent_id) 
        WHERE `date` >= '".addslashes($start)."' AND answer >= 0 AND `date` <= '".addslashes($finish)."' AND CLA.parent_id IS NOT NULL 
        GROUP BY CLB.name, CLB.id ORDER BY CLB.name";
      $arr = [];
      $res = $this->db->query($sql);
      if (!$res) die(print_r($this->db->errorInfo()));
      while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
          //if (!$row['id']) continue;
          $arr[] = [
              "id" => $row['id'].":u",
              "value" => $row['name'],
              "total" => "",//$row['total'],
              "percent" => round((($row['total'])/($row['cnt']*5))*100,2)."%",
              'webix_kids' => true,
              'open' => $open
          ];
      }
    } else if ($type == "g") {
          // Groups list
          $sql = "SELECT checklists.id, checklists.name, SUM(answer) AS total, count(answer) AS cnt FROM checklists LEFT JOIN checklist_questions ON checklist_questions.checklist_id = checklists.id LEFT JOIN checklist_answers ON checklist_answers.question_id = checklist_questions.id WHERE `date` >= '".addslashes($start)."' AND `date` <= '".addslashes($finish)."' AND checklists.parent_id = '$id' AND answer >= 0 GROUP BY checklist_id ORDER BY checklists.name";
          $arr = [];
          $res = $this->db->query($sql);
          if (!$res) die(print_r($this->db->errorInfo()));
          while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
              //if (!$row['id']) continue;

              $arr[] = [
                  "id" => $row['id'].":sg",
                  "value" => $row['name'],
                  "total" => "",//$row['total'],
                  "percent" => round((($row['total'])/($row['cnt']*5))*100,2)."%",
                  'open' => $open
              ];
          }
     } else if ($type == "sg") {
      // List managers, who answered in this group
      $sql = "SELECT user_id, `date`, SUM(answer) AS total, acl_user.fullname AS user_name, count(answer) AS cnt FROM checklist_answers LEFT JOIN checklist_questions ON checklist_questions.id = question_id LEFT JOIN acl_user ON (acl_user.id = checklist_answers.user_id) WHERE checklist_id = '$id' AND `date` >= '".addslashes($start)."' AND `date` <= '".addslashes($finish)."' AND answer >= 0 GROUP BY user_id ORDER BY user_name";
      $arr = [];
      $res = $this->db->query($sql);
      if (!$res) die(print_r($this->db->errorInfo()));
      while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
          //if (!$row['id']) continue;
          $arr[] = [
              "id" => $id.":".$row['user_id'],
              'date' => $row['date'],
              'user_id' => $row['user_id'],
              "value" => $row['user_name'],
              "total" => "",//$row['total'],
              "percent" => round((($row['total'])/($row['cnt']*5))*100,2)."%",
              'open' => $open
              //'open' => true
          ];
      }
    } else if ($type == "u") {
          // List users
          $sql = "SELECT user_id, `date`, SUM(answer) AS total, acl_user.fullname AS user_name, count(answer) AS cnt FROM checklist_answers 
            LEFT JOIN checklist_questions ON checklist_questions.id = question_id 
            LEFT JOIN acl_user ON (acl_user.id = checklist_answers.user_id) 
            LEFT JOIN checklists ON (checklists.id = checklist_questions.checklist_id)
            WHERE checklists.parent_id = '$id' AND `date` >= '".addslashes($start)."' AND `date` <= '".addslashes($finish)."' AND answer >= 0 GROUP BY user_id ORDER BY user_name";
          //die ($sql);
          $arr = [];
          $res = $this->db->query($sql);
          if (!$res) die(print_r($this->db->errorInfo()));
          while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
              //if (!$row['id']) continue;
              $arr[] = [
                  "id" => $id.".".$row['user_id'].":ug",
                  'date' => $row['date'],
                  'user_id' => $row['user_id'],
                  "value" => $row['user_name'],
                  "total" => "",//$row['total'],
                  "percent" => round((($row['total'])/($row['cnt']*5))*100,2)."%",
                  'webix_kids' => true,
                  'open' => $open
              ];
          }
    } else if ($type == "ug") {
          // Groups list
          list($gid,$uid) = explode(".", $id);
          $sql = "SELECT checklists.id, checklists.name, SUM(answer) AS total, count(answer) AS cnt FROM checklists LEFT JOIN checklist_questions ON checklist_questions.checklist_id = checklists.id LEFT JOIN checklist_answers ON checklist_answers.question_id = checklist_questions.id WHERE `date` >= '".addslashes($start)."' AND `date` <= '".addslashes($finish)."' AND checklists.parent_id = '$gid' AND user_id = '$uid' AND answer >= 0 GROUP BY checklist_id ORDER BY checklists.name";
          $arr = [];
          $res = $this->db->query($sql);
          if (!$res) die(print_r($this->db->errorInfo()));
          while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
              //if (!$row['id']) continue;
              $arr[] = [
                  "id" => $row['id'].":".$uid,
                  "value" => $row['name'],
                  "total" => "",//$row['total'],
                  "percent" => round((($row['total'])/($row['cnt']*5))*100,2)."%",
                  'webix_kids' => true,
                  'open' => $open
              ];
          }
    } else if ($type == "da") {
          // answers list
          list($check_list, $user_id, $data) = explode(".",$id);
          $sql = "select a.question, b.answer, b.`comment` from checklist_questions as a left join checklist_answers as b on a.id = b.question_id and b.user_id = '".addslashes($user_id)."' and b.`date` = '".addslashes($data)."' where a.checklist_id = '".addslashes($check_list)."'";
          $res = $this->db->query($sql);
          while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
              $arr[] = [
                  'value' => $row['question'],
                  "total" => $row['answer'],
                  "percent" => $row['comment'],
                  'webix_kids' => false
              ];
          }
          $header_list_answers = [
              'value' => "<b>Вопрос</b>",
              "total" => "<b>Ответ</b>",
              "percent" => "<b>Комментарий</b>"
          ];
          array_unshift($arr, $header_list_answers);
    } else {
          // Days and answers
          $sql = "SELECT user_id, `date`, SUM(answer) AS total, SUM(answer) AS rtotal, acl_user.fullname AS user_name, count(answer) AS cnt FROM checklist_answers LEFT JOIN checklist_questions ON checklist_questions.id = question_id LEFT JOIN acl_user ON (acl_user.id = checklist_answers.user_id) WHERE checklist_id = '$id' AND user_id = '$type' AND`date` >= '".addslashes($start)."' AND `date` <= '".addslashes($finish)."' AND answer >= 0 GROUP BY `date` ORDER BY answered";
          $arr = [];
          $res = $this->db->query($sql);
          if (!$res) die(print_r($this->db->errorInfo()));
          while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
              //if (!$row['id']) continue;
              $arr[] = [
                  "id" => $id.".".$row['user_id'].".".$row['date'].":da",
                  'date' => $row['date'],
                  'user_id' => $row['user_id'],
                  'checklist_id' => $id,
                  'value' => strftime("%a, %d.%m.%Y", strtotime($row['date'])),
                  "total" => $row['rtotal'],
                  "percent" => round((($row['total'])/($row['cnt']*5))*100,2)."%",
                  'webix_kids' => true,
                  'open' => $open
              ];
          }
    }

    if (strlen($parent)) {
      $ant = [ "parent" => $parent,
               "data" => $arr];
      return $ant;
    } else {
      return $arr;
    }
  }


  public function getExt_checklist_listgroups($filter, $pos, $count = 20, $onlycount = 0) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    // Here need to add permission checkers and filters

    if ($onlycount) {
        $res = $this->db->query("SELECT COUNT(*) FROM checklist_answers");
        $row = $res->fetch(\PDO::FETCH_NUM);
        return intval($row[0]);
    }

    if (isset($filter['user_id']) && isset($filter['date']) && $filter['user_id'] !=0 && $filter['date'] != 0) {
      $sql = "SELECT DISTINCT checklists.id, checklists.name FROM checklist_answers LEFT JOIN checklist_questions ON checklist_questions.id = question_id LEFT JOIN checklists ON checklists.id = checklist_id WHERE checklist_answers.date = '".addslashes($filter['date'])."' AND user_id = '".addslashes($filter['user_id'])."'";
    } else {
      $sql = "SELECT id, name FROM checklists WHERE deleted IS NULL AND parent_id ".(isset($filter['parent_id']) && intval($filter['parent_id']) != 0 ? " = ".intval($filter['parent_id']) : "IS NULL")." ORDER BY id";
    }

    $arr = [];
    $res = $this->db->query($sql);
    if (!$res) die(print_r($this->db->errorInfo()));
    while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
      if (!$row['id']) continue;
      if (isset($filter['checklist_id']) && $filter['checklist_id'] != 0 && $filter['checklist_id'] != $row['id']) continue;

      $arr[] = [
          "id" => $row['id'],
          "value" => $row['name'],
      ];
    }

    return $arr;
  }

  public function addgroup($params) {
    // Here need to add permission checkers and filters

    $sql = "INSERT INTO checklists SET name = '".addslashes($params['addgroup'])."', parent_id = ".(isset($params['parent_id']) && $params['parent_id'] != 0 ? intval($params['parent_id']) : "NULL");
    $res = $this->db->query($sql);
    if (!$res) die(print_r($this->db->errorInfo()));

    return [ "id" => $this->db->lastInsertId() ]; //PDO::lastInsertId() ];
  }

  public function deletegroup ($params) {
    // Here need to add permission checkers and filters

    $sql = "UPDATE checklists SET deleted = '1' WHERE id = '".addslashes($params['deletegroup'])."'";
    $res = $this->db->query($sql);
    if (!$res) die(print_r($this->db->errorInfo()));
    return [ "result" => "OK" ];
  }

}