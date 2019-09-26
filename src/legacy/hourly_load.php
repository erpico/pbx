<?php

namespace Erpico;

class Hourly_load {
  private $container;
  private $db;
  private $auth;  

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getHourly_load_chart1($filter, $pos, $count = 20, $onlycount = 0) {
    $utils = new Utils();

    if ($onlycount) {
      $res = $this->db->query("SELECT COUNT(*) FROM cdr");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    $demand_dailyreport = " SELECT substring(calldate,12,2), count(*), HOUR(calldate) ";
    $demand_dailyreport = $demand_dailyreport." FROM cdr ";

    if(isset($filter['t1']) && $filter['t1']!="") {
      $b = explode("-",$filter['t1']);
      $demand_dailyreport = $demand_dailyreport.
          "	WHERE DAY(calldate)=".$b[2]." AND MONTH(calldate)=".$b[1]." AND YEAR(calldate)=".$b[0]." ";
    }
    else $demand_dailyreport = $demand_dailyreport.
      "	WHERE DAY(calldate)=".date("d")." AND MONTH(calldate)=".date("m")." AND YEAR(calldate)=".date("Y")." ";
      if(isset($filter['src'])) $demand_dailyreport = $demand_dailyreport.
          "	AND src LIKE '%".$filter['src']."%' ";
      if(isset($filter['dst'])) $demand_dailyreport = $demand_dailyreport.
          "	AND dst LIKE '%".$filter['dst']."%' ";

    $ext = $this->auth->allow_extens();
    $extens = $utils->sql_allow_extens($ext);
    $demand_dailyreport.= $extens;

    if(isset($filter['t2']) && $filter['t2']!="") $demand_dailyreport = $demand_dailyreport."
                        GROUP BY substring(calldate,12,2) ORDER BY HOUR(calldate) ";
    else $demand_dailyreport = $demand_dailyreport."
                        GROUP BY substring(calldate,12,2) ORDER BY HOUR(calldate) ";

    $result_dailyreport = $this->db->query($demand_dailyreport);
    $dailyreport_arr = [];
    $i = -1;
    $chart_arr = [];
    $month_dataset = [];
    while($dailyreport_sql = $result_dailyreport->fetch(\PDO::FETCH_BOTH)) {
      $i++;
      $dailyreport_arr[$i] = $dailyreport_sql;
    };
    $color = "";
    for($j=0; $j<=23; $j++) {
      switch ($j) {
          case 0: $color = "#996600"; break;
          case 1: $color = "#660000"; break;
          case 2: $color = "#CC0033"; break;
          case 3: $color = "#663333"; break;
          case 4: $color = "#990066"; break;
          case 5: $color = "#993399"; break;
          case 6: $color = "#330066"; break;
          case 7: $color = "#006699"; break;
          case 8: $color = "#336666"; break;
          case 9: $color = "#00CCCC"; break;
          case 10: $color = "#006633"; break;
          case 11: $color = "#009933"; break;
          case 12: $color = "#003300"; break;
          case 13: $color = "#333300"; break;
          case 14: $color = "#FFCC66"; break;
          case 15: $color = "#FF6633"; break;
          case 16: $color = "#CC9999"; break;
          case 17: $color = "#9966CC"; break;
          case 18: $color = "#3333FF"; break;
          case 19: $color = "#99CCFF"; break;
          case 20: $color = "#66CC99"; break;
          case 21: $color = "#CCCC66"; break;
          case 22: $color = "#99CC66"; break;
          case 23: $color = "#339999"; break;
      };

      $this1 = 25;
      for($k=0; $k<=$i; $k++) if($dailyreport_arr[$k]['HOUR(calldate)']==$j) $this1 = $k;

      if($this1!=25) $chart_arr[$j] = [
          'sales' => $dailyreport_arr[$this1]['count(*)'],
          'month' => $j,
          'color' => $color
      ];
      else $chart_arr[$j] = [
          'sales' => "0",
          'month' => $j,
          'color' => $color
      ];

      $month_dataset[$j] = $chart_arr[$j];
    };

    return $month_dataset;
  }


  public function getHourly_load_chart2($filter, $pos, $count = 20, $onlycount = 0) {
    $utils = new Utils();
    $t1 = date("2018-10-25-15:27:50");

    if ($onlycount) {
        $res = $this->db->query("SELECT COUNT(*) FROM cdr");
        $row = $res->fetch(\PDO::FETCH_NUM);
        return intval($row[0]);
    }

    $demand_dailyreport = " SELECT substring(calldate,12,2), count(*), HOUR(calldate), count(IF(disposition = 'ANSWERED',1,NULL)) ";
    $demand_dailyreport = $demand_dailyreport."	FROM cdr ";

    if(isset($filter['t1']) && $filter['t1']!="") {
      $b = explode("-",$filter['t1']);
      $demand_dailyreport = $demand_dailyreport.
          "	WHERE DAY(calldate)=".$b[2]." AND MONTH(calldate)=".$b[1]." AND YEAR(calldate)=".$b[0]." ";
    }
    else $demand_dailyreport = $demand_dailyreport.
      "	WHERE DAY(calldate)=".date("d")." AND MONTH(calldate)=".date("m")." AND YEAR(calldate)=".date("Y")." ";

    if(isset($filter['src'])) $demand_dailyreport = $demand_dailyreport.
      "	AND src LIKE '%".$filter['src']."%' ";
    if(isset($filter['dst'])) $demand_dailyreport = $demand_dailyreport.
      "	AND dst LIKE '%".$filter['dst']."%' ";

    $ext = $this->auth->allow_extens();
    $extens = $utils->sql_allow_extens($ext);
    $demand_dailyreport.= $extens;

    if(isset($filter['t2']) && $filter['t2']!="") $demand_dailyreport = $demand_dailyreport."
                    GROUP BY substring(calldate,12,2) ORDER BY HOUR(calldate) ";
    else $demand_dailyreport = $demand_dailyreport."
                    GROUP BY substring(calldate,12,2) ORDER BY HOUR(calldate) ";

    $result_dailyreport = $this->db->query($demand_dailyreport);
    $dailyreport_arr = [];
    $i = -1;
    $chart_arr = [];
    $month_dataset = [];
    while($dailyreport_sql = $result_dailyreport->fetch(\PDO::FETCH_BOTH)) {
      $i++;
      $dailyreport_arr[$i] = $dailyreport_sql;
      if($dailyreport_sql['count(*)']!=0) $dailyreport_arr[$i]['asr'] = round(($dailyreport_sql["count(IF(disposition = 'ANSWERED',1,NULL))"]/$dailyreport_sql['count(*)'])*100);
      else $dailyreport_arr[$i]['asr'] = 0;
    };
    $color = "";
    for($j=0; $j<=23; $j++) {

      $this1 = 25;
      for($k=0; $k<=$i; $k++) if($dailyreport_arr[$k]['HOUR(calldate)']==$j) $this1 = $k;

      if($this1!=25) $chart_arr[$j] = [
          'sales' => $dailyreport_arr[$this1]['asr'],
          'month' => $j,
          'color' => $color
      ];
      else $chart_arr[$j] = [
          'sales' => "0",
          'month' => $j,
          'color' => $color
      ];

      $month_dataset[$j] = $chart_arr[$j];
    };

    return $month_dataset;
  }


  public function getHourly_load($filter, $pos, $count = 20, $onlycount = 0) {

    $utils = new Utils();

    if ($onlycount) {
      $res = $this->db->query("SELECT COUNT(*) FROM cdr");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    $demand_dailyreport = "		
                    SELECT substring(calldate,1,10), SUM(IF(disposition = 'ANSWERED',duration,0)) AS sum_duration, 
                    count(*) , count(IF(disposition = 'ANSWERED',1,NULL)), 
                    sum(IF(disposition = 'ANSWERED', (duration-billsec),0)), DAY(calldate), MONTH(calldate), YEAR(calldate) ";

    $demand_dailyreport = $demand_dailyreport.
      "	FROM cdr ";

    if(isset($filter['t1']) && $filter['t1']!="") {
      $b = explode("-",$filter['t1']);
      $demand_dailyreport = $demand_dailyreport.
        "	WHERE DAY(calldate)=".$b[2]." AND MONTH(calldate)=".$b[1]." AND YEAR(calldate)=".$b[0]." ";
    }
    else $demand_dailyreport = $demand_dailyreport.
      "	WHERE DAY(calldate)=".date("d")." AND MONTH(calldate)=".date("m")." AND YEAR(calldate)=".date("Y")." ";



    if(isset($filter['src'])) $demand_dailyreport = $demand_dailyreport.
      "	AND src LIKE '%".$filter['src']."%' ";
    if(isset($filter['dst'])) $demand_dailyreport = $demand_dailyreport.
      "	AND dst LIKE '%".$filter['dst']."%' ";


    $ext = $this->auth->allow_extens();
    $extens = $utils->sql_allow_extens($ext);
    $demand_dailyreport.= $extens;

    $demand_dailyreport = $demand_dailyreport."
                    GROUP BY substring(calldate,1,7) ORDER BY calldate DESC ";

    $result_dailyreport = $this->db->query($demand_dailyreport);
    $dailyreport_arr = [];
    $i = -1;
    $sum_calls = 0;
    $chart_arr = [];
    while($dailyreport_sql = $result_dailyreport->fetch(\PDO::FETCH_BOTH)) {
      $i++;
      $dailyreport_arr[$i] = $dailyreport_sql;
      $month = "";
      switch ($dailyreport_arr[$i]['MONTH(calldate)']) {
          case 1: $month = "Январь"; break;
          case 2: $month = "Февраль"; break;
          case 3: $month = "Март"; break;
          case 4: $month = "Апрель"; break;
          case 5: $month = "Май"; break;
          case 6: $month = "Июнь"; break;
          case 7: $month = "Июль"; break;
          case 8: $month = "Август"; break;
          case 9: $month = "Сентябрь"; break;
          case 10: $month = "Октябрь"; break;
          case 11: $month = "Ноябрь"; break;
          case 12: $month = "Декабрь"; break;
      };
      $dailyreport_arr[$i]['10'] = $dailyreport_arr[$i]['DAY(calldate)']."-".$month."-".$dailyreport_arr[$i]['YEAR(calldate)'];
      $dailyreport_arr[$i]['1'] = $utils->time_format($dailyreport_sql['sum_duration']);
      if ($dailyreport_sql[3]) $tmc = $dailyreport_sql[1]/$dailyreport_sql[3]; else $tmc=0;
      $dailyreport_arr[$i]['3'] = $utils->time_format($tmc);
      if ($dailyreport_sql[2]) $asr = $dailyreport_sql[3]/$dailyreport_sql[2]*100;
      else $asr=0;
      $dailyreport_arr[$i]['4'] = round($asr,1);
      if ($dailyreport_sql[3]) $pdd = $dailyreport_sql[4]/$dailyreport_sql[3];
      else $pdd=0;
      $dailyreport_arr[$i]['5'] = round($pdd,1);
      $sum_calls += $dailyreport_sql[2];
      if($i==0) $color = "#FF6666"; elseif($i==1) $color = "#99CC99"; elseif($i==2) $color = "#CC9966"; elseif($i==3) $color = "#FF9999"; elseif($i==4) $color = "#3399FF"; else $color = "#339933";
      $chart_arr[$i] = [
          'sales' => $dailyreport_arr[$i]['1'],
          'month' => $month." ".$dailyreport_arr[$i]['YEAR(calldate)']." - ".sprintf("%02d",intval($dailyreport_sql['sum_duration']/60))." мин",
          'color' => $color
      ];
    };

    for($j=0; $j<$i+1; $j++){
      $dailyreport_arr[$j]['share'] = ($sum_calls!=0 ? $dailyreport_arr[$j]['2']." (".round($dailyreport_arr[$j]['2']*100/$sum_calls,2)."%)" : 0);
    };

    return $dailyreport_arr;
  }

}