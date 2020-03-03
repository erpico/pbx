<?php

namespace Erpico;

class Utils {

  public function time_format($x)
  {
      $ratesec = $x;
      $x = "";
      $days = (intval($ratesec / 86400) == 0 ? "" : intval($ratesec / 86400));
      if ($days != "") {
          $x = $x . $days . "д" . " ";
          $ratesec = $ratesec - intval($ratesec / 86400) * 86400;
      };
      $hours = (intval($ratesec / 3600) == 0 ? "" : intval($ratesec / 3600));
      if ($hours != "") {
          $x = $x . $hours . ":";
          $ratesec = $ratesec - intval($ratesec / 3600) * 3600;
      };
      $minutes = intval($ratesec / 60);
      if ($minutes < 10) $minutes = "0" . $minutes;
      $seconds = intval($ratesec % 60);
      if ($seconds < 10) $seconds = "0" . $seconds;
      $x = $x . $minutes . ":" . $seconds;
      if ($ratesec < 1) {
          $x .= ".";
          $x .= ceil($ratesec * 100);
      }
      return $x;
  }

  public function get_allowed_queues_from_filter($allowed, $queues) {
    if (is_array($allowed) && count($allowed)){
      $result = [];
      if (is_array($queues)){
        foreach ($queues as $queue){
          $queue = trim($queue, "'");
          if (in_array($queue, $allowed)) {          
            $result[] = "'".$queue."'";
          }
        }
        return $result;
      }
    } else {
      // Allowed all
      return $queues;
    }  
  }
  public function sql_allowed_queues($user_queues) {
    $i = count($user_queues);
    $queues_d = "";
    if ($i != 0) {
      for ($h = 0; $h < $i; $h++) {
        $repeat = 0;
        for ($g = 0; $g < $h; $g++) {
          if ($user_queues[$h] == $user_queues[$g]) $repeat = 1;
        };
        if (!$repeat) {
          $queues_d .= "'" . $user_queues[$h] . "'";
          $queues_d .= ",";
        };
      };
      $queues_d = substr($queues_d, 0, -1);
      $queues = " AND queue IN (" . $queues_d . ") ";
    }
    else $queues = "";
    return $queues;
  }


  public function sql_allowed_queues_for_records($user_queues) {
    $i = count($user_queues);
    $queues_d = "";
    if ($i != 0) {
      for ($h = 0; $h < $i; $h++) {
        $repeat = 0;
        for ($g = 0; $g < $h; $g++) {
          if ($user_queues[$h] == $user_queues[$g]) $repeat = 1;
        };
        if (!$repeat) {
          $queues_d .= "'" . $user_queues[$h] . "'";
          $queues_d .= ",";
        };
      };
      $queues_d = substr($queues_d, 0, -1);
      $queues = " AND a.queue IN (" . $queues_d . ") ";
    }
    else $queues = "";
    return $queues;
  }


  public function sql_allow_extens($user_extens) {
    $i = count($user_extens);
    if($i!=0) {
        $extens = " AND (";
        for ($h = 0; $h < $i; $h++) {
            $repeat = 0;
            for ($g = 0; $g < $h; $g++) {
                if ($user_extens[$h] == $user_extens[$g]) $repeat = 1;
            };
            if (!$repeat) {
                $extens .= "src LIKE '" . $user_extens[$h] . "' OR dst LIKE '" . $user_extens[$h] . "' ";
                $extens .= " OR ";
            };
        };
        $extens = substr($extens, 0, -4);
        $extens .= ") ";
    }
    else $extens = "";
    return $extens;
  }


  public function sql_deny_numbers_for_records($user_deny)
  {
    $i = count($user_deny);
    if($i!=0) {
      $deny = " AND A.src NOT IN (".implode(",", $user_deny).") ";
    }
    else $deny = "";
    return $deny;
  }


  public function sql_allowed_queues_n($user_queues) {
    $i = count($user_queues);
    $queues_d = "";
    if ($i != 0) {
        for ($h = 0; $h < $i; $h++) {
            $repeat = 0;
            for ($g = 0; $g < $h; $g++) {
                if ($user_queues[$h] == $user_queues[$g]) $repeat = 1;
            };
            if (!$repeat) {
                $queues_d .= "'" . $user_queues[$h] . "'";
                $queues_d .= ",";
            };
        };
        $queues_d = substr($queues_d, 0, -1);
        $queues = " AND queuename IN (" . $queues_d . ") ";
    }
    else $queues = "";
    return $queues;
  }


  public function check_mask($x,$y){
    for($i=0; $i<count($y); $i++) if(strlen($x)==strlen($y[$i])) {
        $pos = strpos($y[$i],'_');
        if ($pos) {
            $y1 = substr($y[$i], 0, $pos);
            $x1 = substr($x, 0, $pos);
        } else {
            $y1 = $y[$i];
            $x1 = $x;
        }
        if($x1==$y1) return true;
    };
    return false;
  }

  public function delSpaces($delSpace)
  {
    $delSpace = str_replace(', ', ',',$delSpace);
    $value = explode(",",$delSpace);
    return $value;
  }

}