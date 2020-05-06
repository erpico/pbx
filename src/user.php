<?php
namespace App\Middleware;
use Erpico\Rules;
class AuthUser
{    
    private $container;
    public function __construct($container) {
      $this->container = $container;
    }
    public function __invoke($request, $response, $req)
    {        
        //$route = $request->getAttribute('route');
        $token = $request->getParam('token', '');
        if (!strlen($token)) {
          $cookies = $request->getCookieParams();
          $token = isset($cookies['token']) ? trim($cookies['token'], '"') : '';
        }
        $this->container['auth']->auth($token);        
        $response = $req($request, $response);
        return $response;
    }

};

class OnlyAuthUser
{
    private $container;
    
    public function __construct($container) {
        $this->container = $container;
    }

    public function __invoke($request, $response, $req)
    {                                
        if (!$this->container['auth']->isAuth()) return $response->withJson(["error" => 1, "message" => "No auth"]);
        $response = $req($request, $response);
        return $response;
    }

};

namespace Erpico;

class User
{
  private $db;
  private $token;
  private $token_id = 0;
  private $id = 0;
  
  const ALLOWED_CONFIG_HANDLES = ['cfwd.all', 'cfwd.noanswer', 'cfwd.noanswer.timeout', 'cfwd.limit.from', 'cfwd.limit.to', 'cfwd.limit.days'];

  public function __construct($db = null, $_id = 0) {
    if (isset($db)) {
      $this->db = $db;
    } else {
      global $app;    
      $container = $app->getContainer();
      $this->db = $container['db'];
    }
    if (!intval($_id)) {
      $token_data = (isset($_POST['token']) ? self::checkToken($_POST['token']) : (isset($_GET['token']) ? self::checkToken($_GET['token']) : (isset($_COOKIE['pbx_token']) ? self::checkToken($_COOKIE['pbx_token']) : 0)));
      if (is_array($token_data)) {
        $this->id = $token_data['acl_user_id'];
        $this->token_id = $token_data['id'];
        $this->updateToken();
        $this->token = $token_data['token'];
      }
    }
  }

  public function getPhone($id) {
    if (!intval($id)) return "";
    $sql = "SELECT 
      C.val as phone
      FROM acl_user as U
      LEFT JOIN cfg_user_setting AS C ON (C.acl_user_id = U.id AND C.handle = 'cti.ext')
      WHERE U.id = {$id}";
    $res = $this->db->query($sql, \PDO::FETCH_NUM);
    $row = $res->fetch();
    if (is_array($row) && strlen($row[0])) {
      return $row[0];
    }
    return "";
    
  }

  public function auth($token) {
    // Check token
    $this->token = $token;
    $sql = "SELECT id, acl_user_id FROM acl_auth_token WHERE token='".addslashes($token)."' AND UNIX_TIMESTAMP(expire)>UNIX_TIMESTAMP(now())";
    $res = $this->db->query($sql);
    if ($row = $res->fetch(\PDO::FETCH_NUM)) {
      $sql = "UPDATE acl_auth_token SET updated=Now() WHERE id= {$row[0]}";
      $this->id = $row[1];
    } else {
      return 0;
    }
    return 1;
  }

  public function isAuth() {
    return $this->id ? 1 : 0;
  }

