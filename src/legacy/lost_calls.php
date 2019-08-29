<?php

namespace Erpico;

class Lost_calls {
  private $container;
  private $db;
  private $auth;  

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function isClientCalledBack($src, $sql) {
        $utils = new Utils();

        $query = "
        SELECT COUNT(*) AS count_calls
        FROM queue_cdr
        WHERE !outgoing AND src LIKE '%".$src."' AND reason != 'ABANDON' AND reason != 'EXITWITHTIMEOUT' AND
        reason != 'EXITEMPTY' AND reason != 'EXITWITHTKEY' AND reason != 'RINGNOANSWER' ".$sql;

        $que = $this->auth->allowed_queues();
        $queues = $utils->sql_allowed_queues($que);
        $query.= $queues;

        $lost_calls = $this->db->query($query);
        $result = $lost_calls->fetch(\PDO::FETCH_ASSOC);
        return $result["count_calls"] > 0;
  }

  public function isManagerCalledBack($src, $sql) {
        $query = "
        SELECT COUNT(*) AS count_calls
        FROM cdr
        WHERE src LIKE '%".$src."' AND disposition = 'ANSWERED' ".$sql;

        $lost_calls = $this->db->query($query);
        $result = $lost_calls->fetch(\PDO::FETCH_ASSOC);
        return $result["count_calls"] > 0;
  }

  public function getLastCallDate($src, $sql) {
        $utils = new Utils();

        $query = "
        SELECT MAX(calldate), UNIX_TIMESTAMP(MAX(calldate)) 
        FROM queue_cdr 
        WHERE src LIKE '%".$src."' ".$sql;

        $que = $this->auth->allowed_queues();
        $queues = $utils->sql_allowed_queues($que);
        $query.= $queues;

        $lost_calls = $this->db->query($query);
        $result = $lost_calls->fetch(\PDO::FETCH_BOTH);
        return $result;
  }

  public function getLastCallDateUTM($src, $sql) {
        $utils = new Utils();

        $query = "
        SELECT UNIX_TIMESTAMP(MAX(calldate)) 
        FROM queue_cdr 
        WHERE src LIKE '%".$src."' ".$sql;

        $que = $this->auth->allowed_queues();
        $queues = $utils->sql_allowed_queues($que);
        $query.= $queues;

        $lost_calls = $this->db->query($query);
        $result = $lost_calls->fetch(\PDO::FETCH_NUM);
        return $result[0];
  }

  public function getManagerLastCallBackDateUTM($src, $sql) {

        $query = "
        SELECT UNIX_TIMESTAMP(MAX(calldate)) 
        FROM cdr
        WHERE src LIKE '%".$src."' AND disposition = 'ANSWERED' ".$sql;

        $lost_calls = $this->db->query($query);
        $result = $lost_calls->fetch(\PDO::FETCH_NUM);
        return $result[0];
  }

  public function getLost_calls_list($filter, $pos, $count = 20, $onlycount = 0) {

    // Here need to add permission checkers and filters

    $utils = new Utils();

    if ($onlycount) {
      $ssql = " COUNT(*) ";
    } else {
      $ssql = " DISTINCT src  ";
    }

    if(isset($filter['t1']) && isset($filter['t2'])) $sql = "
      AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
    else $sql = "
      AND UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 86400 ";

    $filter = $sql;
    $sql.= " AND (reason = 'ABANDON' OR reason = 'EXITWITHTIMEOUT' OR reason = 'EXITEMPTY' OR reason = 'EXITWITHTKEY') ";

    $query = "
      SELECT ".$ssql."
      FROM queue_cdr 
      WHERE !outgoing ".$sql;

    $que = $this->auth->allowed_queues();
    $queues = $utils->sql_allowed_queues($que);
    $query.= $queues;

    if ($count) {
      $query .= " LIMIT $pos, $count";
    }

    if ($onlycount) {
      $res = $this->db->query($query);
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }
    $lost_calls = $this->db->query($query);
    $count = 0;
    $lost_calls_arr = [];
    while($result = $lost_calls->fetch(\PDO::FETCH_BOTH)) {
        $lost_calls_arr[$count] = $result;
        $count++;
    };

    $lostCals = [];

    $clientCallbackCount = 0;
    $managerCallbackCount = 0;
    $managerCallbackPauseAvg = 0;
    $lostCount = 0;

    for ($j = 0; $j < $count; $j++) {
        $src = $lost_calls_arr[$j][0];

        $lastAbandonedCallDates = $this->getLastCallDate($src, $sql);
        $lastAbandonedCallDate = $lastAbandonedCallDates[0];
        $callDateFilter = " AND calldate>'".$lastAbandonedCallDate."'";

        $clientCalledBack = $this->isClientCalledBack($src, $filter.$callDateFilter);
        if ($clientCalledBack) {
            $clientCallbackCount++;
        }
        else {
            $managerCalledBack = $this->isManagerCalledBack($src, $filter.$callDateFilter);
            $managerCallbackCount += $managerCalledBack ? 1 : 0;
            if (!$clientCalledBack && !$managerCalledBack) {
                $lostCals[$lostCount]['list_losted_calls'] = $src;
                $lostCals[$lostCount]['calldate_last_calls'] = $lastAbandonedCallDate;
                $lostCount++;
            }
        }
    };
    return $lostCals;

  }

