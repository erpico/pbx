<?php

namespace Erpico;

class Ext_incoming_internal {
  private $container;
  private $db;
  private $auth;  

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getExt_incoming_internal($filter, $pos, $count = 20, $onlycount = 0) {
    
    $users = $this->auth->getUsersList();
    $utils = new Utils();

    $sql = "SELECT 
                count(*) AS total,
                dst,
                SUM(billsec) AS totalduration,
                SUM(duration-billsec) AS totalanswertime,					
                count(IF(disposition = 'NO ANSWER',1,NULL)) AS noanswer,
                count(IF(disposition = 'ANSWERED',1,NULL)) AS answered,
                count(IF(disposition = 'BUSY',1,NULL)) AS busy,
                count(IF(disposition = 'FAILED',1,NULL)) AS failed,					          
                SUM(IF(duration-billsec < 20 AND billsec > 0,1,0)) AS sl_cnt,				
                AVG(IF(billsec > 0,duration-billsec,0))	AS avg_answer,
                AVG(duration-billsec)	AS avg_wait,
                AVG(billsec)	AS avg_duration,
                MIN(duration-billsec) AS min_wait,
                MAX(duration-billsec) AS max_wait,
                acl_user.fullname 
                FROM cdr 
                LEFT JOIN acl_user ON (acl_user.name = cdr.dst)
                WHERE LENGTH(dst) > 1 AND LENGTH(dst) < 5  ";

    if(isset($filter['t1']) && isset($filter['t2'])) {
      $sql = $sql." AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
    } else {
      $sql = $sql." AND UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 186400 ";
    }
 
    $wsql = "";
    if (is_array($filter)) {
      if (isset($filter["number"]) && strlen($filter["number"])) {
        if (strlen($wsql)) $wsql .=" AND ";
        $wsql .= " dst='".intval($filter["number"])."'";
      }
      if (isset($filter["user_name"]) && strlen($filter["user_name"])) {
        if (strlen($wsql)) $wsql .=" AND ";
        $wsql .= "  acl_user.fullname LIKE '%".trim(addslashes($filter["user_name"]))."%'";
      }
    }
    if (strlen($wsql)) {
      $sql .= " AND ".$wsql;
    }
    $extens = $this->auth->allow_extens();
    $sql.= $utils->sql_allow_extens($extens);
    $sql.= " GROUP BY dst, fullname ORDER BY dst ";
    
    if ($onlycount) {
      $res = $this->db->query("SELECT COUNT(*) FROM (".$sql.") a");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    } else {
      if ($count) {
        $sql .= " LIMIT $pos, $count";
      }
    }
    $result = $this->db->query($sql);
    $cdr_report = [];

    $num = 0;

    while($list = $result->fetch(\PDO::FETCH_ASSOC)) {
      $cdr_report[$num]['number'] = $list['dst'];      
      $cdr_report[$num]['user_name'] = (isset($list['fullname']) ? $list['fullname'] : '');
      $cdr_report[$num]['user_id'] = $list['dst'];
      $cdr_report[$num]['total'] = $list['total'];

      $cdr_report[$num]['served'] = $list['answered'];
      $cdr_report[$num]['unserved'] = $list['noanswer'] + $list['busy'] + $list['failed'];

      $cdr_report[$num]['avg_answer'] = $utils->time_format($list['avg_answer']);
      $cdr_report[$num]['avg_duration'] = $utils->time_format(round($list['totalduration']/$list['answered']));
      $cdr_report[$num]['avg_wait'] = $utils->time_format($list['avg_wait']);
      $cdr_report[$num]['max_wait'] = $utils->time_format($list['max_wait']);
      $cdr_report[$num]['min_wait'] = $utils->time_format($list['min_wait']);

      $cdr_report[$num]['lcr'] = round($cdr_report[$num]['unserved']/$cdr_report[$num]['total']*100)."%";
      $cdr_report[$num]['sl'] = $list['sl_cnt']." (".round($list['sl_cnt']/$cdr_report[$num]['total']*100)."%)";

      $num++;
    };

    return $cdr_report;
  }
}