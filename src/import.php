<?php
 class ConfigImport
 {  
   private $files;
   private $uploadPath;

   public function __construct($files, $uploadPath)
   {
     $this->uploadPath = $uploadPath;
     if (is_array($files)) {
       $this->files = $files;      
     } 
   }

   public function getConfigData()
   {

    $phones = [];
    $channels = [];
    $patterns = [];
    $buffer = [];
    $options = [];
    if (is_array($this->files)) {  
      foreach ($this->files as $file) {
        $handle = fopen($this->uploadPath."/".$file, "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (!$this->isComment($line)) {
                  if (strpos($line, "[") !== FALSE && strpos($line, "]") !== FALSE) {
                    if (isset($name)) {
                      $this->setValues($name, $options, $headerLine, $arr, $patterns, $phones, $channels);
                    }
                    $options = [];
                    $arr = [];
                    $name = $this->getName($line);
                    $headerLine = $line;
                  }
                  else {
                    if (strpos($line, "=") !== FALSE) {
                      $optionsArr = explode("=", $line);
                      if (COUNT($optionsArr) == 2) {
                        $value = $optionsArr[1];
                        if (strpos($value,";")) {
                          $value = explode(";",$value);
                          $value = $value[0];
                        }
                        $options[$optionsArr[0]] = $value;
                      }
                    }
                  }
                }              
            }
            if (isset($name)) {
              $this->setValues($name, $options, $headerLine, $arr, $patterns, $phones, $channels);
            }
            fclose($handle);
        } else {
        }
        unlink($this->uploadPath."/".$file);
      }
    }


    // var_dump($phones);
    // var_dump($channels);
    // return $patterns;
    foreach ($phones as &$p) {
      $this->setPatternsOptions($p, $patterns);
    }
    foreach ($channels as &$ch) {
      $this->setPatternsOptions($ch, $patterns);
    }
    $result = array_merge($phones, $channels);
    return $result;
    
    $stringResult = "";
    foreach ($result as $arr) {
      $stringResult .= "[".$arr["name"]."]\n";
      foreach ($arr["options"] as $optionKey => $optionValue) {
        $stringResult .= $optionKey."=".$optionValue."\n";
      }
    }
    return ($stringResult);
   }
 
 
  
  /**
   * save info to file
   * to do smth witch class or db
   */
  
  public function setPatternsOptions (&$arr, $patterns)
  {
    if ($arr["patterns"]) {
      foreach ($arr["patterns"] as $pattern) {
        $patternOptions = $this->getPattern($pattern, $patterns);
        foreach ($patternOptions as $optionKey => $optionValue) {
          $this->setUniquesPatternKey($optionKey, $arr);
          $arr["options"][$optionKey] = $optionValue;
        }
      }
    }
  }
  
  public function setUniquesPatternKey (&$optionKey, $p)
  {
    $i = 1;
    if(!$p["options"]) return;
    $originalKey = $optionKey;
    while (array_key_exists($originalKey , $p["options"])) {
      $originalKey .= $i;
      if (array_key_exists($originalKey, $p["options"])) {
        $originalKey = $optionKey;
      } else {
        $optionKey = $originalKey;
        return;
      }
      $i++;
    }
    if (array_key_exists($optionKey, $p["options"])) {
      $optionKey .= "1";
    }
  }
  
  public function isComment ($line)
  {
    if (is_string($line)) {
      $line = trim($line);
      if ($line[0] == ";") {
        return TRUE;
      }
    }
    return FALSE;
  }
  
  public function getName ($str)
  {
    $re = '/\[(.+)]/';
    preg_match($re, $str, $matches);
    if ($matches) {
      if (isset($matches[1])) {
        return $matches[1];
      }
      else if (isset($matches[0])) {
        return $matches[0];
      }
    }
    return $str;
  }
  
  public function isHavPattern ($str)
  {
    $re = '/\[(.+)]\((.+)\)/';
    preg_match($re, $str, $matches);
    if ($matches) {
      if (isset($matches[2])) {
        $bracket = strval($matches[2]);
        if (strlen($bracket) > 1) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }
  
  public function isDescriptionForPattern ($str)
  {
    $re = '/\[(.+)]\((.+)\)/';
    
    preg_match($re, $str, $matches);
    if ($matches) {
      if (isset($matches[2])) {
        $bracket = strval($matches[2]);
        if ($bracket[0] == "!") {
          return TRUE;
        }
      }
    }
    return FALSE;
  }
  
  public function getPatternsNames ($str)
  {
    $re = '/\[(.+)]\((.+)\)/';
    $result = [];
    preg_match($re, $str, $matches);
    if (isset($matches[2])) {
      $extends = strval($matches[2]);
      if ($extends[0] == "!") {
        $extends = str_replace("!", "", $extends);
      }
      $patterns = (explode(",", $extends));
      foreach ($patterns as $pattern) {
        if (strlen(trim($pattern))) {
          $result[] = trim($pattern);
        }
      }
    }
    return $result;
  }
  
  public function isPhone ($str)
  {
    if (is_numeric($str) || intval($str)) {
      return TRUE;
    }
    return FALSE;
  }
  
  public function getPattern ($name, $patterns)
  {
    foreach ($patterns as $pattern) {
      if ($pattern["name"] === $name) {
        return $pattern["options"];
      }
    }
    return [];
  }
  
  public function setValues (&$name, &$options, &$headerLine, &$arr, &$patterns, &$phones, &$channels)
  {
    $arr["name"] = $name;
    $arr["options"] = $options;
    if ($this->isHavPattern($headerLine)) {
      $arr['patterns'] = $this->getPatternsNames($headerLine);
    }
    if ($this->isDescriptionForPattern($headerLine)) {
      $patterns[] = $arr;
    }
    else {
      if ($this->isPhone($name)) {
        $arr["type"] = "phone";
        $phones[] = $arr;
      }
      else {
        $arr["type"] = "channel";
        $channels[] = $arr;
      }
    }
  }
}