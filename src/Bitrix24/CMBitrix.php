<?php
/**
* Helpers class for working with API  
* @author Автор: ViStep.RU
* @version 1.0
* @copyright: ViStep.RU (admin@vistep.ru)
**/

class CMBitrix {

	protected $db;
    protected $ami;
	protected $channel = "";
    protected $server_host;
    protected $logger;
    protected $user;
    protected $utils;
    protected $settings;
    protected $campaign_id = 0;

    public function __construct($channel)
    {
		global $app;
		$container = $app->getContainer();
		$this->db = $container['db'];
		$this->ami = $container['ami'];
		$this->server_host = $container['server_host'];
		$this->logger = $container['logger'];
		$this->user = $container['auth'];//new Erpico\User($this->db);
		$this->utils = new Erpico\Utils();
		$this->channel = $channel;
		$this->settings = new PBXSettings();
    }

    public function setCampaignId($campaignId) {
        $this->campaign_id = $campaignId;
    }

	/**
	 * Get Internal number by using USER_ID.
	 *
	 * @param int $userid
	 *
	 * @return int internal user number
	 */
	public function getIntNumByUSER_ID($userid){ 
	    $result = $this->getBitrixApi(array("ID" => $userid), 'user.get');
	    if ($result){
	        return $result['result'][0]['UF_PHONE_INNER'];
	    } else {
	        return false;
	    }
    
	}

	/**
	 * Get USER_ID by Internal number.
	 *
	 * @param int $intNum
	 *
	 * @return int user id
	 */
	public function getUSER_IDByIntNum($intNum){ 
	    $result = $this->getBitrixApi(array('FILTER' => array ('UF_PHONE_INNER' => $intNum, 'ACTIVE' => 'Y'),), 'user.get');
	    if ($result){
	        return $result['result'][0]['ID'];
	    } else {
	        return false;
	    }
    
	}

  public function getLeadsByFilters($filters, $next, $cron = 0) {
    $leads = [];
    $newFilters = [];
    $filters = is_string($filters) ? json_decode($filters, true) : $filters;
      foreach ($filters as $k => $v) {
          if (str_contains($v, '||')) {
              $values = explode('||', $v);
              foreach ($values as $index => $value) {
                  $newFilters[$k][$index] = $value;
              }
          } else {
              $newFilters[$k] = $v;
          }
      }

      if ($cron) {
          $date = new DateTime(date('Y-m-d H:i:s', strtotime('-60 minutes')));
          $date->setTimezone(new DateTimeZone('Europe/Moscow'));
          $date = $date->format('Y-m-d H:i:s');

          $newFilters['>DATE_MODIFY'] = "$date";
      }

    $result = $this->getBitrixApi([
        'ORDER' => ["DATE_CREATE" => "DESC"],
        'FILTER' => $newFilters,
        'SELECT' => array('ID', 'CONTACT_ID', 'NAME', 'SECOND_NAME', 'LAST_NAME'),
        'start' => $next
    ], 'crm.lead.list');
    if (isset($result['res']) && $result['res'] == false) return $result;
    if (!count($result['result'])) return false;
    foreach ($result['result'] as $lead) {
        $leadInfo = $this->getBitrixApi(['ID' => $lead['ID']], 'crm.lead.get');
        $phone = $leadInfo['result']['PHONE'][0]['VALUE'];

        $fio = '';
        if (!isset($lead['CONTACT_ID'])) {
            if (isset($lead['LAST_NAME'])) $fio .= $lead['LAST_NAME']." ";
            if (isset($lead['NAME'])) $fio .= $lead['NAME']." ";
            if (isset($lead['SECOND_NAME'])) $fio .= $lead['SECOND_NAME'];
        } else {
            $userInfo = $this->getBitrixApi(array("ID" => $lead['CONTACT_ID']), 'crm.contact.get');
            if (isset($userInfo['result']['LAST_NAME'])) $fio .= $userInfo['result']['LAST_NAME'] . " ";
            if (isset($userInfo['result']['NAME'])) $fio .= $userInfo['result']['NAME'] . " ";
            if (isset($userInfo['result']['SECOND_NAME'])) $fio .= $userInfo['result']['SECOND_NAME'];
        }

      array_push($leads, ['ID' => $lead['ID'], 'PHONE' => $phone, 'FIO' => trim($fio)]);
    }
    $result['result'] = $leads;

    return $result;
  }
    /**
     * Upload recorded file to Bitrix24.
     *
     * @param string $call_id
     * @param string $recordingfile
     * @param string $duration
     * @param string $intNum
     *
     * @return int internal user number
     */
    public function uploadRecordedFile($call_id,$recordedfile,$intNum,$duration,$disposition){
        switch ($disposition) {
            case 'ANSWERED':
            case 'COMPLETECALLER':
            case 'COMPLETEAGENT':
            case 'TRANSFER':
                $sipcode = 200; // успешный звонок
                break;
            case 'NO ANSWER':
            case 'ABANDON':
            case 'EXITEMPTY':
            case 'RINGNOANSWER':
            case 'EXITWITHTIMEOUT':
                $sipcode = 304; // нет ответа
                $duration = 0; // Set duration to zero for missed calls
                break;
            case 'BUSY':
                $sipcode =  486; //  занято
                $duration = 0; // Set duration to zero for missed calls
            default:
                if(empty($disposition)) $sipcode = 304; //если пустой пришел, то поставим неотвечено
                else $sipcode = 603; // отклонено, когда все остальное
                break;
        }

        $result = $this->getBitrixApi(array(
            'USER_PHONE_INNER' => $intNum,
            'USER_ID' => $this->getUSER_IDByIntNum($intNum),
            'CALL_ID' => $call_id, //идентификатор звонка из результатов вызова метода telephony.externalCall.register
            'STATUS_CODE' => $sipcode,
            //'CALL_START_DATE' => date("Y-m-d H:i:s"),
            'DURATION' => $duration, //длительность звонка в секундах
            'RECORD_URL' => $recordedfile //url на запись звонка для сохранения в Битрикс24
        ), 'telephony.externalcall.finish');

        if ($result){
            return $result;
        } else {
            return false;
        }

    }

