<?php

namespace Erpico;

class Daily_report {
  private $container;
  private $db;
  private $auth;  

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getDaily_report($filter, $pos, $count = 20, $onlycount = 0) {
    
    // Here need to add permission checkers and filters

    $utils = new Utils();
    
    $wsql = " WHERE 1=1";
    if (is_array($filter) && isset($filter['t1']) && strlen($filter['t1']) && isset($filter['t2']) && strlen($filter['t2'])) {
      $wsql .=" AND calldate>'".trim(addslashes($filter['t1']))."' AND calldate<'".trim(addslashes($filter['t2']))."' ";
    } else {
      $wsql .= " AND calldate > '".date('Y-m-d H:i:s',strtotime('-1 month', mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y"))))."'";
    }
    if (is_array($filter) && isset($filter['src']) && strlen($filter['src'])) {
      $wsql .= " AND src LIKE '%".trim(addslashes($filter['src']))."%' ";
    }
    if (is_array($filter) && isset($filter['dst']) && strlen($filter['dst'])) {
      $wsql .= " AND dst LIKE '%".trim(addslashes($filter['dst']))."%' ";
    }

    if ($onlycount) {
      $res = $this->db->query("
      SELECT COUNT(*) FROM (SELECT 
        substring(calldate,1,10) FROM cdr 
         ".$wsql." GROUP BY substring(calldate,1,10)
        ) a");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    $demand_dailyreport = "		
                    SELECT substring(calldate,1,10), SUM(IF(disposition = 'ANSWERED',duration,0)) AS sum_duration,  
                    count(*) , count(IF(disposition = 'ANSWERED',1,NULL)), 
                    sum(IF(disposition = 'ANSWERED', (duration-billsec),0)) ";

    $demand_dailyreport = $demand_dailyreport.
      "	FROM cdr ";
    $demand_dailyreport = $demand_dailyreport." ".$wsql;

    $ext = $this->auth->allow_extens();
    $extens = $utils->sql_allow_extens($ext);
    $demand_dailyreport.= $extens;

    $demand_dailyreport = $demand_dailyreport." GROUP BY substring(calldate,1,10) ORDER BY substring(calldate,1,10) DESC";
    $result_dailyreport = $this->db->query($demand_dailyreport);
    $dailyreport_arr = [];
    $i = -1;
    $sum_calls = 0;
    while($dailyreport_sql = $result_dailyreport->fetch(\PDO::FETCH_BOTH)) {
      $i++;
      $dailyreport_arr[$i] = $dailyreport_sql;
      $dailyreport_arr[$i]['1'] = $utils->time_format($dailyreport_sql['sum_duration']);

      if ($dailyreport_sql[3]) $tmc = $dailyreport_sql[1]/$dailyreport_sql[3]; else $tmc=0;
      $dailyreport_arr[$i]['3'] = $utils->time_format($tmc);

      if ($dailyreport_sql[2]) $asr = $dailyreport_sql[3]/$dailyreport_sql[2]*100;
      else $asr=0;
      $dailyreport_arr[$i]['4'] = round($asr,1);
      if ($dailyreport_sql[3]) $pdd = $dailyreport_sql[4]/$dailyreport_sql[3];
      else $pdd=0;
      $dailyreport_arr[$i]['5'] = round($pdd,1);
      $dailyreport_arr[$i]['calldate2'] = date('d.m.Y',strtotime($dailyreport_arr[$i]['0']));
      $sum_calls += $dailyreport_sql[2];
    };

    for($j=0; $j<$i+1; $j++){
      $dailyreport_arr[$j]['share'] = ($sum_calls!=0 ? $dailyreport_arr[$j]['2']." (".round($dailyreport_arr[$j]['2']*100/$sum_calls,2)."%)" : 0);
    };

    return $dailyreport_arr;
  }
}