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
    "pattern" => 0
  ];

  public function __construct() {
    global $app;    
    $container = $app->getContainer();
    $this->db = $container['db'];

    $this->user = $container['auth'];//new Erpico\User($this->db);
    $this->utils = new Erpico\Utils();    
  }

  private function getTableName() {
    return "queue";
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
  public function fetchList($filter = "", $start = 0, $end = 20, $onlyCount = 0) {
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
            $wsql .= "`".$key."` ".(intval($fields[$key]) ? "='" : "LIKE '%")."".($fields[$key] ? intval($value) : trim(addslashes($value)))."".(intval($fields[$key]) ? "'" : "%'");
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
      $row["agents"] = $this->getQueueAgent($row['id']);
      $result[] = $row;
    }

    return $result;
  }
  
  public function getQueueAgent($id) {
    $sql = "SELECT queue_agent.acl_user_id, acl_user.name FROM queue_agent 
    LEFT JOIN acl_user ON (acl_user.id = queue_agent.acl_user_id)
    WHERE queue_agent.queue_id = '{$id}'";
    $res = $this->db->query($sql, \PDO::FETCH_NUM);
    $ids = [];
    $names = [];
    while ($row = $res->fetch()) {
      $ids[] = $row[0];
      $names[] = $row[1];
    }
    return ["ids" => $ids, "names" => $names]; 
  }
  private function isUniqueName($name, $id) {
    $data = $this->fetchList(["name" => $name]);
    if (is_array($data)) {
      if (COUNT($data) > 1) {
        if (!intval($id)) {
          return COUNT($data);
        }
        return false;
      } else {
        if (intval($id)) {
          return $data[0]["id"] == intval($id);
        } else {
          if (COUNT($data)) {
            return COUNT($data);
          } else {
            return true;
          }
        }
      }
    }
    return true;
  }

  public function addUpdate($values) {
    if (is_array($values)) {
      if (isset($values['id']) && intval($values['id'])) {
        $sql = "UPDATE ".$this->getTableName()." SET ";
      } else {
        $sql = "INSERT INTO ".$this->getTableName()." SET ";
      }
      if (isset($values["nmae"]) && strlen($values["name"])) {
        if (!$this->isUniqueName($values['name'], $values['id'])) {
          return [ "result" => false, "message" => "Название занято"];
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
          return [ "result" => false, "message" => "Код может содержать только символы английского алфамита и знак '_'"];
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
        if (isset($values['users_ids']) && trim(strlen($values['users_ids']))){
          $users = explode(",",trim($values['users_ids']));
          $ids = [];
          foreach ($users as $user_id) {
            if (intval($user_id)) {
              $ids[] = intval($user_id);
            }          
          }
          if (COUNT($ids)) {
            $this->deleteQueueAgentsExceptFor($id, $ids);
            foreach ($ids as $u_id) {
              $this->saveQueueAgents($id, $u_id);
            }
          }
        } else {
          $this->deleteQueueAgentsExceptFor($id, [0]);
        }
        return [ "result" => true, "message" => "Операция прошла успешно"];
      }
    }
    return [ "result" => false, "message" => "Произошла ошибка выполнения операции"];
  }

  public function deleteQueueAgentsExceptFor($queue_id, $user_ids) {
    $sql = "DELETE FROM queue_agent WHERE queue_id = {$queue_id} AND acl_user_id NOT IN (".implode(",",$user_ids).")";
    $this->db->query($sql);
    return true;
  } 
  
  public function saveQueueAgents($queue_id, $user_id) {
        $sql = "SELECT COUNT(*) FROM queue_agent WHERE
        queue_id = ".intval($queue_id)." AND acl_user_id = {$user_id}";
        $res = $this->db->query($sql, \PDO::FETCH_NUM);
        $row = $res->fetch();
        if (!intval($row[0])) {
          $sql = "INSERT INTO queue_agent 
          (queue_id, acl_user_id, penalty) 
          VALUES 
          (".intval($queue_id).",{$user_id}, 0)";
          $this->db->query($sql);
        }
  }

  public function getConfig() {
    $phones = $this->fetchList(0, 0, 1000000, 0);

    $result = "; ErpicoPBX Queues Configuration\n; WARNING! This lines is autogenerated. Don't modify it.\n\n";    

    foreach ($phones as $p) {
      $result .= "[{$p['name']}](${p['pattern']})\n\n";                 
    }
    return $result;    
  }

  public function getCode($name) {
    $translator = new Erpico\Translator($name);
    $value = $translator->translate();
    $test_result = $this->isUniqueName($value, 0);
    
    while (!is_bool($test_result)  || !$test_result) {
      if (is_numeric($test_result)) {
        $value .= $test_result;
      }
      $test_result = $this->isUniqueName($value, 0);      
    }    
    return $value;
  }
}
