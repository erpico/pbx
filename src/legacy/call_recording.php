<?php

//require_once(__DIR__."../../vendor/autoload.php");

$user = new User();

if(isset($_GET['download'])) {
    $b = explode(".",$_GET['name']);

    $filename = "/var/spool/asterisk/monitor/".substr($_GET['name'],0,10)."/".substr($_GET['name'],11,2)."/".$b[0].".".$b[1];
    if(file_exists($filename.".WAV")) {
        $filename = $filename.".WAV";
    }
    else if(file_exists($filename.".wav")) {
        $filename = $filename.".wav";
        //$filename2 = $_GET['name'].".wav";
    }
    else if(file_exists($filename.".mp3")) {
        $filename = $filename.".mp3";
    };

    if (file_exists($filename)) {
        header("Content-disposition: attachment; filename=".$_GET['name']);
        header("Content-type: application/octet-stream");
        header("Content-Description: File Transfer");
        readfile($filename);
        exit;
    }
    else {
        header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found', true, 404);
        echo "no file";
    };
}
else
    if(isset($_GET['player'])) {
        /**
         * Stream-able file handler
         *
         * @param String $file_location
         * @param Header|String $content_type
         * @return content
         */
        function stream($file, $content_type = 'application/octet-stream') {
            @error_reporting(0);

            // Make sure the files exists, otherwise we are wasting our time
            if (!file_exists($file)) {
                header("HTTP/1.1 404 Not Found");
                exit;
            }

            // Get file size
            $filesize = sprintf("%u", filesize($file));

            // Handle 'Range' header
            if(isset($_SERVER['HTTP_RANGE'])){
                $range = $_SERVER['HTTP_RANGE'];
            } elseif($apache = apache_request_headers()){
                $headers = [];
                foreach ($apache as $header => $val){
                    $headers[strtolower($header)] = $val;
                }
                if(isset($headers['range'])){
                    $range = $headers['range'];
                }
                else $range = FALSE;
            } else $range = FALSE;

            //Is range
            if($range){
                $partial = true;
                list($param, $range) = explode('=',$range);
                // Bad request - range unit is not 'bytes'
                if(strtolower(trim($param)) != 'bytes'){
                    header("HTTP/1.1 400 Invalid Request");
                    exit;
                }
                // Get range values
                $range = explode(',',$range);
                $range = explode('-',$range[0]);
                // Deal with range values
                if ($range[0] === ''){
                    $end = $filesize - 1;
                    $start = $end - intval($range[0]);
                } else if ($range[1] === '') {
                    $start = intval($range[0]);
                    $end = $filesize - 1;
                }else{
                    // Both numbers present, return specific range
                    $start = intval($range[0]);
                    $end = intval($range[1]);
                    if ($end >= $filesize || (!$start && (!$end || $end == ($filesize - 1)))) $partial = false; // Invalid range/whole file specified, return whole file
                }
                $length = $end - $start + 1;
            }
            // No range requested
            else $partial = false;

            // Send standard headers
            header("Content-Type: $content_type");
            header("Content-Length: $filesize");
            header('Accept-Ranges: bytes');

            // send extra headers for range handling...
            if ($partial) {
                header('HTTP/1.1 206 Partial Content');
                header("Content-Range: bytes $start-$end/$filesize");
                if (!$fp = fopen($file, 'rb')) {
                    header("HTTP/1.1 500 Internal Server Error");
                    exit;
                }
                if ($start) fseek($fp,$start);
                while($length){
                    set_time_limit(0);
                    $read = ($length > 8192) ? 8192 : $length;
                    $length -= $read;
                    print(fread($fp,$read));
                }
                fclose($fp);
            }
            //just send the whole file
            else readfile($file);
            exit;
        };

        $b = explode(".",$_GET['name']);

        $filename = "/var/spool/asterisk/monitor/".substr($_GET['name'],0,10)."/".substr($_GET['name'],11,2)."/".$b[0].".".$b[1];
        if(file_exists($filename.".WAV")) $filename = $filename.".WAV";
        else if(file_exists($filename.".wav")) $filename = $filename.".wav";
        else if(file_exists($filename.".mp3")) $filename = $filename.".mp3";

        if (file_exists($filename)) {

            stream($filename, 'audio/mpeg');
            exit;
        }
        else {
            header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found', true, 404);
            echo "no file"; echo $filename;
        }
    }
    else {

        $users_list = $user->getUsersList();

        $date1 = date("Y-m-d", time() - 86400);
        $date2 = date("Y-m-d");
        $time1 = date("H", time() - 86400);
        $time2 = date("H");
        if(isset($_GET['t1']) && isset($_GET['t2'])) {
            $date1 = substr($_GET['t1'],0,10);
            $date2 = substr($_GET['t2'],0,10);
            $time1 = substr($_GET['t1'],11,2);
            $time2 = substr($_GET['t2'],11,2);
        };

        $monitor_dir = "/var/spool/asterisk/monitor";
        $call_recording = [];
        $call_recording_one = [];
        $i = -1;

        $deny_num = $user->deny_numbers();
        $allow_extens = $user->allow_extens();
        //for($date_current = $date1; $date_current<=$date2;$date_current=date("Y-m-d", strtotime($date_current) + 86400))
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

                        //$ext_elem = true;
                        $ext_elem = false;
                        if(empty($allow_extens)) $ext_elem = true;
                        //else if(in_array($b[4],$allow_extens) || in_array($b[5],$allow_extens)) $ext_elem = true;	//МАСКА!!!!!!
                        else if(check_mask($b[4],$allow_extens) || check_mask($b[5],$allow_extens)) $ext_elem = true;

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
                            //$dur = intval((filesize($f)-1100)/1625);
                            //if ($ext == 'mp3') $dur = intval((filesize($f)-1100)/3655);//intval(filesize($f)/3655);

//                            $getID3 = new getID3;
//                            $ThisFileInfo = $getID3->analyze($f);

//                            $dur = @$ThisFileInfo['playtime_seconds'];

//                            $call_recording_one["duration"] = time_format($dur);
                            //$call_recording_one["duration"] = sprintf("%02d:%02d",intval($dur/60),intval($dur%60));
//                            if($b[6]=="o") $call_recording_one["call"] = "Исходящие";
//                            else if($b[6]=="i") $call_recording_one["call"] = "Входящие";

                            $call_recording[$i] = $call_recording_one;
                        };
                    };
                };
            };
            $date_current=date("Y-m-d", strtotime($date_current) + 86400);
        };

        return $call_recording;
    };