    /**
     * Run Bitrix24 REST API method telephony.externalcall.register.json
     *
     * @param int $exten (${EXTEN} from the Asterisk server, i.e. internal number)
     * @param int $callerid (${CALLERID(num)} from the Asterisk server, i.e. number which called us)
     *
     * @return array  like this:
     * Array
     *	(
     *	    [result] => Array
     *	        (
     *	            [CALL_ID] => externalCall.cf1649fa0f4479870b76a0686f4a7058.1513888745
     *	            [CRM_CREATED_LEAD] =>
     *	            [CRM_ENTITY_TYPE] => LEAD
     *	            [CRM_ENTITY_ID] => 24
     *	        )
     *	)
     * We need only CALL_ID
     */
    public function runInputCall($exten, $callerid, $type=2, $crmCreate=1, $lineNumber = "") {
        $userId = $this->getUSER_IDByIntNum($exten);
        switch ($lineNumber) {
            case '0110747':
            case '0111535':
            case '4499999':
                $userId = 812;
                break;
            case '0110748':
            case '0111712':
            case '74957750440':
                $userId = 4121;
                break;
        }
        $result = $this->getBitrixApi(array(
            'USER_PHONE_INNER' => $exten,
            'USER_ID' => $userId,
            'PHONE_NUMBER' => $callerid,
            'TYPE' => $type,
            'CALL_START_DATE' => date("Y-m-d H:i:s"),
            'CRM_CREATE' => $crmCreate,
            'LINE_NUMBER' => $lineNumber,
            'SHOW' => 0,
        ), 'telephony.externalcall.register');

        if ($result){
            return $result['result']['CALL_ID'];
        } else {
            return false;
        }

    }

