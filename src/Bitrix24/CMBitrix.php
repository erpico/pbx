<?php

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

  public function getLeadById(int $id) {
    return $this->getBitrixApi(['ID' => $id], 'crm.lead.get');
  }

  public function getContactById(int $id) {
    return $this->getBitrixApi(['ID' => $id], 'crm.contact.get');
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
        $leadInfo = $this->getLeadById($lead['ID']);
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
