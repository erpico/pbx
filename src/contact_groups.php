<?php

use App\ExportImport;
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
    $this->logger = $container['logger'];
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
  
  /**
   * @param $id
   *
   * @return array
   */
  public function remove($id) {
    try {
      if (!intval($id)) {
        return ["result" => false, "message" => "# группы не может быть пустым"];
      }
      if ($this->db->query("DELETE FROM ".self::getTableName()." WHERE id = ".intval($id))) {
        $this->deleteQueues();
        $this->deleteItems();
        return ["result" => true, "message" => "Удаление прошло успешно"];
      }
    } catch (Exception $ex) {
      $this->logger->error($ex->getMessage()." ON LINE ".$ex->getLine());
      return ["result" => false, "message" => "Произошла ошибка удаления"];
    }
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
    $sql .= " order by name";
    $res = $this->db->query($sql);
    $res = $this->db->query($sql, $onlyCount ? \PDO::FETCH_NUM  : \PDO::FETCH_ASSOC);
    $result = [];

    while ($row = $res->fetch()) {
      if ($onlyCount) {
        return intval($row[0]); 
      }
      $row["queue"] =  $row['queue'];
      $row["queues"] =  $this->getQueues($row['id']);
      $row["items"]  =  [
        "users" => $this->getUserItems($row['id']),
        "queues" => $this->getQueueItems($row['id'])
      ];
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
    $sql = "SELECT q.id, q.fullname
    FROM contact_groups_queues
    LEFT JOIN queue AS q ON (q.id = contact_groups_queues.queue_id)
    WHERE contact_groups_queues.contact_groups_id = '{$id}'";
    $res = $this->db->query($sql, \PDO::FETCH_ASSOC);
    $result = [];
    while ($row = $res->fetch()) {
      $result[] = [ "id" => intval($row['id']), "fullname" => $row['fullname']];
    }
    return $result; 
  }

  public function getQueueItems($id) {
    $sql = "SELECT q.id, q.fullname
    FROM contact_groups_items
    LEFT JOIN queue AS q ON (q.id = contact_groups_items.queue_id)
    WHERE contact_groups_items.contact_groups_id = '{$id}' AND contact_groups_items.queue_id IS NOT NULL";
    $res = $this->db->query($sql, \PDO::FETCH_ASSOC);
    $result = [];
    while ($row = $res->fetch()) {
      $result[] = [ "id" => intval($row['id']), "fullname" => $row['fullname']];
    }
    return $result; 
  }

  public function getUserItems($id) {
    $sql = "SELECT u.id, u.fullname
    FROM contact_groups_items
    LEFT JOIN acl_user AS u ON (u.id = contact_groups_items.acl_user_id)
    WHERE contact_groups_items.contact_groups_id = '{$id}' AND contact_groups_items.acl_user_id IS NOT NULL";
    $res = $this->db->query($sql, \PDO::FETCH_ASSOC);
    $result = [];
    while ($row = $res->fetch()) {
      $result[] = [ "id" => intval($row['id']), "fullname" => $row['fullname']];
    }
    return $result; 
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

  private function isUniqueColumn($column, $value) {
    if (in_array($column, self::FIELDS)) {
      $data = $this->fetchList([$column => $value], 0, 3, 0, 0);      
      if (is_array($data)) {
        if (COUNT($data) > 1) {
          return false;
        } else if (COUNT($data) == 1){
          if (intval($this->getId())) {
            return $data[0]["id"] == intval($this->getId());
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

  public function save($name, $queues, $items_users, $items_queues) {
    if (intval($this->getId())) {
      $sql = " UPDATE contact_groups SET";
    } else {
      $sql = " INSERT INTO contact_groups SET";
    }
    if (isset($name) && strlen($name)) {
      if (!$this->isUniqueColumn("name", $name)) {
        return [ "result" => false, "message" => "Название занято другой группой"];
      }
    } else {
      return [ "result" => false, "message" => "Название не может быть пустым"];
    }
    $sql .= " `name` = '".trim(addslashes($name))."'";
    if (intval($this->getId())) {
      $sql .= " WHERE id = '".$this->getId()."'";
    }
    $res = $this->db->query($sql);
    if ($res) {
      if (!intval($this->getId())) {     
        $this->setId($this->db->lastInsertId());
      }

      $this->deleteQueues();
      $this->deleteItems();

      $this->insertQueues($this->stringToArr($queues));
      $this->insertItemsUsers($this->stringToArr($items_users));
      $this->insertItemsQueues($this->stringToArr($items_queues));

      
      return ["result" => TRUE, "message" => "Контактная группа сохранена"];
    }
    return ["result" => FALSE, "message" => "Произошла ошибка сохранения контактной группы"];
  }

  private function stringToArr($str) {
    $result = [];    
    if (strlen(trim($str))) {
      $arr = explode(",", $str);
      if (COUNT($arr)) {
        foreach ($arr as $e) {
          if (intval($e)) {
            $result[] = intval($e);
          }
        }
      }
    }    
    return $result;
  }

  private function insertQueues($group_queues) {
    foreach ($group_queues as $queue_id) {
      $sql = "INSERT INTO contact_groups_queues SET 
        `contact_groups_id` = '".$this->getId()."',
        `queue_id` = '".$queue_id."'";
      $res = $this->db->query($sql);
    }    
  }

  private function insertItemsQueues($arr) {    
    foreach ($arr as $value) {                   
        $sql = "INSERT INTO contact_groups_items SET 
        `contact_groups_id` = '".$this->getId()."',
        `queue_id` = '".$value."', `queue` = '' ";
        $res = $this->db->query($sql);      
    }
  }

  private function insertItemsUsers($arr) {    
    foreach ($arr as $value) {            
        $sql = "INSERT INTO contact_groups_items SET 
        `contact_groups_id` = '".$this->getId()."',
        `acl_user_id` = '".$value."', `queue` = ''";
        $res = $this->db->query($sql);      
    }
  }

  private function deleteItems() {
    if (intval($this->getId())) {
      $sql = "DELETE FROM contact_groups_items WHERE 
        `contact_groups_id` = '".$this->getId()."'";
      $this->db->query($sql);
      return true;
    }
    return false;
  }

  private function deleteQueues() {
    if (intval($this->getId())) {
      $sql = "DELETE FROM contact_groups_queues WHERE 
        `contact_groups_id` = '".$this->getId()."'";
      $this->db->query($sql);
      return true;
    }
    return false;
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

  public function export() {
    $result = [];

    $queueObject =  new PBXQueue();
    //
    foreach ($this->fetchList(null, 0, null, 0) as $item) {

      $queues_in = [];
      foreach ($item['queues'] as $queue_in) {
        $queues_in[] = $queueObject->getName($queue_in['id']);
      }

      $queues_in_group = [];
      foreach ($item['items']['queues'] as $queue_in_group) {
        $queues_in_group[] = $queueObject->getName($queue_in_group['id']);
      }

      $users_in_group = [];
      foreach ($item['items']['queues'] as $user_in_group) {
        $users_in_group[] = $this->user->getNameById($user_in_group['id']);
      }

      unset($item['id']);
      unset($item['queues']);
      unset($item['items']);

      $item['queues_in'] = $queues_in;
      $item['queues_in_group'] = $queues_in_group;
      $item['users_in_group'] = $users_in_group;

      $result['contact_groups'][] = $item;
    }

    return $result;
  }

  public function import($data, $delete = false) {
    $result = true;
    $exportImport = new ExportImport();

    $queueObject = new PBXQueue();

    //
    if ($delete) {
      $exportImport->truncateTables([
        "contact_groups",
        "contact_groups_items",
        "contact_groups_queues"
      ]);
    }

    $contact_groups = $data->contact_groups;

    foreach ($contact_groups as $item) {
      $item->queue = intval($queueObject->getIdByName($item->queue_in))?:null;
      // группы
      $contactGroupId = $exportImport->importAction($item, ["name", "queue"], "contact_groups");
      // очереди
      foreach ($item->queues_in as $queue_in) {
        $queueId = $queueObject->getIdByName($queue_in);
        $array = [
          "contact_groups_id" => $contactGroupId,
          "queue_id" => $queueId
          ];

        $exportImport->importAction($array, ["contact_groups_id", "queue_id"],"contact_groups_queues");
      }
      // юзеры и очереди в группе
      foreach ($item->queues_in_group as $queue_in_group) {
        //$userId = $this->user->getIdByName($item->user);
        $arrayQueue = [
          "contact_groups_id" => $contactGroupId,
          "queue" => $queue_in_group,
          "queue_id" => $queueObject->getIdByName($queue_in_group),
          "acl_user_id" => null,
          "phone" => null
        ];
        $exportImport->importAction($arrayQueue, ["contact_groups_id", "queue", "queue_id", "acl_user_id", "phone"],"contact_groups_items");
      }

      foreach ($item->users_in_group as $users_in_group) {

        $userId = $this->user->getIdByName($users_in_group);
        $arrayUsers = [
          "contact_groups_id" => $contactGroupId,
          "queue" => "",
          "queue_id" => null,
          "acl_user_id" => $userId,
          "phone" => $this->user->getPhone($userId)
        ];

        $exportImport->importAction($arrayUsers, ["contact_groups_id", "queue", "queue_id", "acl_user_id", "phone"],"contact_groups_items");
      }
    }

    return $result;
  }
}