  /**
	 * Run Bitrix24 REST API method user.get.json return only online users array
	 *
	 *
	 * @return array  like this:
	 *	Array
	 *	(
	 *	    [result] => Array
	 *	        (
	 *	            [0] => Array
	 *	                (
	 *	                    [ID] => 1
	 *	                    [ACTIVE] => 1
	 *	                    [EMAIL] => admin@your-admin.pro
	 *	                    [NAME] => 
	 *	                    [LAST_NAME] => 
	 *	                    [SECOND_NAME] => 
	 *	                    [PERSONAL_GENDER] => 
	 *	                    [PERSONAL_PROFESSION] => 
	 *	                    [PERSONAL_WWW] => 
	 *	                    [PERSONAL_BIRTHDAY] => 
	 *	                    [PERSONAL_PHOTO] => 
	 *	                    [PERSONAL_ICQ] => 
	 *	                    [PERSONAL_PHONE] => 
	 *	                    [PERSONAL_FAX] => 
	 *	                    [PERSONAL_MOBILE] => 
	 *	                    [PERSONAL_PAGER] => 
	 *	                    [PERSONAL_STREET] => 
	 *	                    [PERSONAL_CITY] => 
	 *	                    [PERSONAL_STATE] => 
	 *	                    [PERSONAL_ZIP] => 
	 *	                    [PERSONAL_COUNTRY] => 
	 *	                    [WORK_COMPANY] => 
	 *	                    [WORK_POSITION] => 
	 *	                    [WORK_PHONE] => 
	 *	                    [UF_DEPARTMENT] => Array
	 *	                        (
	 *	                            [0] => 1
	 *	                        )
     *
	 *	                    [UF_INTERESTS] => 
	 *	                    [UF_SKILLS] => 
	 *	                    [UF_WEB_SITES] => 
	 *	                    [UF_XING] => 
	 *	                    [UF_LINKEDIN] => 
	 *	                    [UF_FACEBOOK] => 
	 *	                    [UF_TWITTER] => 
	 *	                    [UF_SKYPE] => 
	 *	                    [UF_DISTRICT] => 
	 *	                    [UF_PHONE_INNER] => 555
	 *	                )
 	 *
	 *		        )
     *
	 *	    [total] => 1
	 *	)
	 */
	public function getUsersOnline(){
	    $result = $this->getBitrixApi(array(
			'FILTER' => array ('IS_ONLINE' => 'Y',),
			), 'user.get');

	    if ($result){
	    	if (isset($result['total']) && $result['total']>0) 
	    		return $result['result'];
	    	else return false;
	    } else {
	        return false;
	    }
    
	}

	/**
	 * Get CRM contact name by phone
	 *
	 * @param string $phone
	 *
	 * @return string or extNum on fail 
	 */
	public function getCrmContactNameByExtNum($extNum){
		$result = $this->getBitrixApi(array(
						'FILTER' => array ('PHONE' => $extNum,),
						'SELECT' => array ('NAME', 'LAST_NAME',),
					), 'crm.contact.list');
		$FullName = $extNum;
		if ($result) {
			if (isset($result['total']) && $result['total']>0) $FullName = $this->translit($result['result'][0]['NAME'].'_'.$result['result'][0]['LAST_NAME']);
		}
		return $FullName;
	}

	/**
	 * Show input call data for online users
	 *
	 * @param string $call_id
	 *
	 * @return bool 
	 */
	public function showInputCallForOnline($call_id){
		$online_users = $this->getUsersOnline();
		if ($online_users){
			foreach ($online_users as $user) {
				$result = $this->getBitrixApi(array(
					'CALL_ID' => $call_id,
					'USER_ID' => $user['ID'],
					), 'telephony.externalcall.show');
			}
			return true;
		} else 
			return false;
	}

	/**
	 * Show input call data for user with internal number
	 *
	 * @param int $intNum (user internal number)
	 * @param int $call_id 
	 *
	 * @return bool 
	 */
	public function showInputCall($intNum, $call_id){
		$user_id = $this->getUSER_IDByIntNum($intNum);
		if ($user_id){
			$result = $this->getBitrixApi(array(
						'CALL_ID' => $call_id,
						'USER_ID' => $user_id,
						), 'telephony.externalcall.show');
			return $result;
		} else 
			return false;
	}

	/**
	 * Hide input call data for all except user with internal number.
	 *
	 * @param int $intNum (user internal number)
	 * @param int $call_id 
	 *
	 * @return bool 
	 */
	public function hideInputCallExcept($intNum, $call_id){
		$user_id = $this->getUSER_IDByIntNum($intNum);
		$online_users = $this->getUsersOnline();
		if (($user_id) && ($online_users)){
			foreach ($online_users as $user) {
				if ($user['ID']!=$user_id){
					$result = $this->getBitrixApi(array(
						'CALL_ID' => $call_id,
						'USER_ID' => $user['ID'],
						), 'telephony.externalcall.hide');
				}
			}
			return true;
		} else 
			return false;
	}

	/**
	 * Hide input call data for user with internal number
	 *
	 * @param int $intNum (user internal number)
	 * @param int $call_id 
	 *
	 * @return bool 
	 */
	public function hideInputCall($intNum, $call_id){
		$user_id = $this->getUSER_IDByIntNum($intNum);
		if ($user_id){
			$result = $this->getBitrixApi(array(
						'CALL_ID' => $call_id,
						'USER_ID' => $user_id,
						), 'telephony.externalcall.hide');
			return $result;
		} else 
			return false;
	}

