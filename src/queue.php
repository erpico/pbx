<?php

class PBXQueue {
  protected $db;
  const FIELDS = [
    "name" => 0,
    "fullname" => 0,
    "description" => 0,
    "url" => 0,
    "close_on_hangup" => 0,
    "viq" => 0,
    "hidden" => 0,
    "service_id" => 1,
    "sl" => 1,
    "pattern" => 0,
    "active" => 0,
    "autopause" => 1,
    "alarms" => 0,
    "deleted" => 0
  ];

  public function __construct() {
    global $app;    
    $container = $app->getContainer();
    $this->db = $container['db'];
    $this->logger = $container['logger'];
    $this->user = $container['auth'];//new Erpico\User($this->db);
    $this->utils = new Erpico\Utils();    
  }

  private function getTableName() {
    return "queue";
  }
  
  /**
   * @param $id
   *
   * @return array
   */
  public function remove($id) {
    try {
      if (!intval($id)) {
        return ["result" => false, "message" => "# очереди не может быть пустым"];
      }
      if ($this->db->query("SET FOREIGN_KEY_CHECKS=0; DELETE FROM ".self::getTableName()." WHERE id = ".intval($id). "; SET FOREIGN_KEY_CHECKS = 1; ")) {
        return ["result" => true, "message" => "Удаление прошло успешно"];
      }
    } catch (Exception $ex) {
      $this->logger->error($ex->getMessage()." ON LINE ".$ex->getLine());
      return ["result" => false, "message" => "Произошла ошибка удаления"];
    }
  }

  public function getName($id) {
    if (!intval($id)) return "";
    $sql = "SELECT name FROM queue WHERE id = {$id} ";
    $res = $this->db->query($sql, \PDO::FETCH_NUM );
    $row = $res->fetch();
    if (is_array($row) && strlen($row[0])) {
      return $row[0];
    }
    return "";
  }
  public function fetchList($filter = "", $start = 0, $end = 20, $onlyCount = 0, $fullnameAsValue = 0, $likeStringValues = true) {
    $sql = "SELECT ";
    if (intval($onlyCount)) {
      $ssql = " COUNT(*) ";  
    } else {
      $ssql = "`id`";
      foreach (self::FIELDS as $field => $isInt) {
        if (strlen($ssql)) $ssql .= ",";
        $ssql .= "`".$field."`";
      }
    }
    $sql .= $ssql." FROM ".self::getTableName();
    $wsql = "";
    if (is_array($filter)) {
      $fields = self::FIELDS;
      $fields["id"] = 1;
      $wsql = "";
      foreach ($filter as $key => $value) {
        if (isset($fields[$key])) {
          if (array_key_exists($key,$fields) && (intval($fields[$key]) ? intval($value) : strlen($value) )) {
            if (strlen($wsql)) $wsql .= " AND ";
            $wsql .= "`".$key."` ".(intval($fields[$key]) ? "='" :  ($likeStringValues ? "LIKE '%" : "='" ))."".($fields[$key] ? intval($value) : trim(addslashes($value)))."".(intval($fields[$key]) ? "'" : ($likeStringValues ? "%'" : "'" ));
          }
        }        
      }
    }
    if (strlen($wsql)) {
      $sql .= " WHERE ".$wsql;
    }
    $sql .= " order by id";
    $res = $this->db->query($sql);
    $res = $this->db->query($sql, $onlyCount ? \PDO::FETCH_NUM  : \PDO::FETCH_ASSOC);
    $result = [];

    while ($row = $res->fetch()) {
      if ($onlyCount) {
        return intval($row[0]);
      }

      if ($fullnameAsValue) {
        $result[] = ["id"=>$row["name"], "value"=>$row["fullname"]];
      } else {
        $row["agents"] = $this->getQueueAgent($row['id']);
        $result[] = $row;
      }
    }

    return $result;
  }
  
  public function getQueueAgent($id) {
    $sql = "SELECT queue_agent.acl_user_id, acl_user.name, queue_agent.phone, queue_agent.static, queue_agent.penalty FROM queue_agent 
    LEFT JOIN acl_user ON (acl_user.id = queue_agent.acl_user_id)
    WHERE queue_agent.queue_id = '{$id}'";
    $res = $this->db->query($sql);
    $result = [];
    while ($row = $res->fetch()) {
      $result[] = $row;
    }
    return $result; 
  }
  private function isUniqueColumn($column, $name, $id) {
    if (in_array($column, SELF::FIELDS)) {
      $data = $this->fetchList([$column => $name], 0, 3, 0, 0, 0);
      if (is_array($data)) {
        if (COUNT($data) > 1) {
          return false;
        } else if (COUNT($data) == 1){
          if (intval($id)) {
            return $data[0]["id"] == intval($id);
          } else {
            return false;
          }
        }
      }
      return true;
    }    
  }

