<?php

namespace Erpico;

class Ext_outgoing {
  private $container;
  private $db;
  private $auth;  

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getExt_outgoing($filter, $pos, $count = 20, $onlycount = 0) {
    
    $utils = new Utils($this->container);
    $users = $this->auth->getUsersList();
    $analysis_outgoing_calls = new \Erpico\Analysis_outgoing_calls($this->container);
    
    $sql = "SELECT 
            count(*) AS total,
            src,
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
            MAX(duration-billsec) AS max_wait ,
            acl_user.fullname";
    if ($onlycount) {
      $sql = "SELECT COUNT(distinct src) ";
    }
    $sql .= " FROM cdr 
    LEFT JOIN cfg_user_setting ON (cfg_user_setting.val = SUBSTRING(channel,POSITION('/' IN channel)+1,LENGTH(channel)-POSITION('-' IN REVERSE(channel))-POSITION('/' IN channel)) AND cfg_user_setting.handle = 'cti.ext')
            LEFT JOIN acl_user ON (acl_user.id = cfg_user_setting.acl_user_id)            
            WHERE ";
    $intPhones = $utils->getIntPhones();
    if (count($intPhones)) {
      $sql .= " src IN ('".trim(implode("','", $intPhones), "'")."') ";          
    } else {
      $sql .= " LENGTH(src) > 1 AND LENGTH(src) < 4 ";          
    }

    $wsql = "";
    if (is_array($filter)) {
      if(isset($filter['t1']) && isset($filter['t2'])) {
        $sql = $sql." AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
      } else {
        $sql = $sql." AND UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 186400 ";
      }
      if (isset($filter["number"]) && strlen($filter["number"])) {
        if (strlen($wsql)) $wsql .=" AND ";
        $wsql .= " src='".intval($filter["number"])."'";
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

    if (is_array($filter) && isset($filter["total"]) && strlen($filter["total"])) {
      $tsql .= "HAVING count(*) ".trim(addslashes($filter["total"]))." ";
    } else {
      $tsql = "";
    }

    if (!$onlycount) {
      $sql.= "GROUP BY src, fullname ".$tsql." ORDER BY src ";
    }
    

    if ($count) {
      $sql .= " LIMIT $pos, $count";
    }

    $cdr_report = [];
    $result = $this->db->query($sql);
    $num = 0;
    if ($onlycount) {
      $row = $result->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }
    while($list = $result->fetch(\PDO::FETCH_ASSOC)) {
      $cdr_report[$num]['number'] = $list['src'];
      $cdr_report[$num]['user_name'] = (isset($list['fullname']) ? $list['fullname'] : '');
      $cdr_report[$num]['total'] = $list['total'];

      $cdr_report[$num]['served'] = $list['answered'];
      $cdr_report[$num]['unserved'] = $list['noanswer'] + $list['busy'] + $list['failed'];

      $cdr_report[$num]['avg_answer'] = $utils->time_format($list['avg_answer']);
      $cdr_report[$num]['avg_duration'] = $utils->time_format(round($list['totalduration']/$list['answered']));
      $cdr_report[$num]['avg_wait'] = $utils->time_format($list['avg_wait']);
      $cdr_report[$num]['max_wait'] = $utils->time_format($list['max_wait']);
      $cdr_report[$num]['min_wait'] = $utils->time_format($list['min_wait']);

      $traffic_for_period = $analysis_outgoing_calls->getTraffic_for_period($filter, $pos, $count, 0, $list['src']);
      $cdr_report[$num]['internal'] = "{$traffic_for_period[0]['count']}, {$traffic_for_period[0]['duration']}";
      $cdr_report[$num]['urban'] = "{$traffic_for_period[1]['count']}, {$traffic_for_period[1]['duration']}";
      $cdr_report[$num]['cellular'] = "{$traffic_for_period[2]['count']}, {$traffic_for_period[2]['duration']}";
      $cdr_report[$num]['intercity'] = "{$traffic_for_period[3]['count']}, {$traffic_for_period[3]['duration']}";
      $cdr_report[$num]['international'] = "{$traffic_for_period[4]['count']}, {$traffic_for_period[4]['duration']}";

      $num++;
    };

    return $cdr_report;
  }
}