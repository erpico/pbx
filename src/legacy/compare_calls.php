<?php

namespace Erpico;

class Compare_calls {
  private $container;
  private $db;
  private $auth;  

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getCompare_calls_chart($filter, $pos, $count = 20, $onlycount = 0) {
    $utils = new Utils();

    if ($onlycount) {
      $res = $this->db->query("SELECT COUNT(*) FROM cdr");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    $demand_dailyreport = "		
                    SELECT substring(calldate,9,5), count(*), DAY(calldate), MONTH(calldate), YEAR(calldate) ";
    $demand_dailyreport = $demand_dailyreport.
      "	FROM cdr ";

    if(isset($filter['t1']) && $filter['t1']!="") {
      if(isset($filter['t2']) && $filter['t2']!="") $period = $filter['t2'];
      else $period = 1;
      $b = explode("-", $filter['t1']);

      if($b[2]<$period) {
          $str1 = strtotime($filter['t1'])+86400;
          $date1 = date("Y-m-d",$str1);
          $str2 = strtotime($filter['t1'])-86400*($period-1);
          $date2 = date("Y-m-d",$str2);
      }
      else {
          $year1 = $b[0];
          $year2 = $b[0];
          $day1 = $b[2]+1;
          $day1 = $day1>9 ? $day1 : "0".$day1;
          $day2 = $b[2]-$period+1;
          $day2 = $day2>9 ? $day2 : "0".$day2;
          $month1 = $b[1];
          $month1 = $month1>9 ? $month1 : "0".$month1;
          $month2 = $b[1];
          $month2 = $month2>9 ? $month2 : "0".$month2;
          $date1 = $year1."-".$month1."-".$day1;
          $date2 = $year2."-".$month2."-".$day2;
      };
      $demand_dailyreport = $demand_dailyreport.
          "	WHERE UNIX_TIMESTAMP(calldate)>UNIX_TIMESTAMP('".$date2."') AND UNIX_TIMESTAMP(calldate)<UNIX_TIMESTAMP('".$date1."')  ";
    }
    else $demand_dailyreport = $demand_dailyreport.
      "	WHERE UNIX_TIMESTAMP(calldate)>UNIX_TIMESTAMP('".date("Y-m-d")."') AND UNIX_TIMESTAMP(calldate)<UNIX_TIMESTAMP('".date("Y-m-d", time() + 86400)."')  ";

    if(isset($filter['src'])) $demand_dailyreport = $demand_dailyreport.
      "	AND src LIKE '%".$filter['src']."%' ";
    if(isset($filter['dst'])) $demand_dailyreport = $demand_dailyreport.
      "	AND dst LIKE '%".$filter['dst']."%' ";

    $ext = $this->auth->allow_extens();
    $extens = $utils->sql_allow_extens($ext);
    $demand_dailyreport.= $extens;

    $demand_dailyreport = $demand_dailyreport."GROUP BY substring(calldate,9,5) ORDER BY calldate ";

    $result_dailyreport = $this->db->query($demand_dailyreport);
    $dailyreport_arr = [];
    $i = -1;
    $chart = [];
    while($dailyreport_sql = $result_dailyreport->fetch(\PDO::FETCH_BOTH)) {
      $i++;
      $dailyreport_arr[$i] = $dailyreport_sql;
    };

    for($j=0; $j<24; $j++) {
      $chart[$j]['sales1'] = 0;
      $chart[$j]['sales2'] = 0;
      $chart[$j]['sales3'] = 0;
      $chart[$j]['sales4'] = 0;
      $chart[$j]['sales5'] = 0;
      $chart[$j]['hour'] = $j;
    };

    $current_day = [];
    $current_day[1] = "day1";
    $current_day[2] = "day2";
    $current_day[3] = "day3";
    $current_day[4] = "day4";
    $current_day[5] = "day5";
    $current_id = 0;
    $day = 0;
    for($k=0; $k<=$i; $k++) {
      if($day!=$dailyreport_arr[$k]['DAY(calldate)']) {
          $day = $dailyreport_arr[$k]['DAY(calldate)'];
          $current_id++;
      };
      for($j=0; $j<24; $j++) {
          if($dailyreport_arr[$k]['DAY(calldate)']<10) $day = "0".$dailyreport_arr[$k]['DAY(calldate)']; else $day = $dailyreport_arr[$k]['DAY(calldate)'];
          if($dailyreport_arr[$k]['MONTH(calldate)']<10) $month = "0".$dailyreport_arr[$k]['MONTH(calldate)']; else $month = $dailyreport_arr[$k]['MONTH(calldate)'];
          $chart[$j][$current_day[$current_id]] = $day."-".$month."-".$dailyreport_arr[$k]['YEAR(calldate)'];
      }
    };

    $line = [];
    $line[1] = "sales1";
    $line[2] = "sales2";
    $line[3] = "sales3";
    $line[4] = "sales4";
    $line[5] = "sales5";
    $line_id = 0;
    $current_day = 0;
    for($k=0; $k<=$i; $k++) {
      $b = explode(" ",$dailyreport_arr[$k]['substring(calldate,9,5)']);

      if($b[1]=="09") $b[1] = 9;
      elseif($b[1]=="08") $b[1] = 8;
      elseif($b[1]=="07") $b[1] = 7;
      elseif($b[1]=="06") $b[1] = 6;
      elseif($b[1]=="05") $b[1] = 5;
      elseif($b[1]=="04") $b[1] = 4;
      elseif($b[1]=="03") $b[1] = 3;
      elseif($b[1]=="02") $b[1] = 2;
      elseif($b[1]=="01") $b[1] = 1;
      elseif($b[1]=="00") $b[1] = 0;

      if($current_day!=$b[0]) {
          $current_day = $b[0];
          $line_id++;
      };
      $chart[$b[1]][$line[$line_id]] = $dailyreport_arr[$k]['count(*)'];
    };

    return $chart;
  }


  public function getCompare_calls($filter, $pos, $count = 20, $onlycount = 0) {

    $utils = new Utils();

    $demand_dailyreport = "		
                    SELECT substring(calldate,1,10), SUM(IF(disposition = 'ANSWERED',duration,0)) AS sum_duration, 
                    count(*) , count(IF(disposition = 'ANSWERED',1,NULL)), 
                    sum(IF(disposition = 'ANSWERED', (duration-billsec),0)), DAY(calldate), MONTH(calldate), YEAR(calldate) ";
    $demand_dailyreport = $demand_dailyreport.
      "	FROM cdr ";

    if(isset($filter['t1']) && $filter['t1']!="") {
      if(isset($filter['t2']) && $filter['t2']!="") $period = $filter['t2'];
      else $period = 1;
      $b = explode("-", $t1);

      if($b[2]<$period) {
          $str1 = strtotime($t1)+86400;
          $date1 = date("Y-m-d",$str1);
          $str2 = strtotime($filter['t1'])-86400*($period-1);
          $date2 = date("Y-m-d",$str2);
      }
      else {
          $year1 = $b[0];
          $year2 = $b[0];
          $day1 = $b[2]+1;
          $day1 = $day1>9 ? $day1 : "0".$day1;
          $day2 = $b[2]-$period+1;
          $day2 = $day2>9 ? $day2 : "0".$day2;
          $month1 = $b[1];
          $month1 = $month1>9 ? $month1 : "0".$month1;
          $month2 = $b[1];
          $month2 = $month2>9 ? $month2 : "0".$month2;
          $date1 = $year1."-".$month1."-".$day1;
          $date2 = $year2."-".$month2."-".$day2;
      };
      $wsql = "	WHERE UNIX_TIMESTAMP(calldate)>UNIX_TIMESTAMP('".$date2."') AND UNIX_TIMESTAMP(calldate)<UNIX_TIMESTAMP('".$date1."')  ";
    }
    else $wsql = "	WHERE UNIX_TIMESTAMP(calldate)>UNIX_TIMESTAMP('".date("Y-m-d")."') AND UNIX_TIMESTAMP(calldate)<UNIX_TIMESTAMP('".date("Y-m-d", time() + 86400)."')  ";

    $ext = $this->auth->allow_extens();
    $extens = $utils->sql_allow_extens($ext);
    $demand_dailyreport.= $extens;

    if ($onlycount) {
      $res = $this->db->query("SELECT COUNT(*) FROM ( SELECT calldate FROM cdr ".$wsql." GROUP BY substring(calldate,1,10) ) a");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    $demand_dailyreport = $demand_dailyreport.$wsql." GROUP BY substring(calldate,1,10) ORDER BY calldate";
    $result_dailyreport = $this->db->query($demand_dailyreport);
    $dailyreport_arr = [];
    $i = -1;
    $chart_arr = [];
    $sum_calls = 0;
    $month = "";
    while($dailyreport_sql = $result_dailyreport->fetch(\PDO::FETCH_BOTH)) {
      $i++;
      $dailyreport_arr[$i] = $dailyreport_sql;

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
      $dailyreport_arr[$i]['calldate'] = $dailyreport_arr[$i]['DAY(calldate)']."-".$month."-".$dailyreport_arr[$i]['YEAR(calldate)'];
      $dailyreport_arr[$i]['10'] = $dailyreport_arr[$i]['YEAR(calldate)']."-".$dailyreport_arr[$i]['MONTH(calldate)']."-".$dailyreport_arr[$i]['DAY(calldate)'];
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