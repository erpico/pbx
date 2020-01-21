<?php

class PBXConfigHelper
{
  const 
      SIP_FILE = "/etc/asterisk/sip.conf",
      QUEUES_FILE = "/etc/asterisk/queues.conf",
      RULES_FILE = "/etc/asterisk/extensions.conf";
  public function getCode($line) {
    preg_match_all('/\[(.+)\]/', $line, $matches);
    if (is_array($matches) && isset($matches[COUNT($matches)-1]) && isseT($matches[COUNT($matches)-1][0]) && strlen($matches[COUNT($matches)-1][0])) {
      return iconv( "WINDOWS-1251", "UTF-8",  trim($matches[COUNT($matches)-1][0]));
    }
    return null;
  }

  public function getName($line) {
    $arr = explode(":", $line);
    if (COUNT($arr)) {
      if (strlen($arr[COUNT($arr)-1])) {
        return iconv( "WINDOWS-1251", "UTF-8",  trim($arr[COUNT($arr)-1]));
      }
    }
    return null;
  }

  public function getOptions($pathFile) {
    if (!$pathFile) {
      return false;
    }
    error_reporting(0);
    $result = [];
    $sipLines = file($pathFile);

    foreach ($sipLines as $line) {

      if (strpos($line, "EPBXT")) {    
        if ($this->getCode($line) && $this->getName($line)) {
          $result[] = [
            "id" => $this->getCode($line),
            "value" =>$this->getName($line)
          ];
        }
      }
    }
    return $result;
  }
  
}

?>