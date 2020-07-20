<?php
/**
 * Created by PhpStorm.
 * User: Drs10
 * Date: 24.06.2020
 * Time: 23:12
 */

namespace App\Services;

use Slim\Http\Request;

/**
 * Class RequestTypeService
 * @package App\Services
 */
class RequestTypeService
{
  const API_TYPE = 'API';
  const BROWSER_TYPE = 'BROWSER';
  
  /**
   * @param Request $request
   *
   * @return string
   */
  public function getType(Request $request): string
  {
    $acceptHeader = $request->getHeaderLine('HTTP_ACCEPT');
    
    return strpos($acceptHeader, 'text/html') !== false ? self::BROWSER_TYPE : self::API_TYPE;
  }
}