  public function login($login, $password, $ip) {
    $sql = "SELECT id, name, fullname
	          FROM acl_user
	          WHERE name='".addslashes($login)."' 
            AND password = sha1(md5(concat(md5(md5('".addslashes($password)."')), ';Ej>]sjkip')))";            
    $res = $this->db->query($sql);
    if ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
      $token = substr(sha1(sprintf("%s%d", $row['name'], round(microtime(1) * 1000))), 0, 32); 
      $sql = "INSERT INTO acl_auth_token (acl_user_id,token,pcode,version,ip,hwid,issued,updated,expire)
              VALUES ('" . $row['id'] . "','" . $token . "','web','3.0','" . $ip . "','',Now(),Now(),DATE_ADD(Now(), INTERVAL 24 HOUR))";
      if (!$this->db->query($sql)) return 0;
      $this->token = $token;
      return [
        'error' => 0,
        'token' => $token,        
        'fullname' => $row['fullname'],
        'ip' => $ip
      ];
    } else {
      return [
        'error' => 1,
        'message' => 'Bad login or password'
      ];
    }
  }

  private function updateToken() {
    $sql = "UPDATE acl_auth_token SET updated = Now(), expire = Now() + INTERVAL 5 DAY WHERE id = '".$this->token_id."' LIMIT 1";
    $this->db->query($sql);
  }

  private function checkToken($token) {
    $sql = "SELECT id, acl_user_id, token FROM acl_auth_token WHERE token = '".$token."'";
    $res = $this->db->query($sql);
  	if ($row = $res->fetch()) {
  		return $row;
  	}
  	return 0;    
  }

  public function logout() {
    $sql = "DELETE FROM acl_auth_token WHERE token = '{$this->token}' AND acl_user_id = '{$this->id}'";
    return $this->db->query($sql);
  }

  public function getInfo() {
    if (!$this->id) return 0;
    $sql = "SELECT id, name, fullname
            FROM acl_user
            WHERE id = {$this->id}";            
    $res = $this->db->query($sql);
    if ($row = $res->fetch(\PDO::FETCH_ASSOC)) {    
      return [
        'error' => 0,    
        'name' => $row['name'],
        'fullname' => $row['fullname'],    
      ];
    } else {
      return [
        'error' => 0,
        'message' => 'No auth'
      ];
    }
  }

  public function getExt(){
    $result_queue = $this->db->query("SELECT B.val FROM cfg_user_setting AS B WHERE acl_user_id = '{$this->id}' AND B.handle = 'cti.ext' LIMIT 1 ");
    $myrow_queue = $result_queue->fetch(\PDO::FETCH_ASSOC);
    return $myrow_queue['val']!="" ? $myrow_queue['val'] : $x;
  }

  public function saveExt($ext){
    $this->db->query("DELETE FROM cfg_user_setting  WHERE acl_user_id != '{$this->id}' AND handle = 'cti.ext' AND val = '".addslashes($ext)."'");
    $res = $this->db->query("SELECT id, val FROM cfg_user_setting WHERE acl_user_id = '{$this->id}' AND handle = 'cti.ext' LIMIT 1 ");
    $row = $res->fetch(\PDO::FETCH_ASSOC);
    if ($row && $row['val'] != $ext) {      
      $this->db->query("UPDATE cfg_user_setting SET val = '".addslashes($ext)."' WHERE id = '{$row['id']}' LIMIT 1");  
    } else if (!$row) {
      $this->db->query("INSERT INTO cfg_user_setting SET acl_user_id = '{$this->id}', handle = 'cti.ext', val = '".addslashes($ext)."'");  
    }
    return 0;
  }

  public function allow_extens() {
    if(isset($_COOKIE['token'])) {
        $token = $_COOKIE['token'];
    }
    else if(isset($_GET['token'])) {
        $token = "'".$_GET['token']."'";
    };

    $user_extens = [];
    $i = 0;
    $demand_user_defult_extens = "
    SELECT val
    FROM cfg_setting
    WHERE handle = 'cti.extens.allow.mask' ";
    $result_user_defult_extens = $this->db->query($demand_user_defult_extens);
    while ($myrow_user_defult_extens = $result_user_defult_extens->fetch(\PDO::FETCH_ASSOC)) {
        if ($myrow_user_defult_extens['val'] != "") {
            $b = explode(",", $myrow_user_defult_extens['val']);
            $c = count($b);
            for ($j = 0; $j < $c; $j++) {
                $search = 'X';
                $replace = '_';
                $user_extens[$i] = str_replace($search, $replace, $b[$j]);
                $i++;
            };
        };
    };

    $demand_user_extens = "
    SELECT val
    FROM cfg_user_setting
    WHERE handle = 'cti.extens.allow.mask'
    AND acl_user_id = (SELECT acl_user_id FROM acl_auth_token WHERE token=".$token.") ";
    $result_user_extens = $this->db->query($demand_user_extens);
    while ($myrow_user_extens = $result_user_extens->fetch(\PDO::FETCH_ASSOC)) {
        if ($myrow_user_extens['val'] != "") {
            $b = explode(",", $myrow_user_extens['val']);
            $c = count($b);
            for ($j = 0; $j < $c; $j++) {
                $search = 'X';
                $replace = '_';
                $user_extens[$i] = str_replace($search, $replace, $b[$j]);
                $i++;
            };
        };
    };

    $demand_user_group_extens = "
    SELECT A.val
    FROM cfg_group_setting AS A
    LEFT JOIN acl_user_group_has_users AS B ON (A.acl_user_group_id = B.acl_user_group_id)
    LEFT JOIN acl_user AS C ON (B.acl_user_id = C.id)
    WHERE C.id = (SELECT acl_user_id FROM acl_auth_token WHERE token=".$token.") 
    AND A.handle = 'cti.extens.allow.mask' ";
    $result_user_group_extens = $this->db->query($demand_user_group_extens);
    while ($myrow_user_group_extens = $result_user_group_extens->fetch(\PDO::FETCH_ASSOC)) {
        if ($myrow_user_group_extens['val'] != "") {
            $b = explode(",", $myrow_user_group_extens['val']);
            $c = count($b);
            for ($j = 0; $j < $c; $j++) {
                $search = 'X';
                $replace = '_';
                $user_extens[$i] = str_replace($search, $replace, $b[$j]);
                $i++;
            };
        };
    };
    return $user_extens;
  }
  
  public function allowed_queues()
  {
    if(isset($_COOKIE['token'])) {
        $token = $_COOKIE['token'];
    }
    else if(isset($_GET['token'])) {
        $token = "'".$_GET['token']."'";
    };
    $user_queues = [];
    $i = 0;
    $demand_user_defult_queues = "
     SELECT val
     FROM cfg_setting
     WHERE handle = 'cti.queues.allowed' ";
    $result_user_defult_queues = $this->db->query($demand_user_defult_queues);
    while ($myrow_user_defult_queues =$result_user_defult_queues->fetch(\PDO::FETCH_ASSOC)) {
        if ($myrow_user_defult_queues['val'] != "") {
            $b = explode(",", $myrow_user_defult_queues['val']);
            $c = count($b);
            for ($j = 0; $j < $c; $j++) {
                $user_queues[$i] = $b[$j];
                $i++;
            };
        };
    };

    $demand_user_queues = "
    SELECT val
    FROM cfg_user_setting
    WHERE handle = 'cti.queues.allowed'
    AND acl_user_id = (SELECT acl_user_id FROM acl_auth_token WHERE token=".$token.") ";
    $result_user_queues = $this->db->query($demand_user_queues);
    while ($myrow_user_queues = $result_user_queues->fetch(\PDO::FETCH_ASSOC)) {
        if ($myrow_user_queues['val'] != "") {
            $b = explode(",", $myrow_user_queues['val']);
            $c = count($b);
            for ($j = 0; $j < $c; $j++) {
                $user_queues[$i] = $b[$j];
                $i++;
            };
        };
    };

    $demand_user_group_queues = "
    SELECT A.val
    FROM cfg_group_setting AS A
    LEFT JOIN acl_user_group_has_users AS B ON (A.acl_user_group_id = B.acl_user_group_id)
    LEFT JOIN acl_user AS C ON (B.acl_user_id = C.id)
    WHERE C.id = (SELECT acl_user_id FROM acl_auth_token WHERE token=".$token.") 
    AND A.handle = 'cti.queues.allowed' ";
    $result_user_group_queues = $this->db->query($demand_user_group_queues);
    while ($myrow_user_group_queues = $result_user_group_queues->fetch(\PDO::FETCH_ASSOC)) {
        if ($myrow_user_group_queues['val'] != "") {
            $b = explode(",", $myrow_user_group_queues['val']);
            $c = count($b);
            for ($j = 0; $j < $c; $j++) {
                $user_queues[$i] = $b[$j];
                $i++;
            };
        };
    };
    return $user_queues;
  }
  
  public function getUsersList() {
    $sql = "SELECT A.name, A.fullname, B.val, A.description, C.ip, C.issued, C.updated, C.version 
            FROM acl_user AS A LEFT JOIN cfg_user_setting AS B ON A.id = B.acl_user_id LEFT JOIN acl_auth_token AS C ON C.acl_user_id = A.id 
            WHERE A.state = '1' AND B.handle = 'cti.ext' AND B.val != '' AND ((SELECT MAX(id) FROM acl_auth_token AS D WHERE D.acl_user_id = A.id) = C.id or C.id IS NULL)";

    $result = [];
    $res = $this->db->query($sql);
    while ($row = $res->fetch(\PDO::FETCH_NUM)) {
      $result[$row[2]] = ["number" => $row[2],
      "fio" => (strlen($row[1]) ? $row[1] : $row[0])];
    }
    return $result;
  }

  public function deny_numbers()
  {
    if(isset($_COOKIE['token'])) {
      $token = $_COOKIE['token'];
    }
    else if(isset($_GET['token'])) {
      $token = "'".$_GET['token']."'";
    };

    $user_deny = [];
    $i = 0;
    $demand_user_defult_deny = "
        SELECT val
        FROM cfg_setting
        WHERE handle = 'cti.spy_deny' ";
    $result_user_defult_deny = $this->db->query($demand_user_defult_deny);
    while ($myrow_user_defult_deny = $result_user_defult_deny->fetch(\PDO::FETCH_ASSOC)) {
      if ($myrow_user_defult_deny['val'] != "") {
        $b = explode(",", $myrow_user_defult_deny['val']);
        $c = count($b);
        for ($j = 0; $j < $c; $j++) {
          $user_deny[$i] = $b[$j];
          $i++;
        };
      };
    };

    $demand_user_deny = "
        SELECT val
        FROM cfg_user_setting
        WHERE handle = 'cti.spy_deny'
        AND acl_user_id = (SELECT acl_user_id FROM acl_auth_token WHERE token=".$token.") ";
    $result_user_deny = $this->db->query($demand_user_deny);
    while ($myrow_user_deny = $result_user_deny->fetch(\PDO::FETCH_ASSOC)) {
      if ($myrow_user_deny['val'] != "") {
        $b = explode(",", $myrow_user_deny['val']);
        $c = count($b);
        for ($j = 0; $j < $c; $j++) {
          $user_deny[$i] = $b[$j];
          $i++;
        };
      };
    };

    $demand_user_group_deny = "
        SELECT A.val
        FROM cfg_group_setting AS A
        LEFT JOIN acl_user_group_has_users AS B ON (A.acl_user_group_id = B.acl_user_group_id)
        LEFT JOIN acl_user AS C ON (B.acl_user_id = C.id)
        WHERE C.id = (SELECT acl_user_id FROM acl_auth_token WHERE token=".$token.") 
        AND A.handle = 'cti.spy_deny' ";
    $result_user_group_deny = $this->db->query($demand_user_group_deny);
    while ($myrow_user_group_deny = $result_user_group_deny->fetch(\PDO::FETCH_ASSOC)) {
      if ($myrow_user_group_deny['val'] != "") {
        $b = explode(",", $myrow_user_group_deny['val']);
        $c = count($b);
        for ($j = 0; $j < $c; $j++) {
          $user_deny[$i] = $b[$j];
          $i++;
        };
      };
    };
    return $user_deny;
  }

  public function fullname_queue($x){
    if (!is_string($x)) return $x;
    $result_queue = $this->db->query("SELECT fullname FROM queue WHERE name='".$x."' LIMIT 1 ");
    $myrow_queue = $result_queue->fetch(\PDO::FETCH_ASSOC);
    return $myrow_queue['fullname']!="" ? $myrow_queue['fullname'] : $x;
  }

  public function fullname_agent($x){
    $result_queue = $this->db->query("SELECT fullname FROM acl_user WHERE name='".$x."' LIMIT 1 ");
    $myrow_queue = $result_queue->fetch(\PDO::FETCH_ASSOC);
    return $myrow_queue['fullname']!="" ? $myrow_queue['fullname'] : $x;
  }

  public function fullname_agent_short($x, $l = 6){
    $result_queue = $this->db->query("SELECT fullname FROM acl_user WHERE name='".$x."' LIMIT 1 ");
    $myrow_queue = $result_queue->fetch(\PDO::FETCH_ASSOC);
    $fullname = $myrow_queue['fullname']!="" ? $myrow_queue['fullname'] : $x;

    return mb_substr($fullname,0,$l,"UTF-8");
  }

  public function getAgentPhone($x){
    $result_queue = $this->db->query("SELECT B.val FROM acl_user AS A LEFT JOIN cfg_user_setting AS B ON (A.id = B.acl_user_id) WHERE A.name = '$x' AND B.handle = 'cti.ext' LIMIT 1 ");
    $myrow_queue = $result_queue->fetch(\PDO::FETCH_ASSOC);
    return $myrow_queue['val']!="" ? $myrow_queue['val'] : $x;
  }

  public function fetchList($filter = "", $start = 0, $end = 20, $onlycount = 0, $shortlist = 0, $fullnameAsValue = 0, $likeStringValues = true)
  {
    if ($onlycount) {
      $sql = "SELECT 
      COUNT(*)
      FROM acl_user as U
      LEFT JOIN cfg_user_setting AS C ON (C.acl_user_id = U.id AND C.handle = 'cti.ext')
    ";
    } else {
      $sql = "SELECT 
      U.id, U.name, U.fullname, U.description, C.val as phone, U.state
      FROM acl_user as U
      LEFT JOIN cfg_user_setting AS C ON (C.acl_user_id = U.id AND C.handle = 'cti.ext')
    ";
    }
    $wsql = "";
    if (is_array($filter)) {
      if (isset($filter['name']) && strlen($filter['name'])) {
        if (strlen($wsql)) $wsql .= " AND ";
        $wsql .= " U.name LIKE '%".trim(addslashes($filter['name']))."%'";
      }
      if (isset($filter['fullname']) && strlen($filter['fullname'])) {
        if (strlen($wsql)) $wsql .= " AND ";
        $wsql .= " U.fullname ".($likeStringValues ? "LIKE '%" : "='" ).trim(addslashes($filter['fullname'])).($likeStringValues ? "%'" : "'" );
      }
      if (isset($filter['phone']) && strlen($filter['phone'])) {
        if (strlen($wsql)) $wsql .= " AND ";
        $wsql .= " C.val ".($likeStringValues ? "LIKE '%" : "='" ).trim(addslashes($filter['phone'])).($likeStringValues ? "%'" : "'" );
      }
      if (isset($filter['description']) && strlen($filter['description'])) {
        if (strlen($wsql)) $wsql .= " AND ";
        $wsql .= " U.description ".($likeStringValues ? "LIKE '%" : "='" ).trim(addslashes($filter['description'])).($likeStringValues ? "%'" : "'" );
      }
      if (isset($filter['state']) && intval($filter['state'])) {
        if (strlen($wsql)) $wsql .= " AND ";
        $wsql .= " U.state = '".intval($filter['state'])."%'";
      }
      if (isset($filter['id']) && intval($filter['id'])) {
        if (strlen($wsql)) $wsql .= " AND ";
        $wsql .= " U.id = '".intval($filter['id'])."%'";
      }
      if (isset($filter['value']) && strlen($filter['value'])) {
        if (strlen($wsql)) $wsql .= " AND ";
        $wsql .= " (U.name ".($likeStringValues ? "LIKE '%" : "='" ).trim(addslashes($filter['value'])).($likeStringValues ? "%'" : "'" )." OR U.fullname ".($likeStringValues ? "LIKE '%" : "='" ).trim(addslashes($filter['value'])).($likeStringValues ? "%'" : "'" ).")";
      }
    }
    if (strlen($wsql)) {
      $sql .= "WHERE ".$wsql;
    }
    if (!intval($onlycount)) {
      $sql .= " LIMIT {$start}, {$end}";
    }
    $res = $this->db->query($sql, $onlycount ? \PDO::FETCH_NUM  : \PDO::FETCH_ASSOC);
    $result = [];
    $rules = new PBXRules();

    while ($row = $res->fetch()) {
      if ($onlycount) {
        return intval($row[0]);
      }
      if (isset($row['id']) && intval($row['id'])) {
        $groups =  $this->getUserGroups(intval($row['id']));
        $row['user_groups'] = $groups['names'];
        $row['user_groups_ids'] = $groups['ids'];
      }
      if (intval($shortlist)) {
        if ($fullnameAsValue) {
          $result[] = ["id"=>$row["name"], "value"=>$row["fullname"]];
        } else {
          $result[] = ["id"=>$row["id"], "value"=>$row["name"], "phone"=>$row["phone"], "fullname" => $row["fullname"]];
        }
      } else {
        $row['config'] = $this->getUserConfig($row['id']);
        $row['rules'] = $rules->getUserRules($row['id']);
        $result[] = $row;
      }
    }

    return $result;
  }

  public function getUserGroups($id)
  {
    $sql = "SELECT
      distinct G.name, G.id
      FROM acl_user_group_has_users AS HR
      LEFT JOIN acl_user_group AS G ON (G.id = HR.acl_user_group_id)
      WHERE HR.acl_user_id = {$id}";
    $res = $this->db->query($sql,\PDO::FETCH_NUM);
    $result_str = [];
    $result_id = [];
    while ($row = $res->fetch()) {
      array_push($result_str, $row[0]);
      array_push($result_id, $row[1]);
    }

    return [
      "names" => $result_str,
      "ids" => $result_id
    ];
  }

  public function getUsersGroup($id)
  {
    $sql = "SELECT
      U.name, U.id
      FROM acl_user_group_has_users AS HR
      LEFT JOIN acl_user AS U ON (U.id = HR.acl_user_id)
      WHERE HR.acl_user_group_id = {$id}";
    $res = $this->db->query($sql,\PDO::FETCH_NUM);
    $result_str = [];
    $result_id = [];
    while ($row = $res->fetch()) {
      if (isset($row[0]) && isset($row[1])) {
        array_push($result_str, $row[0]);
        array_push($result_id, $row[1]);
      }
    }

    return [
      "names" => $result_str,
      "ids" => $result_id
    ];

  }

  public function addUpdate($params)
  {
    try {          
      if (intval($params['id'])) {
        $sql = " UPDATE acl_user SET ";
        $endsql = " WHERE id = ".intval($params['id']);
      } else {
        $sql = " INSERT INTO acl_user SET ";
      };
      if (isset($params['name']) && strlen(trim($params['name']))) {
        if (!$this->isUniqueColumn("name", $params['name'], $params['id'])) {
          return [ "result" => false, "message" => "Логин занят другим пользователем"];
        } else {
          $sql .= "`name` = '".trim(addslashes($params['name']))."',";
        }
      } else {
        return ["result" => false, "message" => "Логин не может быть пустым"];
      }

      if (isset($params['fullname']) && strlen(trim($params['fullname']))) {
        /*if (!$this->isUniqueColumn("fullname", $params['fullname'], $params['id'])) {
          return [ "result" => false, "message" => "Ф.И.О. занято другим пользователем"];
        } else {*/
          $sql .= "`fullname` = '".trim(addslashes($params['fullname']))."',";
        //}
      } else {
        return ["result" => false, "message" => "Ф.И.О. не может быть пустым"];
      }

      if (isset($params['state']) && intval($params['state'])) {
        $sql .= "`state` = '".intval($params['state'])."',";
      } else {
        return ["result" => false, "message" => "state can`t be empty"];
      }
      
      if (!intval($params['id'])) {
        if (isset($params['password']) && strlen($params['password'])) {
          $sql .= "password = sha1(md5(concat(md5(md5('".addslashes($params["password"])."')), ';Ej>]sjkip'))),";
        } else {
          return ["result" => false, "message" => "password can`t be empty"];
        }
      } else {
        if (isset($params['password']) && trim($params['password'])) {
          $sql .= "password = sha1(md5(concat(md5(md5('".addslashes($params["password"])."')), ';Ej>]sjkip'))),";
        }
      }
      $sql .= "`description` = '".trim(addslashes($params['description']))."',";

      if (intval($params['id'])) {
        $sql .= "`updated` = NOW()";
      } else {
        $sql .= "`created` = NOW()";
      }
      if (isset($endsql)) {
        $sql .= $endsql;
      }
      $res = $this->db->query($sql);
      if ($res) {
        if (intval($params['id'])) {
          $id = intval($params['id']);
          $sql = "UPDATE cfg_user_setting SET ";
          $end_sql = " WHERE acl_user_id = {$id} AND handle = 'cti.ext' LIMIT 1";
        } else {
          $id = $this->db->lastInsertId();
          $sql = "INSERT INTO cfg_user_setting SET ";
        }
        $rules = new PBXRules();
        $rules->saveUser($params['rules'], $id);
        $sql .= "acl_user_id = '{$id}', handle = 'cti.ext', val = '{$params['phone']}', updated = NOW()";
        if(isset($end_sql)) {
          $sql .= $end_sql;
        }
        $res = $this->db->query($sql);
        if (isset($params['config'])) {
          $this->saveUserConfig($id, json_decode($params['config'], true));
        }
        if (isset($params['user_groups_ids'])) {
          $groups = explode(",",$params['user_groups_ids']);
          $groups_int = [];
          foreach ($groups as $group) {
            if (intval($group)) $groups_int[] = intval($group);
          }
          if (is_array($groups_int) && COUNT($groups_int)) {
            
            $this->saveUserGroups($groups_int,$id);
          }
        }
      }
    } catch (\Throwable $th) {
      return ["result" => false, "message" => "Произошла ошибка выполнения операции", "info" => $th];
    }

    return ["result" => true, "message" => "Операция прошла успешно"];
  }
  
  private function getUserConfig($userId)
  {
    $sql = "SELECT handle, val FROM cfg_user_setting WHERE acl_user_id = $userId";
    $res = $this->db->query($sql);
    $result = [];
    while ($res && $row = $res->fetch()) {
      $result[] = ['key' => $row['handle'], 'value' => $row['val']];
    }
    
    return $result;
  }
  
  private function saveUserConfig($userId, $params)
  {
    $paramsKeys = array_column($params, 'key');
    $paramsValues = array_column($params, 'value');
    foreach (self::ALLOWED_CONFIG_HANDLES  as $handle) {
      $key = array_search($handle, $paramsKeys);
      if ($key !== FALSE && strlen($paramsValues[$key])) {
        $this->addUpdateUserConfigByHandle($userId, $paramsKeys[$key], $paramsValues[$key]);
      } else $this->deleteUserConfigByHandle($userId, $handle);
    }
  }
  private function deleteUserConfigByHandle($userId, $handle)
  {
    $result = false;
    $config = $this->getUserConfigByHandle($userId, $handle);
    if ($config) {
      $sql = "DELETE FROM cfg_user_setting WHERE id = {$config[id]}";
      $res = $this->db->query($sql);
      $result = $res ? true : false;
    }
    
    return $result;
  }
  
  private function addUpdateUserConfigByHandle($userId, $handle, $value)
  {
    $config = $this->getUserConfigByHandle($userId, $handle);
    $sql = 'INSERT INTO cfg_user_setting';
    if ($config) {
      $sql = 'UPDATE cfg_user_setting';
      $endSql ="WHERE id = {$config[id]}";
    }
    $sql .= " SET val = '".trim(addslashes($value))."', handle = '".trim(addslashes($handle))."', updated = NOW(), acl_user_id = '{$userId}'";
    if (isset($endSql)) $sql .= ' '.$endSql;
    $res = $this->db->query($sql);
    
    return $res ? true : false;
  }
  
  private function getUserConfigByHandle($userId, $handle)
  {
    $result = [];
    $res = $this->db->query("SELECT id, val FROM cfg_user_setting WHERE acl_user_id = '{$userId}' AND handle = '{$handle}' LIMIT 1");
    $row = $res->fetch();
    if ($row) {
      $result = ['id' => $row['id'], 'value' => $row['val'], 'handle' => $handle];
    }
    
    return $result;
  }

  public function remove($id) {
    try {
      if (!intval($id)) {
        return ["result" => false, "message" => "# пользователя не может быть пустым"];
      }
      if ($this->db->query("UPDATE acl_user SET state=3 WHERE id = ".intval($id))) {
//        $this->deleteUserGroupsExceptFor([0],$id);
        return ["result" => true, "message" => "Удаление прошло успешно"];
      }
    } catch (Exception $ex) {
      $this->logger->error($ex->getMessage()." ON LINE ".$ex->getLine());
      return ["result" => false, "message" => "Произошла ошибка удаления"];
    }
  }
  
  public function deleteUserGroupsExceptFor($ids, $user_id) {
    if (is_array($ids)) {
    $sql = "DELETE FROM acl_user_group_has_users WHERE acl_user_group_id NOT IN (".implode(",",$ids).") AND acl_user_id = {$user_id}";
    $this->db->query($sql);
    }
  }

  public function saveUserGroups($group_ids, $user_id) {
    if (is_array($group_ids)) {
      foreach ($group_ids as $id) {
        $sql = "SELECT COUNT(*) FROM acl_user_group_has_users WHERE
        acl_user_group_id = ".intval($id)." AND acl_user_id = {$user_id}";
        $res = $this->db->query($sql, \PDO::FETCH_NUM);
        $row = $res->fetch();
        if (!intval($row[0])) {
          $sql = "INSERT INTO acl_user_group_has_users 
          (acl_user_id, acl_user_group_id) 
          VALUES 
          ({$user_id},".intval($id).")";
          $this->db->query($sql);
        }
      }
    }
  }
  

  public function fetchGroups()
  {
    $sql = "SELECT id, name as value FROM acl_user_group";
    $res = $this->db->query($sql,\PDO::FETCH_ASSOC);
    $result = [];
    while ($row = $res->fetch()) {
      array_push($result, $row);
    }

    return $result;
  }
  public function getAllGroups($filter = "", $start = 0, $end = 20, $onlycount = 0, $likeStringValues = true)
  {
    if (intval($onlycount)) {
      $sql = "SELECT COUNT(*) FROM acl_user_group";
    } else {
      $sql = "SELECT id, name, description FROM acl_user_group";
    }
    $wsql = "";
    if (is_array($filter)) {
      if (isset($filter['name']) && strlen(trim(addslashes($filter['name'])))) {
        if (strlen($wsql)) $wsql .= " AND ";
        $wsql .= "`name` ".($likeStringValues ? "LIKE '%" : "='" )."".trim(addslashes($filter['name'])).($likeStringValues ? "%'" : "'" );
      }
      if (isset($filter['id']) && intval(addslashes($filter['id']))) {
        if (strlen($wsql)) $wsql .= " AND ";
        $wsql .= "`id` = '".intval($filter['id'])."'";
      }
      if (isset($filter['description']) && strlen(trim(addslashes($filter['description'])))) {
        if (strlen($wsql)) $wsql .= " AND ";
        $wsql .= "`description` ".($likeStringValues ? "LIKE '%" : "='" )."".trim(addslashes($filter['description'])).($likeStringValues ? "%'" : "'" );
      }
    }
    if (strlen($wsql)) {
      $sql .= " WHERE ".$wsql;
    }
    $res = $this->db->query($sql,intval($onlycount) ? \PDO::FETCH_NUM : \PDO::FETCH_ASSOC);
    $result = [];
    $rules = new PBXRules();
    while ($row = $res->fetch()) {
      if (intval($onlycount)) {
        return $row[0];
      }
      $users = $this->getUsersGroup(intval($row['id']));
      $row['list_users_str'] = $users['names'];
      $row['list_users_ids'] = $users['ids'];
      $row['rules'] = $rules->getGroupRules($row['id']);
      array_push($result, $row);
    } 
    return $result;
  }

  public function isUniqueColumn($column, $value, $id = 0)
  {    
    try {
      //$data = $this->fetchList([$column => $value], 0, 3, 0, 0, 0, 0);      
      $sql = " SELECT id FROM acl_user WHERE $column = '".addslashes($value)."'";
      $res = $this->db->query($sql, \PDO::FETCH_NUM);
      $data = $res->fetchAll();
      if (is_array($data)) {
        if (COUNT($data) > 1) {
          return false;
        } else if (COUNT($data) == 1){
          if (intval($id)) {
            return $data[0][0] == intval($id);
          } else {
            return false;
          }        
        }
        return true;
      }
    } catch (\Throwable $th) {
      $th->getMessage();
    }
  }

  public function isUniqueColumnGroup($column, $value, $id = 0)
  {    
    try {
      $data = $this->getAllGroups([$column => $value], 0, 3, 0, 0);
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
        return true;
      }
    } catch (\Throwable $th) {
      $th->getMessage();
    }
  }
  
  /**
   * @param $id
   *
   * @return array
   */
  public function removeGroup($id)
  {
    try {
      if (!intval($id)) {
        return ["result" => false, "message" => "# группы не может быть пустым"];
      }
      if ($this->db->query("DELETE FROM acl_user_group WHERE id = ".intval($id))) {
        $this->deleteUsersFromGroupsExceptFor($id, [0]);
        return ["result" => true, "message" => "Удаление прошло успешно"];
      }
    } catch (Exception $ex) {
      $this->logger->error($ex->getMessage()." ON LINE ".$ex->getLine());
      return ["result" => false, "message" => "Произошла ошибка удаления"];
    }
  }
  
  public function addUpdateGroup($params)
  {
    try {
      if (isset($params['id']) && intval($params['id'])) {
        $sql = "UPDATE acl_user_group SET ";
        $end_sql = " WHERE id = '".intval($params['id'])."'";
      } else {
        $sql = "INSERT INTO acl_user_group SET ";
      }
      if (isset($params['name']) && strlen(trim($params['name']))) {
        if (!$this->isUniqueColumnGroup("name", $params['name'], $params['id'])) {
          return [ "result" => false, "message" => "Название занято другой группой"];
        }
      } else {
        return ["result" => false, "message" => "Название не может быть пустым"];
      }
      $sql .= "`name` = '".trim(addslashes($params['name']))."'";
      if (isset($params['description']) && strlen(trim($params['description']))) {
        $sql .= ",`description` = '".trim(addslashes($params['description']))."'";
      }
      if (isset($end_sql)) {
        $sql .= $end_sql;
      }
      $res = $this->db->query($sql);
      if ($res) {
        if (isset($params['id']) && intval($params['id'])) {
          $id = $params['id'];
        } else {
          $id = $this->db->lastInsertId();
        }
        $rules = new PBXRules();
        $rules->saveGroup($params['rules'], $id);
        if (isset($params['list_users_ids']) && trim(strlen($params['list_users_ids']))){
          $users = explode(",",trim($params['list_users_ids']));
          $ids = [];
          foreach ($users as $user_id) {
            if (intval($user_id)) {
              $ids[] = intval($user_id);
            }          
          }
          if (COUNT($ids)) {
            $this->deleteUsersFromGroupsExceptFor($id, $ids);
            foreach ($ids as $u_id) {
              $this->saveUserGroups([$id], $u_id);
            }
          }
        } else {
          $this->deleteUsersFromGroupsExceptFor($id, [0]);
        }      
        
      }
    } catch (\Throwable $th) {
      return ["result" => false, "message" => "Произошла ошибка выполнения операции", "info" => $th ];
    }
    return ["result" => true, "message" => "Операция прошла успешно" ];
  }

  public function deleteUsersFromGroupsExceptFor($group_id, $user_ids)
  {
    $sql = "DELETE FROM acl_user_group_has_users WHERE acl_user_group_id = {$group_id} AND acl_user_id NOT IN (".implode(",",$user_ids).")";
    $this->db->query($sql);
    return true;
  } 
}
