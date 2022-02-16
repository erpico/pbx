<?php

namespace Erpico;

class Interval_reports {
  private $container;
  private $db;
  private $auth;

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getInterval_reports_day_total($filter, $pos, $count = 20, $onlycount = 0) {

    // Here need to add permission checkers and filters

    $utils = new Utils();

    if ($onlycount) {
      $res = $this->db->query("SELECT COUNT(*) FROM queue_cdr");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    $demand = " SELECT ";
      $demand.= isset($filter['groupByUuid']) ? "SUBSTRING(a.calldate,1,10) AS calldate_day, " : " SUBSTRING(calldate,1,10) AS calldate_day, ";

// Interval reports hour=3 / day=else(in use) / week=2 / month=4
/*
    if($filter['type']==2) $demand.= "
				DAYOFWEEK(calldate) AS calldate_weekday, ";
    else if($filter['type']==3) $demand.= "
            SUBSTRING(calldate,12,2) AS calldate_hour, ";
    else if($filter['type']==4) $demand.= "
            SUBSTRING(calldate,1,7) AS calldate_month, ";
->  else $demand.= "
            SUBSTRING(calldate,1,10) AS calldate_day, ";
*/

    $demand.= isset($filter['groupByUuid']) ? "count(*) AS count_calls, 
SUM(a.talktime) AS sum_talktime, 
SUM(a.holdtime) AS sum_holdtime, 
count(IF(a.reason = 'COMPLETEAGENT',1,NULL)) AS completeagent_calls, 
count(IF(a.reason = 'COMPLETECALLER',1,NULL)) AS completecaller_calls, 
count(IF(a.reason = 'TRANSFER',1,NULL)) AS transfer_calls, 
count(IF(a.reason = 'ABANDON',1,NULL)) AS abandon_calls, 
count(IF(a.reason = 'EXITEMPTY',1,NULL)) AS exitempty_calls, 
count(IF(a.reason = 'EXITWITHTIMEOUT',1,NULL)) AS exitwithtimeout_calls, 
count(IF(a.reason = 'EXITWITHKEY',1,NULL)) AS exitwithkey_calls, 
count(IF(a.reason = 'SYSCOMPAT',1,NULL)) AS syscompat_calls, 
MAX(a.holdtime) AS holdtime_max, 
MAX(a.talktime) AS talktime_max, 
MIN(a.holdtime) AS holdtime_min, 
MIN(IF(a.talktime,a.talktime,NULL)) AS talktime_min 
FROM queue_cdr a
LEFT OUTER JOIN queue_cdr b ON a.uniqid = b.uniqid AND a.id < b.id " : "	
            count(*) AS count_calls,
            SUM(talktime) AS sum_talktime,
            SUM(holdtime) AS sum_holdtime,
            count(IF(reason = 'COMPLETEAGENT',1,NULL)) AS completeagent_calls,
            count(IF(reason = 'COMPLETECALLER',1,NULL)) AS completecaller_calls,
            count(IF(reason = 'TRANSFER',1,NULL)) AS transfer_calls,
            count(IF(reason = 'ABANDON',1,NULL)) AS abandon_calls,
            count(IF(reason = 'EXITEMPTY',1,NULL)) AS exitempty_calls,
            count(IF(reason = 'EXITWITHTIMEOUT',1,NULL)) AS exitwithtimeout_calls,
            count(IF(reason = 'EXITWITHKEY',1,NULL)) AS exitwithkey_calls,
            count(IF(reason = 'SYSCOMPAT',1,NULL)) AS syscompat_calls,
            MAX(holdtime) AS holdtime_max,
            MAX(talktime) AS talktime_max,
            MIN(holdtime) AS holdtime_min,
            MIN(IF(talktime,talktime,NULL)) AS talktime_min
            FROM queue_cdr ";

//  Time settings

    if(isset($filter['t1']) && isset($filter['t2'])) {
        $demand = isset($filter['groupByUuid'])
            ? $demand . "WHERE a.calldate>'".$filter['t1']."' AND a.calldate<'".$filter['t2']."' "
            : $demand . "WHERE calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
    } else {
        $demand = isset($filter['groupByUuid'])
            ? $demand . " WHERE Now()-a.calldate < 86400 "
            : $demand . " WHERE Now()-calldate < 86400 ";
    }
    if (isset($filter['groupByUuid'])) $demand .= "AND b.uniqid IS NULL ";

    // $demand = $demand." WHERE UNIX_TIMESTAMP(calldate)>UNIX_TIMESTAMP('".$t1."') AND UNIX_TIMESTAMP(calldate)<UNIX_TIMESTAMP('".$t2."') ";

//  Filters

    if(isset($filter['filter'])) {
      if($filter['filter']==2) {
          $demand = isset($filter['groupByUuid'])
              ? $demand . " AND (a.reason = 'COMPLETEAGENT' OR a.reason = 'COMPLETECALLER' OR a.reason = 'TRANSFER') AND !a.outgoing "
              : $demand . " AND (reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') AND !outgoing ";
      }
      else if($filter['filter']==3) {
          $demand = isset($filter['groupByUuid'])
          ? $demand . " AND (a.reason = 'ABANDON' OR a.reason = 'EXITWITHTIMEOUT' OR a.reason = 'EXITEMPTY' OR a.reason = 'EXITWITHTKEY') AND !a.outgoing "
          : $demand . " AND (reason = 'ABANDON' OR reason = 'EXITWITHTIMEOUT' OR reason = 'EXITEMPTY' OR reason = 'EXITWITHTKEY') AND !outgoing ";
      }
      else if($filter['filter']==4) {
          $demand = isset($filter['groupByUuid'])
              ? $demand . "AND a.outgoing=1 "
              : $demand . "AND outgoing=1 ";
      }
      else {
          $demand = isset($filter['groupByUuid'])
            ? $demand . " AND !a.outgoing AND a.reason != 'RINGNOANSWER' "
            : $demand . " AND !outgoing AND reason != 'RINGNOANSWER' ";
      }
    } else {
        $demand = isset($filter['groupByUuid'])
            ? $demand . " AND !a.outgoing AND a.reason != 'RINGNOANSWER' "
            : $demand . " AND !outgoing AND reason != 'RINGNOANSWER' ";
    }

    if(isset($filter['src'])) {
        $demand = isset($filter['groupByUuid'])
        ? $demand ." AND a.src LIKE '%" . $filter['src'] . "%' "
        : $demand ." AND src LIKE '%" . $filter['src'] . "%' ";
    }

    if(isset($filter['queue'])) {
        $demand = isset($filter['groupByUuid'])
            ? $demand ." AND a.queue = " . $filter['queue'] . " "
            : $demand ." AND queue = " . $filter['queue'] . " ";
    }

    $que = $this->auth->allowed_queues();
    $queues = $utils->sql_allowed_queues($que);
    $demand.= $queues;


// Interval reports hour=3 / day=else(in use) / week=2 / month=4

    if($filter['type']==2) {
        $demand .= isset($filter['groupByUuid'])
        ? " GROUP BY DAYOFWEEK(a.calldate) "
        : " GROUP BY DAYOFWEEK(calldate) ";
    } else if($filter['type']==3) {
        $demand .= isset($filter['groupByUuid'])
        ? " GROUP BY SUBSTRING(a.calldate,12,2) "
        : " GROUP BY SUBSTRING(calldate,12,2) ";
    } else if($filter['type']==4) {
        $demand .= isset($filter['groupByUuid'])
            ? " GROUP BY SUBSTRING(a.calldate,1,7) "
            : " GROUP BY SUBSTRING(calldate,1,7) ";
    } else {
        $demand .= isset($filter['groupByUuid'])
        ? "GROUP BY SUBSTRING(a.calldate,1,10) "
        : "GROUP BY SUBSTRING(calldate,1,10) ";
    }
    $result = $this->db->query($demand);
    $cdr_report = [];
    $i = -1;
    while($myrow = $result->fetch(\PDO::FETCH_ASSOC)) {
      $i++;
      $cdr_report[$i] = $myrow;
    };

    for($j=0; $j<=$i; $j++) {
        $b = explode("-",$cdr_report[$j]['calldate_day']);
        switch ($b[1]) {
            case 1:
                $cdr_report[$j]['calldate'] = $b[0]."-".$b[1]."-".$b[2];
                $cdr_report[$j]['calldate2'] = $b[2]." "."January1"." ".$b[0];
                $cdr_report[$j]['calldate_short'] = $b[2]." "."January1"." ".$b[0];
                break;
            case 2:
                $cdr_report[$j]['calldate'] = $b[0]."-".$b[1]."-".$b[2];
                $cdr_report[$j]['calldate2'] = $b[2]." "."February1"." ".$b[0];
                $cdr_report[$j]['calldate_short'] = $b[2]." "."February1"." ".$b[0];
                break;
            case 3:
                $cdr_report[$j]['calldate'] = $b[0]."-".$b[1]."-".$b[2];
                $cdr_report[$j]['calldate2'] = $b[2]." "."March1"." ".$b[0];
                $cdr_report[$j]['calldate_short'] = $b[2]." "."March1"." ".$b[0];
                break;
            case 4:
                $cdr_report[$j]['calldate'] = $b[0]."-".$b[1]."-".$b[2];
                $cdr_report[$j]['calldate2'] = $b[2]." "."April1"." ".$b[0];
                $cdr_report[$j]['calldate_short'] = $b[2]." "."April1"." ".$b[0];
                break;
            case 5:
                $cdr_report[$j]['calldate'] = $b[0]."-".$b[1]."-".$b[2];
                $cdr_report[$j]['calldate2'] = $b[2]." "."May1"." ".$b[0];
                $cdr_report[$j]['calldate_short'] = $b[2]." "."May1"." ".$b[0];
                break;
            case 6:
                $cdr_report[$j]['calldate'] = $b[0]."-".$b[1]."-".$b[2];
                $cdr_report[$j]['calldate2'] = $b[2]." "."June1"." ".$b[0];
                $cdr_report[$j]['calldate_short'] = $b[2]." "."June1"." ".$b[0];
                break;
            case 7:
                $cdr_report[$j]['calldate'] = $b[0]."-".$b[1]."-".$b[2];
                $cdr_report[$j]['calldate2'] = $b[2]." "."July1"." ".$b[0];
                $cdr_report[$j]['calldate_short'] = $b[2]." "."July1"." ".$b[0];
                break;
            case 8:
                $cdr_report[$j]['calldate'] = $b[0]."-".$b[1]."-".$b[2];
                $cdr_report[$j]['calldate2'] = $b[2]." "."August1"." ".$b[0];
                $cdr_report[$j]['calldate_short'] = $b[2]." "."August1"." ".$b[0];
                break;
            case 9:
                $cdr_report[$j]['calldate'] = $b[0]."-".$b[1]."-".$b[2];
                $cdr_report[$j]['calldate2'] = $b[2]." "."September1"." ".$b[0];
                $cdr_report[$j]['calldate_short'] = $b[2]." "."September1"." ".$b[0];
                break;
            case 10:
                $cdr_report[$j]['calldate'] = $b[0]."-".$b[1]."-".$b[2];
                $cdr_report[$j]['calldate2'] = $b[2]." "."October1"." ".$b[0];
                $cdr_report[$j]['calldate_short'] = $b[2]." "."October1"." ".$b[0];
                break;
            case 11:
                $cdr_report[$j]['calldate'] = $b[0]."-".$b[1]."-".$b[2];
                $cdr_report[$j]['calldate2'] = $b[2]." "."November1"." ".$b[0];
                $cdr_report[$j]['calldate_short'] = $b[2]." "."November1"." ".$b[0];
                break;
            case 12:
                $cdr_report[$j]['calldate'] = $b[0]."-".$b[1]."-".$b[2];
                $cdr_report[$j]['calldate2'] = $b[2]." "."December1"." ".$b[0];
                $cdr_report[$j]['calldate_short'] = $b[2]." "."December1"." ".$b[0];
                break;
        };

// Interval reports hour=3 / day=else(in use) / week=2 / month=4
/*
      if($filter['type']==2) {
		if($cdr_report[$j]['calldate_weekday']==1) { $cdr_report[$j]['calldate'] = 0; $cdr_report[$j]['calldate2'] = $cdr_report[$j]['calldate_short'] = translate("Sunday"); }
		else if($cdr_report[$j]['calldate_weekday']==2) { $cdr_report[$j]['calldate'] = 1; $cdr_report[$j]['calldate2'] = $cdr_report[$j]['calldate_short'] = translate("Monday"); }
		else if($cdr_report[$j]['calldate_weekday']==3) { $cdr_report[$j]['calldate'] = 2; $cdr_report[$j]['calldate2'] = $cdr_report[$j]['calldate_short'] = translate("Tuesday"); }
		else if($cdr_report[$j]['calldate_weekday']==4) { $cdr_report[$j]['calldate'] = 3; $cdr_report[$j]['calldate2'] = $cdr_report[$j]['calldate_short'] = translate("Wednesday"); }
		else if($cdr_report[$j]['calldate_weekday']==5) { $cdr_report[$j]['calldate'] = 4; $cdr_report[$j]['calldate2'] = $cdr_report[$j]['calldate_short'] = translate("Thursday"); }
		else if($cdr_report[$j]['calldate_weekday']==6) { $cdr_report[$j]['calldate'] = 5; $cdr_report[$j]['calldate2'] = $cdr_report[$j]['calldate_short'] = translate("Friday"); }
		else if($cdr_report[$j]['calldate_weekday']==7) { $cdr_report[$j]['calldate'] = 6; $cdr_report[$j]['calldate2'] = $cdr_report[$j]['calldate_short'] = translate("Saturday"); };
	}

      else if($filter['type']==3) {
          if($cdr_report[$j]['calldate_hour']=="01" || $cdr_report[$j]['calldate_hour']=="21") $cdr_report[$j]['calldate_short'] = $cdr_report[$j]['calldate'] = $cdr_report[$j]['calldate2'] = $cdr_report[$j]['calldate_hour']." ".translate("hour1");
          else if(($cdr_report[$j]['calldate_hour']>"01" && $cdr_report[$j]['calldate_hour']<"05") || ($cdr_report[$j]['calldate_hour']>"21")) $cdr_report[$j]['calldate_short'] = $cdr_report[$j]['calldate'] = $cdr_report[$j]['calldate2'] = $cdr_report[$j]['calldate_hour']." ".translate("hour2");
          else if(($cdr_report[$j]['calldate_hour']>"04" && $cdr_report[$j]['calldate_hour']<"21") || ($cdr_report[$j]['calldate_hour']=="00")) $cdr_report[$j]['calldate_short'] = $cdr_report[$j]['calldate'] = $cdr_report[$j]['calldate2'] = $cdr_report[$j]['calldate_hour']." ".translate("hour3");
      }

      else if($filter['type']==4) {
          $b = explode("-",$cdr_report[$j]['calldate_month']);
          switch ($b[1]) {
              case 1:
                  $cdr_report[$j]['calldate'] = $b[0]."-".$b[1];
                  $cdr_report[$j]['calldate2'] = "January"." ".$b[0];
                  $cdr_report[$j]['calldate_short'] = "January"." ".$b[0];
                  break;
              case 2:
                  $cdr_report[$j]['calldate'] = $b[0]."-".$b[1];
                  $cdr_report[$j]['calldate2'] = "February"." ".$b[0];
                  $cdr_report[$j]['calldate_short'] = "February"." ".$b[0];
                  break;
              case 3:
                  $cdr_report[$j]['calldate'] = $b[0]."-".$b[1];
                  $cdr_report[$j]['calldate2'] = "March"." ".$b[0];
                  $cdr_report[$j]['calldate_short'] = "March"." ".$b[0];
                  break;
              case 4:
                  $cdr_report[$j]['calldate'] = $b[0]."-".$b[1];
                  $cdr_report[$j]['calldate2'] = "April"." ".$b[0];
                  $cdr_report[$j]['calldate_short'] = "April"." ".$b[0];
                  break;
              case 5:
                  $cdr_report[$j]['calldate'] = $b[0]."-".$b[1];
                  $cdr_report[$j]['calldate2'] = "May"." ".$b[0];
                  $cdr_report[$j]['calldate_short'] = "May"." ".$b[0];
                  break;
              case 6:
                  $cdr_report[$j]['calldate'] = $b[0]."-".$b[1];
                  $cdr_report[$j]['calldate2'] = "June"." ".$b[0];
                  $cdr_report[$j]['calldate_short'] = "June"." ".$b[0];
                  break;
              case 7:
                  $cdr_report[$j]['calldate'] = $b[0]."-".$b[1];
                  $cdr_report[$j]['calldate2'] = "July"." ".$b[0];
                  $cdr_report[$j]['calldate_short'] = "July"." ".$b[0];
                  break;
              case 8:
                  $cdr_report[$j]['calldate'] = $b[0]."-".$b[1];
                  $cdr_report[$j]['calldate2'] = "August"." ".$b[0];
                  $cdr_report[$j]['calldate_short'] = "August"." ".$b[0];
                  break;
              case 9:
                  $cdr_report[$j]['calldate'] = $b[0]."-".$b[1];
                  $cdr_report[$j]['calldate2'] = "September"." ".$b[0];
                  $cdr_report[$j]['calldate_short'] = "September"." ".$b[0];
                  break;
              case 10:
                  $cdr_report[$j]['calldate'] = $b[0]."-".$b[1];
                  $cdr_report[$j]['calldate2'] = "October"." ".$b[0];
                  $cdr_report[$j]['calldate_short'] = "October"." ".$b[0];
                  break;
              case 11:
                  $cdr_report[$j]['calldate'] = $b[0]."-".$b[1];
                  $cdr_report[$j]['calldate2'] = "November"." ".$b[0];
                  $cdr_report[$j]['calldate_short'] = "November"." ".$b[0];
                  break;
              case 12:
                  $cdr_report[$j]['calldate'] = $b[0]."-".$b[1];
                  $cdr_report[$j]['calldate2'] = "December"." ".$b[0];
                  $cdr_report[$j]['calldate_short'] = "December"." ".$b[0];
                  break;
          };
      }

->    else {
      }
*/
      $cdr_report[$j]['calls_served'] = $cdr_report[$j]['completeagent_calls'] + $cdr_report[$j]['completecaller_calls'] + $cdr_report[$j]['transfer_calls'];
      $cdr_report[$j]['calls_unserved'] = $cdr_report[$j]['abandon_calls'] + $cdr_report[$j]['exitwithtimeout_calls'] + $cdr_report[$j]['exitempty_calls'] + $cdr_report[$j]['exitwithkey_calls'] + $cdr_report[$j]['syscompat_calls'];
      $cdr_report[$j]['calls_total'] = $cdr_report[$j]['calls_served'] + $cdr_report[$j]['calls_unserved'];
      $cdr_report[$j]['calls_served_per'] = round(($cdr_report[$j]['calls_served']*100/$cdr_report[$j]['calls_total']),1);
      $cdr_report[$j]['calls_unserved_per'] = round(($cdr_report[$j]['calls_unserved']*100/$cdr_report[$j]['calls_total']),1);
      $sum_talktime = $cdr_report[$j]['talktime_sum'] = $cdr_report[$j]['sum_talktime'];
      $sum_holdtime = $cdr_report[$j]['holdtime_sum'] = $cdr_report[$j]['sum_holdtime'];
      $cdr_report[$j]['sum_talktime'] = $utils->time_format($cdr_report[$j]['sum_talktime']);
      $cdr_report[$j]['sum_holdtime'] = $utils->time_format($cdr_report[$j]['sum_holdtime']);
      $cdr_report[$j]['calls_served_chart'] = '
        <div style="float:left;width:120px;">
          <div class="chart_calls_default_right">'.$cdr_report[$j]['calls_served'].' ('.$cdr_report[$j]['calls_served_per'].'%)</div>
          <div class="chart_calls_served" style="width:'.$cdr_report[$j]['calls_served_per'].'%;"></div>
        </div>
      ';
      $cdr_report[$j]['calls_unserved_chart'] = '
        <div style="float:right;width:120px;">
          <div class="chart_calls_default_left">'.$cdr_report[$j]['calls_unserved'].' ('.$cdr_report[$j]['calls_unserved_per'].'%)</div>
          <div class="chart_calls_unserved" style="width:'.$cdr_report[$j]['calls_unserved_per'].'%;"></div>
        </div>		
      ';
      $cdr_report[$j]['sum_talktime_chart'] = '
        <div style="float:right;width:120px;">
          <div class="chart_calls_default_right">'.$cdr_report[$j]['sum_talktime'].'</div>
          <div class="chart_calls_served" style="width:'.round(($sum_talktime*100/($sum_talktime+$sum_holdtime)),1).'%;"></div>
        </div>
      ';
      $cdr_report[$j]['sum_holdtime_chart'] = '
        <div style="float:left;width:120px;">
          <div class="chart_calls_default_left">'.$cdr_report[$j]['sum_holdtime'].'</div>
          <div class="chart_calls_unserved" style="width:'.round(($sum_holdtime*100/($sum_talktime+$sum_holdtime)),1).'%;"></div>
        </div>		
      ';
    };
    return [$cdr_report, $i];
  }

  public function getInterval_reports_day($filter, $pos, $count = 20, $onlycount = 0)
  {
    $cdr_report = $this->getInterval_reports_day_total($filter, $pos, $count = 20, $onlycount = 0)[0];
    return $cdr_report;
  }

  public function getInterval_reports_day_chart1($filter, $pos, $count = 20, $onlycount = 0) {

    $i = $this->getInterval_reports_day_total($filter, $pos, $count = 20, $onlycount = 0)[1];
    $cdr_report = $this->getInterval_reports_day_total($filter, $pos, $count = 20, $onlycount = 0)[0];

    $chart_total_calls = [];
    for($j=0; $j<=$i; $j++) {
      $chart_total_calls[$j]['sales'] = $cdr_report[$j]['calls_total'];
      $chart_total_calls[$j]['color'] = "#FF3333";
      $chart_total_calls[$j]['month'] = $cdr_report[$j]['calldate_short'];
    };

    return $chart_total_calls;
  }

  public function getInterval_reports_day_chart2($filter, $pos, $count = 20, $onlycount = 0) {

    $i = $this->getInterval_reports_day_total($filter, $pos, $count = 20, $onlycount = 0)[1];
    $cdr_report = $this->getInterval_reports_day_total($filter, $pos, $count = 20, $onlycount = 0)[0];

    $chart_total_calls = [];
    for($j=0; $j<=$i; $j++) {
      $chart_total_calls[$j]['sales'] = $cdr_report[$j]['transfer_calls'];
      $chart_total_calls[$j]['sales2'] = $cdr_report[$j]['completeagent_calls'];
      $chart_total_calls[$j]['sales3'] = $cdr_report[$j]['completecaller_calls'];
      $chart_total_calls[$j]['year'] = $cdr_report[$j]['calldate_short'];
    };
    return $chart_total_calls;
  }

  public function getInterval_reports_day_chart3($filter, $pos, $count = 20, $onlycount = 0) {

    $i = $this->getInterval_reports_day_total($filter, $pos, $count = 20, $onlycount = 0)[1];
    $cdr_report = $this->getInterval_reports_day_total($filter, $pos, $count = 20, $onlycount = 0)[0];

    $chart_total_calls = [];
    for($j=0; $j<=$i; $j++) {
      $chart_total_calls[$j]['sales'] = $cdr_report[$j]['abandon_calls'];
      $chart_total_calls[$j]['sales2'] = $cdr_report[$j]['exitwithtimeout_calls'];
      $chart_total_calls[$j]['sales3'] = $cdr_report[$j]['exitwithkey_calls'];
      $chart_total_calls[$j]['sales4'] = $cdr_report[$j]['exitempty_calls'];
      $chart_total_calls[$j]['year'] = $cdr_report[$j]['calldate_short'];
    };

    return $chart_total_calls;
  }

  public function getInterval_reports_day_chart4($filter, $pos, $count = 20, $onlycount = 0) {

    $i = $this->getInterval_reports_day_total($filter, $pos, $count = 20, $onlycount = 0)[1];
    $cdr_report = $this->getInterval_reports_day_total($filter, $pos, $count = 20, $onlycount = 0)[0];

    $chart_total_calls = [];
    for($j=0; $j<=$i; $j++) {
      $chart_total_calls[$j]['sales'] = $cdr_report[$j]['talktime_min'];
      $chart_total_calls[$j]['sales2'] = ($cdr_report[$j]['calls_served']!=0 ? round($cdr_report[$j]['talktime_sum']/$cdr_report[$j]['calls_served']) : 0);
      $chart_total_calls[$j]['sales3'] = $cdr_report[$j]['talktime_max'];
      $chart_total_calls[$j]['year'] = $cdr_report[$j]['calldate_short'];
    };

    return $chart_total_calls;
  }

  public function getInterval_reports_day_chart5($filter, $pos, $count = 20, $onlycount = 0) {

    $i = $this->getInterval_reports_day_total($filter, $pos, $count = 20, $onlycount = 0)[1];
    $cdr_report = $this->getInterval_reports_day_total($filter, $pos, $count = 20, $onlycount = 0)[0];

    $chart_total_calls = [];
    for($j=0; $j<=$i; $j++) {
      $chart_total_calls[$j]['sales'] = $cdr_report[$j]['holdtime_min'];
      $chart_total_calls[$j]['sales2'] = round($cdr_report[$j]['holdtime_sum']/($cdr_report[$j]['calls_served']+$cdr_report[$j]['calls_unserved']));
      $chart_total_calls[$j]['sales3'] = $cdr_report[$j]['holdtime_max'];
      $chart_total_calls[$j]['year'] = $cdr_report[$j]['calldate_short'];
    };

    return $chart_total_calls;
  }
}
