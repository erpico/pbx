<?php
use Erpico\User;

class PBXContactGroups {
  protected $db;
  private $id;
  private $name;
  const FIELDS = [
    "name" => 0
  ];

  public function __construct($id = 0) {
    global $app;    
    $container = $app->getContainer();
    $this->db = $container['db'];
    $this->setId(intval($id));
    $this->setName('');
    if (intval($id)) {
      $sql = "SELECT name FROM contact_groups WHERE id = '".intval($id)."'";
      $res = $this->db->query($sql);
      $row = $res->fetch();
      if (isset($row['name'])) {
        $this->setName($row['name']);
      }
    }
    
    $this->user = $container['auth'];
  }

  private function getTableName() {
    return "contact_groups";
  }

  public function getId() {
    return $this->id;
  }
  
  public function setId($id) {
    return $this->id = intval($id);
  }

  public function setName($name) {
    return $this->name = $name;
  }
  public function getName() {
    return $this->name;
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
    $sql .= " order by name";
    $res = $this->db->query($sql);
    $res = $this->db->query($sql, $onlyCount ? \PDO::FETCH_NUM  : \PDO::FETCH_ASSOC);
    $result = [];

    while ($row = $res->fetch()) {
      if ($onlyCount) {
        return intval($row[0]);
      }
      $row["queues"] =  $this->getQueues($row['id']);
      $row["users"]  =  $this->getUsers($row['id']);
      $result[]      = $row;
    }

    return $result;
  }

  public function getFullInfo() {    
    return [
      "id" => $this->getId(),
      "name" => $this->getName(),
      "acl_phones" => $this->getPhonesInfo(),
      "queues" => $this->getQueuesInfo(),
      "group_queues" => $this->getGroupsQueuesInfo()
    ];
  }

  public function getQueues($id) {
    $sql = "SELECT q.id, q.name
    FROM contact_groups_queues
    LEFT JOIN queue AS q ON (q.id = contact_groups_queues.queue_id)
    WHERE contact_groups_queues.contact_groups_id = '{$id}' AND contact_groups_queues.queue_id IS NOT NULL";
    $res = $this->db->query($sql, \PDO::FETCH_NUM);
    $ids = [];
    $names = [];
    while ($row = $res->fetch()) {
      $ids[] = $row[0];
      $names[] = $row[1];
    }
    return ["ids" => $ids, "names" => $names]; 
  }

  public function getUsers($id) {
    $sql = "SELECT u.id, u.name, contact_groups_items.phone
    FROM contact_groups_items
    LEFT JOIN acl_user AS u ON (u.id = contact_groups_items.acl_user_id)
    WHERE contact_groups_items.contact_groups_id = '{$id}' AND contact_groups_items.acl_user_id IS NOT NULL";
    $res = $this->db->query($sql, \PDO::FETCH_NUM);
    $ids = [];
    $names = [];
    while ($row = $res->fetch()) {
      $ids[] = $row[0];
      $names[] = $row[1];
      $phones[] = $row[2];
    }
    return ["ids" => $ids, "names" => $names, "phones" => $phones]; 
  }  

  public function save($name, $acl_phones, $queues, $group_queues) {
    if (intval($this->getId())) {
      $sql = " UPDATE contact_groups SET";
    } else {
      $sql = " INSERT INTO contact_groups SET";
    }
    if (!strlen($name)) {
      return [
        "result" => FALSE,
        "message" => "Имя не может быть пустым"
      ];
    }
    $sql .= " `name` = '".trim(addslashes($name))."'";
    if (intval($this->getId())) {
      $sql .= " WHERE id = '".$this->getId()."'";
    }
    $res = $this->db->query($sql);
    if ($res) {
      if (intval($this->getId())) {
      } else {
        $this->setId($this->db->lastInsertId());
      }
      $this->deletePhones();
      $this->deleteQueues();
      $this->deleteGroupsQueues();
      if (isset($acl_phones) && strlen($acl_phones)) {        
        $this->insertPhones($this->stringToArr($acl_phones));
      }
      if (isset($queues) && strlen($queues)) {        
        $this->insertQueues($this->stringToArr($queues));
      }
      if (isset($group_queues) && strlen($group_queues)) {        
        $this->insertGroupsQueues($this->stringToArr($group_queues));
      }
      return ["result" => TRUE, "message" => "Контактная группа сохранена"];
    }
    return ["result" => FALSE, "message" => "Произошла ошибка сохранения контактной группы"];
  }

