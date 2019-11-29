<?php
  /**
   * Created by PhpStorm.
   * User: Drs10
   * Date: 29.10.2019
   * Time: 0:43
   */
  
  namespace Erpico;
  
  /**
   * Translator class is a sample class for translate sentence from russian to english lang
   *
   */
  class Translator
  {
    private $from;
    private $to;
    private $value;
    private $result = "";
  
    /**
     * Set necessary variables
     * @param string
     * @param array
     * @param array
     */
    public function __construct ($value = null, $from = null, $to = null)
    {
      if (isset($value) && is_string($value)) {
        $this->value = $value;
      }
      if (isset($from) && is_array($from)) {
        $this->from = $from;
      }
      else {
        $this->from = ['А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я', 'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я', ' '];
      }
      if (isset($to) && is_array($from)) {
        $this->to = $to;
      }
      else {
        $this->to = ['a', 'b', 'v', 'g', 'd', 'e', 'e', 'gh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'sch', 'y', 'y', 'y', 'e', 'yu', 'ya', 'a', 'b', 'v', 'g', 'd', 'e', 'e', 'gh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'sch', 'y', 'y', 'y', 'e', 'yu', 'ya', ' '];
      }
    }
  
    /**
     * Translate value from "from" alphabet to "to" alphabet
     * @return string
     */
    public function translate ()
    {
      $value = $this->getValue();
      if (!isset($value) || !strlen(trim($value))) {
        return $this->getResult();
      }
      $this->setResult(str_replace($this->getFrom(), $this->getTo(), $value));
      $this->setResult(str_replace('-', '', $this->getResult()));
      //TO DO: create reg exp for special symbols;
      $this->setResult(preg_replace('/[^\w+]/', '', $this->getResult()));
      return $this->getResult();
    }
    
    /**
     * @return string
     */
    public function getValue ()
    {
      return $this->value;
    }
    
    /**
     * @return Translator
     * @param string
     */
    public function setValue ($value)
    {
      $this->value = $value;
      return $this;
    }
    
    /**
     * @return string
     */
    public function getResult ()
    {
      return $this->result;
    }
    
    /**
     * @param mixed $result
     */
    public function setResult ($result)
    {
      $this->result = $result;
    }
    
    /**
     * @return array
     */
    public function getFrom ()
    {
      return $this->from;
    }
    
    /**
     * @return array
     */
    public function getTo ()
    {
      return $this->to;
    }
  }