<?php

namespace Erpico;

class Analysis_outgoing_calls
{
  private $container;
  private $db;
  private $auth;

  public function __construct($contaiter)
  {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  // Time settings
  /*
      $t1 = date("Y-m-d H:i:s", time() - 86400);
      $t2 = date("Y-m-d H:i:s");

      if(isset($filter['t1']) && isset($filter['t2'])) {
          $t1 = $filter['t1'];
          $t2 = $filter['t2'];
      };
  */

  public function getTraffic_for_period($filter, $pos, $count = 20, $onlycount = 0)
  {
    $utils = new Utils();

    if ($onlycount) {
      return 5;
    }

    $sql = "	SELECT COUNT(id) AS cnt, FLOOR(SUM(IF(disposition = 'ANSWERED',duration,0))/60) AS sum 
                FROM cdr 
                WHERE LENGTH(src) IN (3,4) AND LENGTH(dst) IN (3,4) AND calldate >= '" . $filter['t1'] . "' AND calldate <= '" . $filter['t2'] . "'";

    $ext = $this->auth->allow_extens();
    $extens = $utils->sql_allow_extens($ext);
    $sql .= $extens;

    $total_arr = [];
    $result = $this->db->query($sql);
    $myrow = $result->fetch(\PDO::FETCH_ASSOC);
    $total_arr[0]['name'] = "Внутренние";
    $total_arr[0]['count'] = $myrow['cnt'];
    $total_arr[0]['duration'] = $utils->time_format($myrow['sum']);

    $sql = "	SELECT COUNT(id) AS cnt, FLOOR(SUM(IF(disposition = 'ANSWERED',duration,0))/60) AS sum 
            FROM cdr 
            WHERE LENGTH(src) IN (3,4) AND LENGTH(dst) IN (5,6) AND calldate >= '" . $filter['t1'] . "' AND calldate <= '" . $filter['t2'] . "'";

    $sql .= $extens;

    $result = $this->db->query($sql);
    $myrow = $result->fetch(\PDO::FETCH_ASSOC);
    $total_arr[1]['name'] = "Городские";
    $total_arr[1]['count'] = $myrow['cnt'];
    $total_arr[1]['duration'] = $utils->time_format($myrow['sum']);

    $sql = "	SELECT COUNT(id) AS cnt, FLOOR(SUM(IF(disposition = 'ANSWERED',duration,0))/60) AS sum 
            FROM cdr 
            WHERE LENGTH(src) IN (3,4) AND LENGTH(dst) > 9 AND dst LIKE '89%' AND calldate >= '" .  $filter['t1'] . "' AND calldate <= '" . $filter['t2'] . "'";

    $sql .= $extens;

    $result = $this->db->query($sql);
    $myrow = $result->fetch(\PDO::FETCH_ASSOC);
    $total_arr[2]['name'] = "Сотовые";
    $total_arr[2]['count'] = $myrow['cnt'];
    $total_arr[2]['duration'] = $utils->time_format($myrow['sum']);

    $sql = "	SELECT COUNT(id) AS cnt, FLOOR(SUM(IF(disposition = 'ANSWERED',duration,0))/60) AS sum 
            FROM cdr 
            WHERE LENGTH(src) IN (3,4) AND LENGTH(dst) > 9 AND dst NOT LIKE '89%' AND dst NOT LIKE '810%' AND calldate >= '" . $filter['t1'] . "' AND calldate <= '" . $filter['t2'] . "'";

    $sql .= $extens;

    $result = $this->db->query($sql);
    $myrow = $result->fetch(\PDO::FETCH_ASSOC);
    $total_arr[3]['name'] = "Межгород";
    $total_arr[3]['count'] = $myrow['cnt'];
    $total_arr[3]['duration'] = $utils->time_format($myrow['sum']);

    $sql = "	SELECT COUNT(id) AS cnt, FLOOR(SUM(IF(disposition = 'ANSWERED',duration,0))/60) AS sum 
            FROM cdr 
            WHERE LENGTH(src) IN (3,4) AND LENGTH(dst) > 9 AND dst LIKE '810%' AND calldate >= '" .  $filter['t1'] . "' AND calldate <= '" . $filter['t2'] . "'";

    $result = $this->db->query($sql);
    $myrow = $result->fetch(\PDO::FETCH_ASSOC);
    $total_arr[4]['name'] = "Международные";
    $total_arr[4]['count'] = $myrow['cnt'];
    $total_arr[4]['duration'] = $utils->time_format($myrow['sum']);

    return $total_arr;
  }

  public function getPopular_city_for_period($filter, $pos, $count = 20, $onlycount = 0)
  {
    $utils = new Utils();

    if ($onlycount) {
      $ssql = " COUNT(*) ";
    } else {
      $ssql = " dst AS dst, count(dst) AS count_dst, FLOOR(SUM(IF(disposition = 'ANSWERED',duration,0))/60) AS floor, GROUP_CONCAT(DISTINCT src) AS group_src ";
    }

    $sql = "	SELECT ".$ssql." FROM cdr 
      WHERE length(dst) IN (5,6) and length(src) IN (3,4) AND calldate >= '" .  $filter['t1'] . "' AND calldate <= '" . $filter['t2'] . "'";

    $ext = $this->auth->allow_extens();
    $extens = $utils->sql_allow_extens($ext);
    $sql .= $extens;

    $sql .= "	group by dst order by SUM(IF(disposition = 'ANSWERED',duration,0)) desc LIMIT {$pos},{$count}";
    if ($onlycount) {
      $res = $this->db->query($sql);
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }
    $result = $this->db->query($sql);
    $i = 0;
    while ($myrow[$i] = $result->fetch(\PDO::FETCH_BOTH)) {
        $i++;
    };
    $total_arr = [];
    for ($j = 0; $j < $i; $j++) {
        $total_arr[$j] = $myrow[$j];
        $total_arr[$j]['floor'] = $utils->time_format($myrow[$j]['floor']);
    };

    return $total_arr;
  }

  public function getPopular_longdistance_over_period($filter, $pos, $count = 20, $onlycount = 0)
  {
    $utils = new Utils();
    $total_arr = [];

    if ($onlycount) {
      $ssql = " COUNT(*) ";
    } else {
      $ssql = " dst AS dst, count(dst) AS count_dst, FLOOR(SUM(IF(disposition = 'ANSWERED',duration,0))/60) AS floor, GROUP_CONCAT(DISTINCT src) AS group_src  ";
    }

    $sql = "SELECT ".$ssql." FROM cdr 
    WHERE length(dst) > 10 and dst not like '89%' and length(src) IN (3,4) AND calldate >= '" . $filter['t1'] . "' AND calldate <= '" . $filter['t2'] . "'";

    $ext = $this->auth->allow_extens();
    $extens = $utils->sql_allow_extens($ext);
    $sql .= $extens;

    $sql .= " group by dst order by SUM(IF(disposition = 'ANSWERED',duration,0)) desc LIMIT 20";

    if ($onlycount) {
      $res = $this->db->query($sql);
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    $result = $this->db->query($sql);
    $i = 0;
    while ($myrow[$i] = $result->fetch(\PDO::FETCH_BOTH)) {
        $i++;
    };
    for ($j = 0; $j < $i; $j++) {
        $total_arr[$j] = $myrow[$j];
        $total_arr[$j]['floor'] = $utils->time_format($myrow[$j]['floor']);
    };
    return $total_arr;
  }

  public function getPopular_cell_for_period($filter, $pos, $count = 20, $onlycount = 0)
  {
    $utils = new Utils();
    $total_arr = [];

    if ($onlycount) {
      $ssql = " count(*) ";
    } else {
      $ssql = "dst AS dst, count(dst) AS count_dst, FLOOR(SUM(IF(disposition = 'ANSWERED',duration,0))/60) AS floor, GROUP_CONCAT(DISTINCT src) AS group_src ";
    }

    $sql = "SELECT ".$ssql." FROM cdr 
      WHERE length(dst) > 10 and dst like '89%' and length(src) IN (3,4) AND calldate >= '" . $filter['t1'] . "' AND calldate <= '" . $filter['t2'] . "'";

    $ext = $this->auth->allow_extens();
    $extens = $utils->sql_allow_extens($ext);
    $sql .= $extens;

    $sql .= "	group by dst order by SUM(IF(disposition = 'ANSWERED',duration,0)) desc LIMIT 20";
    
    if ($onlycount) {
      $res = $this->db->query($sql);
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }
    $result = $this->db->query($sql);
    $i = 0;
    while ($myrow[$i] = $result->fetch(\PDO::FETCH_BOTH)) {
        $i++;
    };
    for ($j = 0; $j < $i; $j++) {
        $total_arr[$j] = $myrow[$j];
        $total_arr[$j]['floor'] = $utils->time_format($myrow[$j]['floor']);
    };
    return $total_arr;
  }

  public function getMost_calling_employees_for_period($filter, $pos, $count = 20, $onlycount = 0)
  {
    $utils = new Utils();
    $total_arr = [];

    if ($onlycount) {
      $ssql = " count(*) ";
    } else {
      $ssql = "src AS src, count(src) AS count_src, FLOOR(SUM(IF(disposition = 'ANSWERED',duration,0))/60) AS floor, GROUP_CONCAT(DISTINCT dst) AS group_dst ";
    }

    $sql = "SELECT ".$ssql." FROM cdr 
      WHERE length(dst) > 4 and length(src) IN (3,4) AND calldate >= '" . $filter['t1'] . "' AND calldate <= '" . $filter['t2'] . "'";

    $ext = $this->auth->allow_extens();
    $extens = $utils->sql_allow_extens($ext);
    $sql .= $extens;

    $sql .= "	group by src order by SUM(IF(disposition = 'ANSWERED',duration,0)) desc LIMIT 20";
    if ($onlycount) {
      $res = $this->db->query($sql);
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }
    $result = $this->db->query($sql);
    $i = 0;
    while ($myrow[$i] = $result->fetch(\PDO::FETCH_BOTH)) {
        $i++;
    };
    for ($j = 0; $j < $i; $j++) {
        $total_arr[$j] = $myrow[$j];
        $total_arr[$j]['floor'] = $utils->time_format($myrow[$j]['floor']);
        $b = explode(",", $myrow[$j]['group_dst']);
        $c = count($b);
        $group_dst = "";
        for ($z = 0; $z < $c; $z++) {
            $group_dst = $group_dst . $b[$z];
            if ($z != $c - 1) $group_dst = $group_dst . ", ";
        };
        $total_arr[$j]['group_dst'] = $group_dst;
    };
    return $total_arr;
  }
}