  public function getLost_calls_total($filter, $pos, $count = 20, $onlycount = 0) {

    $utils = new Utils();

    if ($onlycount) {
      $ssql = " COUNT(*) ";
    } else {
      $ssql = " DISTINCT src  ";
    }

    if(isset($filter['t1']) && isset($filter['t2'])) $sql = "
      AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
    else $sql = "
      AND UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 86400 ";

    $filter = $sql;
    $sql.= " AND (reason = 'ABANDON' OR reason = 'EXITWITHTIMEOUT' OR reason = 'EXITEMPTY' OR reason = 'EXITWITHTKEY') ";

    $query = "
      SELECT ".$ssql." 
      FROM queue_cdr 
      WHERE !outgoing ".$sql;

    $que = $this->auth->allowed_queues();
    $queues = $utils->sql_allowed_queues($que);
    $query.= $queues;

    if ($count) {
      $query .= " LIMIT $pos, $count";
    }

    if ($onlycount) {
      $res = $this->db->query($query);
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }
    $lost_calls = $this->db->query($query);
    $count = 0;
    $lost_calls_arr = [];
    while($result = $lost_calls->fetch(\PDO::FETCH_BOTH)) {
        $lost_calls_arr[$count] = $result;
        $count++;
    };

    $lostCals = [];

    $clientCallbackCount = 0;
    $managerCallbackCount = 0;
    $managerCallbackPauseAvg = 0;
    $lostCount = 0;

    for ($j = 0; $j < $count; $j++) {
        $src = $lost_calls_arr[$j][0];

        $lastAbandonedCallDates = $this->getLastCallDate($src, $sql);
        $lastAbandonedCallDate = $lastAbandonedCallDates[0];
        $lastAbandonedCallDateUTM = $lastAbandonedCallDates[1];
        $callDateFilter = " AND calldate>'".$lastAbandonedCallDate."'";

        $clientCalledBack = $this->isClientCalledBack($src, $filter.$callDateFilter);
        if ($clientCalledBack) {
            $clientCallbackCount++;
        }
        else {
            $managerCalledBack = $this->isManagerCalledBack($src, $filter.$callDateFilter);
            $managerCallbackCount += $managerCalledBack ? 1 : 0;
            if (!$clientCalledBack && !$managerCalledBack) {
                $lostCals[$lostCount]['list_losted_calls'] = $src;
                $lostCals[$lostCount]['calldate_last_calls'] = $lastAbandonedCallDate;
                $lostCount++;
            }
            else if ($managerCalledBack) {
                $managerLastCallBackDateUTM = $this->getManagerLastCallBackDateUTM($src, $filter.$callDateFilter);
                if ($managerLastCallBackDateUTM > $lastAbandonedCallDateUTM) {
                    $managerCallbackPauseAvg += $managerLastCallBackDateUTM - $lastAbandonedCallDateUTM;
                }
            }
        }
    };
    $managerCallbackPauseAvg = $managerCallbackCount!=0 ? round(($managerCallbackPauseAvg / $managerCallbackCount) / 3600, 2) : 0;

    $lost_calls_arr['title_unserved_calls'] = "<b>Необслуженные звонки</b>";
    $lost_calls_arr['client_callback'] = $clientCallbackCount;
    $lost_calls_arr['manager_callback'] = $managerCallbackCount;
    $lost_calls_arr['speed_callback'] = $managerCallbackPauseAvg;
    $lost_calls_arr['call_was_lost'] = $lostCount;

    return $lost_calls_arr;
  }
}