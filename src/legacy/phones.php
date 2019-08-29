<?php

namespace Erpico;

class Phones {
  private $container;
  private $db;
  private $auth;

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getPhones($filter, $pos, $count = 20, $onlycount = 0) {
    
    // Here need to add permission checkers and filters

    if ($onlycount) {      
      $res = $this->db->query("SELECT COUNT(*) FROM acl_user");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

      $sql = "	SELECT A.name, A.fullname, B.val, A.description, C.ip, C.issued, C.updated, C.version 
			    FROM acl_user AS A 
			    LEFT JOIN cfg_user_setting AS B ON A.id = B.acl_user_id 
			    LEFT JOIN acl_auth_token AS C ON C.acl_user_id = A.id 
			    WHERE A.state = '1' AND B.handle = 'cti.ext' AND B.val != '' 
			    AND ((SELECT MAX(id) FROM acl_auth_token AS D WHERE D.acl_user_id = A.id AND D.pcode = 'Phone Manager') = C.id or C.id IS NULL) 
			    ORDER BY B.val asc, C.updated DESC";

    if ($count) {
      $sql .= " LIMIT $pos, $count";
    }

    $res = $this->db->query($sql);
    $result = [];
    $group_arr = [];
    while ($row = $res->fetch(\PDO::FETCH_NUM)) {
        $group = (($row[3]!="") ? $row[3] : 'Без имени');
        if(!in_array($group, $group_arr)) $group_arr[] = $group;
        $result[$group]['data'][] = [
            "number" => $row[2],
            "fio" => (strlen($row[1]) ? $row[1] : $row[0]),
            "group" => ""
        ];
    }

    $final_result = [];
    $final_row = [];
    for($i=0; $i<count($group_arr); $i++){
        $final_row['group'] = $group_arr[$i];
        $final_row['open'] = true;
        $final_row['data'] = $result[$group_arr[$i]]['data'];
        $final_result[] = $final_row;
    }

    return $final_result;
  }
}