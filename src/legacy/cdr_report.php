<?php

namespace Erpico;

class Cdr_report {
  private $container;
  private $db;
  private $auth;  

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getCdr_report($filter, $pos, $count = 20, $stat = 0) {

    $wsql = "";

    if (is_array($filter) && isset($filter['calldate']) && strlen($filter['calldate'])) {
      $dates = json_decode($filter['calldate'], 1);
      if ($dates['start']) {
          $d = strtotime($dates['start']);            
          $wsql .= "AND calldate >= '".date("Y-m-d 00:00:00", $d)."' ";
      }
      if ($dates['end']) {
          $d = strtotime($dates['end']);            
          $wsql .= "AND calldate <= '".date("Y-m-d 23:59:59", $d)."' ";
      }        
    }

    if(is_array($filter) && isset($filter['src']) && strlen($filter['src'])) {
      $wsql = $wsql."	AND src LIKE '%".addslashes($filter['src'])."%' ";
    }

    if(is_array($filter) && isset($filter['dst']) && strlen($filter['dst'])) {
      $wsql = $wsql."	AND dst LIKE '%".addslashes($filter['dst'])."%' ";
    }

    if ($stat) {
      $sql = "SELECT COUNT(*) AS total,  
              SUM(billsec) AS sum_billsec, SUM(IF(disposition = 'ANSWERED',duration,0)) AS sum_duration, 
              count(IF(disposition = 'ANSWERED',1,NULL)) AS count_answered,
              sum(IF(disposition = 'ANSWERED', (duration-billsec),0)) AS sum_answered FROM cdr";
      if (strlen($wsql)) {
        $sql .= " WHERE 1=1 $wsql";
      }
      $result_cdr = $this->db->query($sql);
      $cdr = $result_cdr->fetch(\PDO::FETCH_ASSOC);
      return $cdr;
    }

    $utils = new Utils();

    $demand_cdr = "
          SELECT calldate,src,dst,duration AS ratesec,disposition,userfield,department,currency,cost,B.fullname AS fullname, cdr.channel, cdr.dstchannel 
          FROM cdr		
          LEFT JOIN acl_user AS B ON 
          (B.id = (SELECT MAX(A.acl_user_id) FROM cfg_user_setting AS A WHERE ((A.val=src OR A.val=dst) AND A.handle = 'cti.ext')))
          WHERE 1=1 $wsql";

    $ext = $this->auth->allow_extens();
    $extens = $utils->sql_allow_extens($ext);
    $demand_cdr.= $extens;

    $demand_cdr = $demand_cdr.
      " ORDER BY calldate DESC ";

    if ($count) {
      $demand_cdr .= " LIMIT $pos, $count";
    }

    $result_cdr = $this->db->query($demand_cdr);
    $cdr_arr = [];
    $i = 0;
    while($cdr = $result_cdr->fetch(\PDO::FETCH_ASSOC)) {
      $cdr_arr[$i] = $cdr;
      $cdr_arr[$i]['ratesec'] = $utils->time_format($cdr_arr[$i]['ratesec']);
      $cdr_arr[$i]['fullname'] = empty($cdr_arr[$i]['fullname']) ? "" : $cdr_arr[$i]['fullname'];
      if($cdr_arr[$i]['cost']>0) $cdr_arr[$i]['cost'] = $cdr_arr[$i]['cost']." ".$cdr_arr[$i]['currency'];
      else $cdr_arr[$i]['cost'] = "0.00";
      $cdr_arr[$i]['status'] = $cdr_arr[$i]['disposition'];
      if($cdr_arr[$i]['disposition']!="ANSWERED") $cdr_arr[$i]['ratesec'] = $utils->time_format(0);
      $cdr_arr[$i]['calldate2'] = date('d.m.Y H:i:s',strtotime($cdr_arr[$i]['calldate']));
      $i++;
    };
    return $cdr_arr;
  }


  public function getCdr_report_total($filter, $pos, $count = 20, $onlycount = 0)
  {

    if ($onlycount) {
      $result_cdr = $this->db->query("SELECT COUNT(*) FROM cdr");
      $cdr = $result_cdr->fetch(\PDO::FETCH_NUM);
      return intval($cdr[0]);
    }

    $utils = new Utils();
    $t1 = date("2018-10-25 15:27:50");
    $t2 = date("2018-10-27 15:27:50");

    $demand_cdr = "	SELECT SUM(billsec),SUM(IF(disposition = 'ANSWERED',duration,0)) AS sum_duration,count(*),count(IF(disposition = 'ANSWERED',1,NULL)),
                    sum(IF(disposition = 'ANSWERED', (duration-billsec),0))
                    FROM cdr ";

// Time settings

//    if(isset($filter['t1']) && isset($filter['t2'])&&($filter['t1']!="") && ($filter['t2']!="")) $demand_cdr = $demand_cdr.
//        "	WHERE calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
//    else $demand_cdr = $demand_cdr.
//        "	WHERE UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 86400 ";

    if(isset($t1) && isset($t2)&&($t1!="") && ($t2!="")) $demand_cdr = $demand_cdr.
          "	WHERE calldate>'".$t1."' AND calldate<'".$t2."' ";
    else $demand_cdr = $demand_cdr.
          "	WHERE UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 86400 ";


//    if (isset($filter['src'])) $demand_cdr = $demand_cdr .
//      "	AND src LIKE '%" . $filter['src'] . "%' ";
//    if (isset($filter['dst'])) $demand_cdr = $demand_cdr .
//      "	AND dst LIKE '%" . $filter['dst'] . "%' ";

    $ext = $this->auth->allow_extens();
    $extens = $utils->sql_allow_extens($ext);
    $demand_cdr .= $extens;

    if ($count) {
      $demand_cdr .= " LIMIT $pos, $count";
    }

    $result_cdr = $this->db->query($demand_cdr);
    $cdr = $result_cdr->fetch(\PDO::FETCH_BOTH);
    $cdr_arr = [];

    $cdr_arr['1'] = $utils->time_format($cdr['sum_duration']);

    $cdr_arr['2'] = $cdr['count(*)'];
    if ($cdr[3]) $tmc = $cdr[1] / $cdr[3]; else $tmc = 0;
    $cdr_arr['3'] = sprintf("%02d:%02d", intval($tmc / 60), intval($tmc % 60));

    if ($cdr[2] != 0) $asr = $cdr[3] / $cdr[2] * 100; else $asr = 0;
    $cdr_arr['4'] = round($asr, 1);
    if ($cdr[3] != 0) $pdd = $cdr[4] / $cdr[3]; else $pdd = 0;
    $cdr_arr['5'] = round($pdd, 1);

    return $cdr;
  }


}