<?php

namespace Erpico;

class Month_traffic {
  private $container;
  private $db;
  private $auth;  

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getMonth_traffic_period($filter, $pos, $count = 20, $onlycount = 0) {
    
    if ($onlycount) {
      $res = $this->db->query("SELECT COUNT(*) FROM cdr");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    $year = date("Y")-2;
    $demand_month_trafic = "SELECT distinct MONTH(calldate),YEAR(calldate)
      FROM cdr 
      WHERE YEAR(calldate)>".$year."  
      GROUP BY substring(calldate,1,7),calldate ";
    $result_month_trafic = $this->db->query($demand_month_trafic);
    $i = -1;
    $month_trafic = [];
    $richselect = [];
    $current_month = [];
    while($month_trafic_sql = $result_month_trafic->fetch(\PDO::FETCH_BOTH)) {
      switch ($month_trafic_sql['MONTH(calldate)']) {
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
      $current_month['id'] = $month_trafic_sql['MONTH(calldate)']."-".$month_trafic_sql['YEAR(calldate)'];
      $current_month['value'] = $month."-".$month_trafic_sql['YEAR(calldate)'];
      $richselect[] = $current_month;
    };
    return $richselect;
  }


  public function getMonth_traffic_data($filter, $pos, $count = 20, $onlycount = 0) {
    $utils = new Utils();

    if ($onlycount) {
      $res = $this->db->query("SELECT COUNT(*) FROM cdr");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    $year = date("Y")-1;
    $demand_dailyreport = "
      SELECT substring(calldate,1,10), SUM(IF(disposition = 'ANSWERED',duration,0)) AS sum_duration,
      count(*) , count(IF(disposition = 'ANSWERED',1,NULL)),
      sum(IF(disposition = 'ANSWERED', (duration-billsec),0)), MONTH(calldate), YEAR(calldate) 
    ";

    $demand_dailyreport = $demand_dailyreport."	FROM cdr ";

    //  Time settings

    if(isset($filter['t1']) && $filter['t1']!="") {
      if(isset($filter['t2']) && $filter['t2']!="") {
        $period = $filter['t2'];
      } else {
        $period = 1;
      }
      $b = explode("-", $filter['t1']);
      $month = $b[0];
      if($month<$period) {
        $year = $b[1];
        $month1 = 12+($month-$period);
        $month1 = $month1>9 ? $month1 : "0".$month1;
        $month2 = $month+1;
        $month2 = $month2>9 ? $month2 : "0".$month2;
        $date1 = ($year-1).$month1;
        $date2 = $year.$month2;
      } else {
          $year = $b[1];
          $month1 = $month-$period;
          $month1 = $month1>9 ? $month1 : "0".$month1;
          $month2 = $month+1;
          $month2 = $month2>9 ? $month2 : "0".$month2;
          $date1 = $year.$month1;
          $date2 = $year.$month2;
      };
      $demand_dailyreport = $demand_dailyreport."	
      WHERE EXTRACT(YEAR_MONTH FROM calldate)>".$date1."
      AND EXTRACT(YEAR_MONTH FROM calldate)<".$date2."  ";
    }
    else $demand_dailyreport = $demand_dailyreport.
        "	WHERE YEAR(calldate)>".$year." ";

    $ext = $this->auth->allow_extens();
    $extens = $utils->sql_allow_extens($ext);
    $demand_dailyreport.= $extens;

    $demand_dailyreport = $demand_dailyreport."GROUP BY substring(calldate,1,7),calldate ORDER BY calldate DESC";

    $result_dailyreport = $this->db->query($demand_dailyreport);
    $dailyreport_arr = [];
    $i = -1;
    $sum_calls = 0;
    $chart_arr = [];
    $res = [];
    $month = "";
    while($dailyreport_sql = $result_dailyreport->fetch(\PDO::FETCH_BOTH)) {
      $i++;
      switch ($dailyreport_sql['MONTH(calldate)']) {
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
      $dailyreport_sql['10'] = $dailyreport_sql['YEAR(calldate)']."-".$dailyreport_sql['MONTH(calldate)'];
      $dailyreport_sql['month'] = $month." ".$dailyreport_arr[$i]['YEAR(calldate)'];
      $dailyreport_sql['1'] = $dailyreport_sql['sum_duration'];
      $dailyreport_sql['time'] = $utils->time_format($dailyreport_sql['sum_duration']);
      if (is_numeric($dailyreport_sql[3])) $tmc = $dailyreport_sql[1]/$dailyreport_sql[3]; else $tmc=0;
      $dailyreport_sql['3'] = $utils->time_format($tmc);
      if (is_numeric($dailyreport_sql[2])) $asr = $dailyreport_sql[3]/$dailyreport_sql[2]*100;
      else $asr=0;
      $dailyreport_sql['4'] = round($asr,1);
      if (is_numeric($dailyreport_sql[3])) $pdd = $dailyreport_sql[4]/$dailyreport_sql[3];
      else $pdd=0;
      $dailyreport_sql['5'] = round($pdd,1);
      $sum_calls += $dailyreport_sql[2];
      if($i==0) $color = "#FF6666"; elseif($i==1) $color = "#99CC99"; elseif($i==2) $color = "#CC9966"; elseif($i==3) $color = "#FF9999"; elseif($i==4) $color = "#3399FF"; else $color = "#339933";
      $chart_arr[$i] = [
          'sales' => $dailyreport_sql['sum_duration'],
          'month' => $month." ".$dailyreport_sql['YEAR(calldate)']." - ".sprintf("%02d",intval($dailyreport_sql['sum_duration']/60))." мин",
          'color' => $color
      ];
      $dailyreport_sql['share'] = ($sum_calls!=0 && $dailyreport_sql['2']!= 0? $dailyreport_sql['2']." (".round($dailyreport_sql['2']*100/$sum_calls,2)."%)" : 0);
      $res[] = $dailyreport_sql;
    };

    return [$res, $chart_arr, $i];
  }

  public function getMonth_traffic($filter, $pos, $count = 20, $onlycount = 0) {
    $dailyreport_arr = $this->getMonth_traffic_data($filter, $pos, $count = 20, $onlycount = 0)[0];
    return $dailyreport_arr;
  }

  public function getMonth_traffic_chart($filter, $pos, $count = 20, $onlycount = 0) {
    $chart_arr = $this->getMonth_traffic_data($filter, $pos, $count = 20, $onlycount = 0)[1];
    $i = $this->getMonth_traffic_data($filter, $pos, $count = 20, $onlycount = 0)[2];
    $month_dataset = [];
    for($j=0; $j<=$i; $j++) $month_dataset[] = $chart_arr[$j];
    return $month_dataset;
  }

}

