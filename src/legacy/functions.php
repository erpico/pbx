<?php

function time_format($x)
{
    $ratesec = $x;
    $x = "";
    $days = (intval($ratesec / 86400) == 0 ? "" : intval($ratesec / 86400));
    if ($days != "") {
        $x = $x . $days . "d" . " ";
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







function sql_allow_extens($user_extens) {
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
};




?>