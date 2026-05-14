<?php
namespace Erpico;

class PBXRules {
  protected $db;
  private $id;
  private $name;
  const FIELDS = [
    "name" => 0,
    "description" => 0,
    "handle" => 0,
    "updated" => 0
  ];

  public function __construct($id = 0) {
    global $app;    
    $container = $app->getContainer();
    $this->db = $container['db'];
    
    $this->user = $container['auth'];
  }

  private function getTableName() {
    return "acl_rule";
  }

  public function fetchList() {
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
    $sql .= " order by name";
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

  public function getGroupRules($groupId) {
    $sql = "SELECT H.acl_rule_id FROM acl_user_group_has_rules AS H 
      WHERE H.acl_user_group_id = '".intval($groupId)."'";
    $res = $this->db->query($sql);
    $result = [];

    while ($row = $res->fetch()) {
      $result[] = $row['acl_rule_id'];
    }

    return $result;
  }

  public function getUserRules($userId) {
    $sql = "SELECT H.acl_rule_id FROM acl_user_has_rules AS H 
      WHERE H.acl_user_id = '".intval($userId)."'";
    $res = $this->db->query($sql);
    $result = [];

    while ($row = $res->fetch()) {
      $result[] = $row['acl_rule_id'];
    }

    return $result;
  }
  
  public function saveGroup($values, $id) {
    if (!strlen($values)) {
      $this->deleteGroup($id);
      return false;
    }
    $rules = $this->strToArray($values);
    $this->deleteGroup($id);
    foreach ($rules as $rule) {
      $sql = "INSERT INTO acl_user_group_has_rules 
      SET acl_user_group_id = '".intval($id)."', acl_rule_id = '".$rule."'";
      $res = $this->db->query($sql);
    }
    return true;
  }  

  public function saveUser($values, $id) {
    if (!strlen($values)) {
      $this->deleteUser($id);
      return false;
    }
    $rules = $this->strToArray($values);
    $this->deleteUser($id);
    foreach ($rules as $rule) {
      $sql = "INSERT INTO acl_user_has_rules 
      SET acl_user_id = '".intval($id)."', acl_rule_id = '".$rule."'";
      $res = $this->db->query($sql);
    }
    return true;
  }

  
  private function deleteUser($id) {
    if (!intval($id)) return false;
    $sql = "DELETE FROM acl_user_has_rules WHERE acl_user_id = '".intval($id)."'";
    $this->db->query($sql);
    return true;
  }

  private function deleteGroup($id) {
    if (!intval($id)) return false;
    $sql = "DELETE FROM acl_user_group_has_rules WHERE acl_user_group_id = '".intval($id)."'";
    $this->db->query($sql);
    return true;
  }

  private function strToArray($string) {
    $arr = explode(",",$string);
    $result = [];
    foreach ($arr as $value) {
      if (intval($value)) {
        $result[] = $value;
      }
    }
    return $result;
  }

}