  public function addUpdate($values) {
    if (is_array($values)) {
      if (isset($values['id']) && intval($values['id'])) {
        $sql = "UPDATE ".$this->getTableName()." SET ";
      } else {
        $sql = "INSERT INTO ".$this->getTableName()." SET ";
      }
      if (isset($values["name"]) && strlen($values["name"])) {
        if (!$this->isUniqueColumn("name",$values['name'], $values['id'])) {
          return [ "result" => false, "message" => "Код занят другой очередью"];
        }
      }
      if (isset($values["fullname"]) && strlen($values["fullname"])) {
        if (!$this->isUniqueColumn("fullname",$values['fullname'], $values['id'])) {
          return [ "result" => false, "message" => "Название занято другой очередью"];
        }
      }
      
      if (!isset($values["name"]) || !strlen($values["name"])) {
        if (!intval($values["id"])) {
          return [ "result" => false, "message" => "Код не может быть пустым"];
        }
      } else {
        $pattern = '/[A-Za-z0-9\_]/'; 
        $matches = preg_replace ($pattern, "", $values["name"]); 
        if (strlen($matches)) {
          return [ "result" => false, "message" => "Код может содержать только символы английского алфавита и знак '_'"];
        }
      }

      foreach (self::FIELDS as $field => $isInt) {
        if (isset($values[$field]) && (intval($isInt) ? intval($values[$field]) : strlen($values[$field]) )) {
          if (strlen($ssql)) $ssql .= ",";
          $ssql .= "`".$field."`='".($isInt ? intval($values[$field]) : trim(addslashes($values[$field])))."'";
        }  
      }

      if (strlen($ssql)) {
        $sql .= $ssql;
        if (isset($values['id']) && intval($values['id'])) {
          $sql .= " WHERE id ='".intval($values['id'])."'";
        }
        $this->db->query($sql);
        if (isset($values['id']) && intval($values['id'])) {
          $id = intval($values['id']);
        } else {
          $id = $this->db->lastInsertId();
        }
        //$this->deleteQueueAgents($id);
        $agents = json_decode($values["agents"], true);
        if (is_array($agents) && count($agents)){
          $this->deleteQueueAgentsExceptFor($id);
          foreach ($agents as $a) {
            if (
              intval($a['acl_user_id']) == 0
              && (trim($a['phone']) === "" || $a['phone'] === null)
            ) {
              return ["result" => false, "message" => "Укажите номер телефона или выберите сотрудника."];
            } else {
              if ($a['acl_user_id'] == "") {
                $a['acl_user_id'] = NULL;
              }
              $this->saveQueueAgents($id, $a['acl_user_id'], $a);
            }
          }
        } else {
          $this->deleteQueueAgentsExceptFor($id);
        }
        return [ "result" => true, "message" => "Операция прошла успешно"];
      }
    }
    return [ "result" => false, "message" => "Произошла ошибка выполнения операции"];
  }

  public function deleteQueueAgentsExceptFor($queue_id) {
    $sql = "DELETE FROM queue_agent WHERE queue_id = {$queue_id}";
    $this->db->query($sql);
    return true;
  } 
  
  public function saveQueueAgents($queue_id, $user_id, $agents) {
    $sql = "SELECT COUNT(*) FROM queue_agent WHERE queue_id = ".intval($queue_id)." AND phone = '".trim(addslashes($agents["phone"]))."'";
         if ($user_id == NULL){
          $sql .= " AND acl_user_id IS NULL";
        } else {
          $sql .= " AND acl_user_id = {$user_id}";
        }
        //die($sql);
        $res = $this->db->query($sql, \PDO::FETCH_NUM);
        $row = $res->fetch();
        if (!intval($row[0])) {
            $sql = "INSERT INTO queue_agent 
            (queue_id, penalty, phone, static, acl_user_id) 
            VALUES 
            (".intval($queue_id).", ".intval($agents["penalty"]).", '".trim(addslashes($agents["phone"]))."', ".intval($agents["static"]).", ";
            if ($user_id == NULL){
              $sql .= " NULL )";
            } else {
              $sql .= " {$user_id} )";
            }
            $this->db->query($sql);
        }
  }

  public function getConfig() {
    $queues = $this->fetchList(0, 0, 1000000, 0);

    $result = "; ErpicoPBX Queues Configuration\n; WARNING! This lines is autogenerated. Don't modify it.\n\n";    

    foreach ($queues as $p) {
      if ($p['deleted'] || !$p['active']) {
        continue;
      }
      $result .= "[{$p['name']}]({$p['pattern']})\n";    
      if (is_array($p['agents'])) {
        foreach ($p['agents'] as $a) {
          if ($a['static'] && strlen($a['phone'])) {
            $result .= "member => Local/{$a['phone']}@queue_member,{$a['penalty']},{$a['phone']}\n";
          }
        }
      }
      $result .= "\n";
    }
    return $result;    
  }

  public function getCode($name) {
    $translator = new Erpico\Translator($name);
    $value = $translator->translate();
    $test_result = $this->isUniqueColumn("name", $value, 0);
    $i = 0;
    while (!is_bool($test_result)  || !$test_result) {
      $safeValue = $value;
      $safeValue .= ++$i;
      $test_result = $this->isUniqueColumn("name",$safeValue, 0);      
    }    
    return  $value .= $i ? $i : "";
  }

}
