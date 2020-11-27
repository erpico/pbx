<?php

class PBXChannel {
  protected $db;
  const FIELDS = [
    "provider" => 0,
    "login" => 0,
    "password" => 0,
    "phone" => 0,    
    "rules" => 0,
    "name" => 0,
    "fullname" => 0,
    "host" => 0,
    "port" => 1,
    "active" => 0,
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
    return "peers";
  }

  public function fetchList($filter = "", $start = 0, $end = 20, $onlyCount = 0, $likeStringValues = true) {
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
    // die($sql)
    $res = $this->db->query($sql);
    $res = $this->db->query($sql, $onlyCount ? \PDO::FETCH_NUM  : \PDO::FETCH_ASSOC);
    $result = [];

    while ($row = $res->fetch()) {
      if ($onlyCount) {
        return intval($row[0]);
      }
      $result[] = $row;
    }

    return $result;
  }
  
  private function isUniqueColumn($column, $value, $id = 0) {    
    if (in_array($column, self::FIELDS)) {
      $data = $this->fetchList([$column => $value], 0, 3, 0, 0);      
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
    } else {
      throw new Exception("Undefined ".$column." column given", 1);
    }
    return true;
  }
  
  /**
   * @param $id
   *
   * @return array
   */
  public function remove($id) {
    try {
      if (!intval($id)) {
        return ["result" => false, "message" => "# канала не может быть пустым"];
      }
      if ($this->db->query("DELETE FROM ".self::getTableName()." WHERE id = ".intval($id))) {
        return ["result" => true, "message" => "Удаление прошло успешно"];
      }
    } catch (Exception $ex) {
      $this->logger->error($ex->getMessage()." ON LINE ".$ex->getLine());
      return ["result" => false, "message" => "Произошла ошибка удаления"];
    }
  }

  public function addUpdate($values) {
    if (is_array($values)) {
      if (isset($values['id']) && intval($values['id'])) {
        $sql = "UPDATE ".$this->getTableName()." SET ";
      } else {
        $sql = "INSERT INTO ".$this->getTableName()." SET ";
      }
      if (isset($values["login"]) && strlen($values["login"])) {
        if (!$this->isUniqueColumn("login", $values['login'], $values['id'])) {
          return [ "result" => false, "message" => "Логин занят другим коналом"];
        }
      } else {
        return [ "result" => false, "message" => "Логин не может быть пустым"];
      }
      if (isset($values["name"]) && strlen($values["name"])) {
        if (!$this->isUniqueColumn("name", $values['name'], $values['id'])) {
          return [ "result" => false, "message" => "Код занят другим коналом"];
        }
      } else {
        return [ "result" => false, "message" => "Код не может быть пустым"];
      }
      if (isset($values["fullname"]) && strlen($values["fullname"])) {
        if (!$this->isUniqueColumn("fullname", $values['fullname'], $values['id'])) {
          return [ "result" => false, "message" => "Название занят другим коналом"];
        }
      } else {
        return [ "result" => false, "message" => "Название не может быть пустым"];
      }      
      if (isset($values["phone"]) && strlen($values["phone"])) {
        if (!$this->isUniqueColumn("phone", $values['phone'], $values['id'])) {
          return [ "result" => false, "message" => "Телефон занят другим коналом"];
        }
      }
      if (!isset($values["password"]) || !strlen($values["password"])) {
        if (!intval($values["id"])) {
          return [ "result" => false, "message" => "Пароль не может быть пустым"];
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
      if (!isset($values["fullname"]) || !strlen($values["fullname"])) {
        if (!intval($values["id"])) {
          return [ "result" => false, "message" => "Название не может быть пустым"];
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
        return [ "result" => true, "message" => "Операция прошла успешно"];
      }
    }
    return [ "result" => false, "message" => "Произошла ошибка выполнения операции"];
  }

  public function getConfig() {
    $phones = $this->fetchList(0, 0, 1000000, 0);

    $result = "; ErpicoPBX Peers Configuration\n; WARNING! This lines is autogenerated. Don't modify it.\n\n";    

    foreach ($phones as $p) {
      if ($p['deleted'] || !$p['active']) {
        continue;
      }
      $result .= "[{$p['name']}]({$p['provider']})\n".
                 "  type = friend\n".
                 "  dynamic = no\n".
                 "  host = {$p['host']}\n".
                 "  port = {$p['port']}\n".
                 "  remotesecret = {$p['password']}\n".
                 "  defaultuser = {$p['login']}\n".
                 "  fromdomain = {$p['host']}\n".
                 "  fromuser = {$p['login']}\n".
                 "  nat = yes\n".
                 "  context = {$p['rules']}\n".                 
                 "  insecure = port,invite\n".
                 "  qualify = yes\n\n";
    }
    return $result;    
  }

  public function getRegConfig() {
    $phones = $this->fetchList(0, 0, 1000000, 0);

    $result = "; ErpicoPBX Peers Registry Configuration\n; WARNING! This lines is autogenerated. Don't modify it.\n\n"; 

    foreach ($phones as $p) {
      if ($p['deleted'] || !$p['active']) {
        continue;
      }
      $result .= "register => {$p['login']}:{$p['password']}@{$p['host']}:{$p['port']}/{$p['phone']}\n";
    }
    return $result;    
  }

  public function getPjsipConfig() {
    $phones = $this->fetchList(0, 0, 1000000, 0);

    $result = "; ErpicoPBX Peers Configuration\n; WARNING! This lines is autogenerated. Don't modify it.\n\n";    

    foreach ($phones as $p) {
      if ($p['deleted'] || !$p['active']) {
        continue;
      }
      $result .= "[{$p['name']}]\n".
                 "  type=registration\n".
                 "  outbound_auth={$p['name']}\n".
                 "  server_uri=sip:{$p['host']}:{$p['port']}\n".
                 "  client_uri=sip:{$p['login']}@{$p['host']}:{$p['port']}\n".
                 "  retry_interval=60\n".
                 "[{$p['name']}]\n".
                 "  type=auth\n".
                 "  auth_type=userpass\n".
                 "  password={$p['password']}\n".
                 "  username={$p['login']}\n".
                 "[{$p['name']}]\n".
                 "  type=aor\n".
                 "  contact=sip:{$p['host']}:{$p['port']}\n".
                 "[{$p['name']}]\n".
                 "  type=endpoint\n".
                 "  context={$p['rules']}\n".
                 "  disallow=all\n".
                 "  allow=opus\n".
                 "  allow=ulaw\n".
                 "  allow=alaw\n".
                 "  outbound_auth={$p['name']}\n".
                 "  aors={$p['name']}\n".
                 "  from_user={$p['login']}\n".
                 "  from_domain={$p['host']}\n".
                 "[{$p['name']}]\n".
                 "  type=identify\n".
                 "  endpoint={$p['name']}\n".
                 "  match={$p['host']}:{$p['port']}\n\n";

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
      $test_result = $this->isUniqueColumn("name", $safeValue, 0);      
    }    
    return $value .= $i ? $i : "";
  }

}
