<?php

namespace Erpico;

class Call_recording {
  private $container;
  private $db;
  private $auth;  

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getCall_recording_2($filter, $pos, $count = 20, $onlycount = 0) {
    
    // Here need to add permission checkers and filters

    $utils = new Utils();
    $users_list = $this->auth->getUsersList();
    $t1 = date("2018-10-25 15:27:50");
    $t2 = date("2018-10-27 15:27:50");

    if ($onlycount) {
      $res = $this->db->query("SELECT COUNT(*) FROM acl_user");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    $date1 = date("Y-m-d", time() - 86400);
    $date2 = date("Y-m-d");
    $time1 = date("H", time() - 86400);
    $time2 = date("H");

//  Time settings
/*
    if(isset($_GET['t1']) && isset($_GET['t2'])) {
      $date1 = substr($_GET['t1'],0,10);
      $date2 = substr($_GET['t2'],0,10);
      $time1 = substr($_GET['t1'],11,2);
      $time2 = substr($_GET['t2'],11,2);
    };
*/
      if(isset($t1) && isset($t2)) {
          $date1 = substr($t1,0,10);
          $date2 = substr($t2,0,10);
          $time1 = substr($t1,11,2);
          $time2 = substr($t2,11,2);
      };

      $monitor_dir = "/var/spool/asterisk/monitor";
      $call_recording = [];
      $call_recording_one = [];
      $i = -1;

      $deny_num = $this->auth->deny_numbers();
      $allow_extens = $this->auth->allow_extens();
      $date_current = $date1;
      while($date_current<=$date2) {
          for($time_current=0;$time_current<24;$time_current++) {
              if(($date_current==$date1 && $time_current>=$time1 && $date1!=$date2) || ($date_current==$date2 && $time_current<=($time2-1) && $date1!=$date2) || ($date_current>$date1 && $date_current<$date2) || ($time_current>=$time1 && $time_current<=$time2 && $date1==$date2)) {
                  if($time_current<10) $time_current = "0".$time_current;
                  $file_dir = $monitor_dir."/".$date_current."/".$time_current."/*";
                  foreach(glob($file_dir) as $f) {
                      $fileinfo = pathinfo(strtolower($f));
                      $ext = $fileinfo['extension'];
                      $b = explode("-",$fileinfo['basename']);
                      $b[3] = str_replace("_", ":", $b[3]);

                      $repeat = 0;
                      for($m=0; $m<$user_deny_count; $m++) if(($b[4]==$user_deny[$m]) || ($b[5]==$user_deny[$m])) $repeat = 1;

                      $ext_elem = false;
                      if(empty($allow_extens)) $ext_elem = true;
                      else if($utils->check_mask($b[4],$allow_extens) || $utils->check_mask($b[5],$allow_extens)) $ext_elem = true;

                      if(!in_array($b[4],$deny_num) && !in_array($b[5],$deny_num)) if($ext_elem) if($repeat==0) {
                          $i++;

                          $call_recording_one["calldate"] = $b[0]."-".$b[1]."-".$b[2]." ".$b[3];
                          $call_recording_one["calldate2"] = date('d.m.Y H:i:s',strtotime($call_recording_one["calldate"]));
                          $call_recording_one["name"] = basename($f,'.'.$fileinfo['extension']).".".$ext;//(isset($fileinfo['filename']) ? $fileinfo['filename'] : $fileinfo['basename']);
                          $call_recording_one["size"] = round(filesize($f)/1024, 1);
                          $call_recording_one["src"] = $b[4];
                          $call_recording_one["dst"] = $b[5];
                          if (isset($users_list[$b[4]])) {
                              $call_recording_one["user"] = $users_list[$b[4]]["fio"];
                          } else if (isset($users_list[$b[5]])) {
                              $call_recording_one["user"] = $users_list[$b[5]]["fio"];
                          } else {
                              $call_recording_one["user"] = '';
                          }
                          $call_recording_one["download"] = '<i class="fa fa-download" style="color:#666666;margin-top:10px;"></i>';

//                            $getID3 = new getID3;
//                            $ThisFileInfo = $getID3->analyze($f);

//                            $dur = @$ThisFileInfo['playtime_seconds'];

//                            $call_recording_one["duration"] = time_format($dur);

                            if($b[6]=="o") $call_recording_one["call"] = "Исходящие";
                            else if($b[6]=="i") $call_recording_one["call"] = "Входящие";

                          $call_recording[$i] = $call_recording_one;
                      };
                  };
              };
          };
          $date_current=date("Y-m-d", strtotime($date_current) + 86400);
      };

      return $call_recording;

  }
}