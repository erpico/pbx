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
    "port" => 1
  ];

  public function __construct() {
    global $app;    
    $container = $app->getContainer();
    $this->db = $container['db'];

    $this->user = $container['auth'];//new Erpico\User($this->db);
    $this->utils = new Erpico\Utils();
  }

  private function getTableName() {
    return "peers";
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
  
  private function isUniquePhone($phone, $id = 0) {
    $data = $this->fetchList(["phone" => $phone]);
    if (is_array($data)) {
      if (COUNT($data) > 1) {
        return false;
      } else if (COUNT($data) == 1){
        return $data[0]["id"] == intval($id);
      }
    }
    return true;
  }

  private function isUniqueLogin($login, $id) {
    $data = $this->fetchList(["login" => $login]);
    if (is_array($data)) {
      if (COUNT($data) > 1) {
        return false;
      } else if (COUNT($data) == 1) {
        return $data[0]["id"] == intval($id);
      }
    }
    return true;
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
      if (isset($values["login"]) && strlen($values["login"])) {
        if (!$this->isUniqueLogin($values['login'], $values['id'])) {
          return [ "result" => false, "message" => "Логин занят другим пользователем"];
        }
      } else {
        return [ "result" => false, "message" => "Логин не может быть пустым"];
      }
      if (isset($values["name"]) && strlen($values["name"])) {
        if (!$this->isUniqueName($values['name'], $values['id'])) {
          return [ "result" => false, "message" => "Код занят другим пользователем"];
        }
      } else {
        return [ "result" => false, "message" => "Код не может быть пустым"];
      }
      if (isset($values["phone"]) && strlen($values["phone"])) {
        if (!$this->isUniquePhone($values['phone'], $values['id'])) {
          return [ "result" => false, "message" => "Телефон занят другим пользователем"];
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
      $result .= "[{$p['name']}]({$p['provider']})\n".
                 "  type = friend\n".
                 "  dynamic = no\n".
                 "  host = {$p['host']}\n".
                 "  port = {$p['port']}\n".
                 "  remotesecret = {$p['password']}\n".
                 "  defaultuser = {$p['login']}\n".
                 "  fromdomain = {$p['host']}\n".
                 "  nat = yes\n".
                 "  context = {$p['rules']}\n".
                 "  fromuser = {$p['phone']}\n".
                 "  insecure = port,invite\n".
                 "  qualify = yes\n".                 
                 "  callerid = {$p['phone']} <{$p['phone']}>\n\n";
    }
    return $result;    
  }

  public function getRegConfig() {
    $phones = $this->fetchList(0, 0, 1000000, 0);

    $result = "; ErpicoPBX Peers Registry Configuration\n; WARNING! This lines is autogenerated. Don't modify it.\n\n"; 

    foreach ($phones as $p) {
      $result .= "register => {$p['name']}?{$p['login']}:{$p['password']}@{$p['host']}:{$p['port']}\n";
    }
    return $result;    
  }

  public function getPjsipConfig() {
    $phones = $this->fetchList(0, 0, 1000000, 0);

    $result = "; ErpicoPBX Peers Configuration\n; WARNING! This lines is autogenerated. Don't modify it.\n\n";    

    foreach ($phones as $p) {      
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