	/**
	 * Check string for json data.
	 *
	 * @param string $string
	 *
	 * @return bool 
	 */
	public function isJson($string) {
	    json_decode($string);
	    return (json_last_error() == JSON_ERROR_NONE);
	}

	/**
	 * Api requests to Bitrix24 
	 *
	 * @param array $data
	 * @param string $method
	 * @param string $url
	 *
	 * @return array or false 
	 */

	public function getBitrixApi($data, $method){
		$url = $this->settings->getSettingByHandle('bitrix.api_url')['val'];
		if (!$url) return ['res' => false, 'message' => 'Отсутсвует URL входящего вебхука Битрикс24'];
	    $queryUrl = $url.$method.'.json';
	    $queryData = http_build_query($data);
	    $curl = curl_init();
	    curl_setopt_array($curl, array(
	    CURLOPT_SSL_VERIFYPEER => 0,
	    CURLOPT_POST => 1,
	    CURLOPT_HEADER => 0,
	    CURLOPT_RETURNTRANSFER => 1,
	    CURLOPT_URL => $queryUrl,
	    CURLOPT_POSTFIELDS => $queryData,
	        ));
	    $result = curl_exec($curl);
	    curl_close($curl);
      	$this->logRequest($queryUrl,$queryData, $result);
	    if ($this->isJson($result)){
	        $result = json_decode($result, true);
	        return $result;
	    } else {
	        return false;
	    }
	}

	/**
	 * Remove item from array.
	 *
	 * @param array $data
	 * @param mixed $needle
	 *
	 * @return array
	 */
	public function removeItemFromArray(&$data,$needle,$what) {

		if($what === 'value') {
			if (($key = array_search($needle, $data)) !== false) {
       	 		unset($data[$key]);
       		}
    	}

    	elseif($what === 'key') {
    		if (array_key_exists($needle, $data)) {
       	 		unset($data[$needle]);
       		}
       	}

        //return $data;
	}

	/**
	 * Translit string.
	 *
	 * @param string $string
	 *
	 * @return string
	 */
  	public function translit($string) {
	    $converter = array(
	        'а' => 'a',   'б' => 'b',   'в' => 'v',
	        'г' => 'g',   'д' => 'd',   'е' => 'e',
	        'ё' => 'e',   'ж' => 'zh',  'з' => 'z',
	        'и' => 'i',   'й' => 'y',   'к' => 'k',
	        'л' => 'l',   'м' => 'm',   'н' => 'n',
	        'о' => 'o',   'п' => 'p',   'р' => 'r',
	        'с' => 's',   'т' => 't',   'у' => 'u',
	        'ф' => 'f',   'х' => 'h',   'ц' => 'c',
	        'ч' => 'ch',  'ш' => 'sh',  'щ' => 'sch',
	        'ь' => '\'',  'ы' => 'y',   'ъ' => '\'',
	        'э' => 'e',   'ю' => 'yu',  'я' => 'ya',
	        
	        'А' => 'A',   'Б' => 'B',   'В' => 'V',
	        'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
	        'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z',
	        'И' => 'I',   'Й' => 'Y',   'К' => 'K',
	        'Л' => 'L',   'М' => 'M',   'Н' => 'N',
	        'О' => 'O',   'П' => 'P',   'Р' => 'R',
	        'С' => 'S',   'Т' => 'T',   'У' => 'U',
	        'Ф' => 'F',   'Х' => 'H',   'Ц' => 'C',
	        'Ч' => 'Ch',  'Ш' => 'Sh',  'Щ' => 'Sch',
	        'Ь' => '\'',  'Ы' => 'Y',   'Ъ' => '\'',
	        'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya',
	    );
	    return strtr($string, $converter);
  	}

	public function logRequest($url, $query, $response) {
		$sql = "INSERT INTO bitrix24_requests 
                SET datetime = Now(), 
                    channel = :channel, 
                    url = :url, 
                    query = :query, 
                    response = :response,
                    campaign_id = :campaign_id";
		$stmt = $this->db->prepare($sql);
		$stmt->bindParam('channel', $this->channel);
		$stmt->bindParam('url', $url);
		$stmt->bindParam('query', $query);
		$stmt->bindParam('response', $response);
		$stmt->bindParam('campaign_id', $this->campaign_id);
		return $stmt->execute()?true:false;
	}
}
