<?php
class PBXConfigHelper
{
  const 
      ASTERISK_DIR = __DIR__."/../../configs/asterisk",
      SIP_FILE = __DIR__."/../../configs/sip.conf",
      QUEUES_FILE = __DIR__."/../../configs/queues.conf",
      RULES_FILE = __DIR__."/../../configs/extensions.conf";
  public function getCode($line) {
    preg_match_all('/\[(.+)\]/', $line, $matches);
    if (is_array($matches) && isset($matches[COUNT($matches)-1]) && isseT($matches[COUNT($matches)-1][0]) && strlen($matches[COUNT($matches)-1][0])) {
      return iconv( "WINDOWS-1251", "UTF-8",  trim($matches[COUNT($matches)-1][0]));
    }
    return null;
  }
  public function getAsteriskFiles()
  {
    if (is_dir(self::ASTERISK_DIR)) {
      $files = scandir(self::ASTERISK_DIR);
      return $files;
    }
    return false;
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
  
  /**
   * @param string $str
   * @return string
   */
  public static function generatePatternName($str)
  {
    $arr = explode("_",$str);
    $res = [];
    foreach ($arr as $value) {
      if ($value != "tpl") {
        $value = strval($value);
        $value[0] = strtoupper($value[0]);
      }
      $res[] = $value;
    }
    if (count($res)) {
      $name = implode(" ", $res);
    } else {
      $name = $str;
    }
    return $name;
  }
  
  /**
   * @param $patterns
   */
  public static function setPatterns($patterns)
  {
    if (!file_exists(self::SIP_FILE)) {
      /* try create file if OS give me permissions for this */
      file_put_contents(self::SIP_FILE, "");
    }
    if (!is_writable(self::SIP_FILE)) {
      Throw new Exception(self::SIP_FILE."  don`t writable");
    }
    $handle = fopen(self::SIP_FILE, "a");
    foreach ($patterns as $pattern) {
      if (isset($pattern["options"])) {
        $patternString  = sprintf("\n[%s](!) ;EPBXT: %s \n",$pattern['code'],$pattern['name']);
        foreach ($pattern["options"] as $key => $value) {
          $patternString .= sprintf("%s=%s",$key,$value);
        }
        fwrite($handle, $patternString);
      }
    }
    fclose($handle);
  }
}

?>