  private function stringToArr($acl_phones) {
    $result = [];
    if (strlen(trim($acl_phones))) {
      $acl_phones_arr = explode(",", $acl_phones);
      if (COUNT($acl_phones_arr)) {
        foreach ($acl_phones_arr as $phone) {
          if (intval($phone)) {
            $result[] = $phone;
          }
        }
      }
    }
    return $result;
  }

  private function insertPhones($acl_users) {
    $user = new User();
    foreach ($acl_users as $user_id) {
      $phone = $user->getPhone($user_id);
      if (strlen($phone)) {
        $sql = "INSERT INTO contact_groups_items SET 
          `contact_groups_id` = '".$this->getId()."',
          `acl_user_id` = '".$user_id."',
          `phone` = '".$phone."',
          `queue` = ''
          ";
        $res = $this->db->query($sql);
      }
    }
  }

  private function insertQueues($group_queues) {
    foreach ($group_queues as $queue_id) {
      $sql = "INSERT INTO contact_groups_queues SET 
        `contact_groups_id` = '".$this->getId()."',
        `queue_id` = '".$queue_id."'";
      $res = $this->db->query($sql);
    }    
  }

  private function insertGroupsQueues($arr) {
    $queue = new PBXQueue();
    foreach ($arr as $value) {
      $queueName = $queue->getName($value);
      if (strlen($queueName)) {        
        $sql = "INSERT INTO contact_groups_items SET 
        `contact_groups_id` = '".$this->getId()."',
        `queue_id` = '".$value."',
        `queue` = '".$queueName."'";
      $res = $this->db->query($sql);
      }
    }
  }

  private function deletePhones() {
    if (intval($this->getId())) {
      $sql = "DELETE FROM contact_groups_items WHERE 
        `contact_groups_id` = '".$this->getId()."' AND `phone` IS NOT NULL AND `phone` != '' ";
      $this->db->query($sql);
      return true;
    }
    return false;
  }

  private function deleteQueues() {
    if (intval($this->getId())) {
      $sql = "DELETE FROM contact_groups_queues WHERE 
        `contact_groups_id` = '".$this->getId()."' AND `queue_id` IS NOT NULL";
      $this->db->query($sql);
      return true;
    }
    return false;
  } 

  private function deleteGroupsQueues() {
    if (intval($this->getId())) {
      $sql = "DELETE FROM contact_groups_items WHERE 
        `contact_groups_id` = '".$this->getId()."' AND `queue_id` IS NOT NULL AND `queue` != '' ";
      $this->db->query($sql);
      return true;
    }
    return false;
  }

  private function getGroupsQueuesInfo() {
    $result = [];
    if (intval($this->getId())) {
      $sql = "SELECT queue_id FROM contact_groups_items WHERE 
        `contact_groups_id` = '".$this->getId()."' AND `queue_id` IS NOT NULL AND `queue` != '' ";
      $res = $this->db->query($sql);
      while ($row = $res->fetch()) {
        if (intval($row['queue_id'])) {
          $result[] = $row['queue_id'];
        }
      }      
    }
    return $result;
  }

  private function getQueuesInfo() {
    $result = [];
    if (intval($this->getId())) {
      $sql = "SELECT queue_id FROM contact_groups_queues WHERE 
        `contact_groups_id` = '".$this->getId()."' AND `queue_id` IS NOT NULL";
      $res = $this->db->query($sql);
      while ($row = $res->fetch()) {
        if (intval($row['queue_id'])) {
          $result[] = $row['queue_id'];
        }
      }
    }
    return $result;
  } 

  private function getPhonesInfo() {
    $result = [];
    if (intval($this->getId())) {
      $sql = "SELECT acl_user_id FROM contact_groups_items WHERE 
        `contact_groups_id` = '".$this->getId()."' AND `phone` IS NOT NULL AND `phone` != '' ";
      $res = $this->db->query($sql);
      while ($row = $res->fetch()) {
        if (intval($row['acl_user_id'])) {
          $result[] = $row['acl_user_id'];
        }
      }
    }
    return $result;
  }
  
}
