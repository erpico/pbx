<?php


use Bitrix24\Exceptions\Bitrix24EmptyResponseException;
use Bitrix24\Exceptions\Bitrix24Exception;
use Bitrix24\Exceptions\Bitrix24IoException;
use Bitrix24\Exceptions\Bitrix24SecurityException;
use Erpico\User;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class EBitrix {

    protected $obB24App;
    protected $db;
    protected $settings;
    protected $channel;

    public function __construct($request, $channel = 0)
    {
        $settings = new PBXSettings();
        $this->settings = $settings;
        $appId = $settings->getSettingByHandle('bitrix.app_id')['val'];
        $secret = $settings->getSettingByHandle('bitrix.secret')['val'];
        $domain = $settings->getSettingByHandle('bitrix.domain')['val'];

        if ($request) $bitrix24_token = json_decode(gzdecode(base64_decode($request->getCookieParam('bitrix24_token'))), true);
        if(!isset($bitrix24_token['member_id'])) $bitrix24_token['member_id'] = $settings->getSettingByHandle('bitrix24.member_id')['val'];
        if(!isset($bitrix24_token['access_token'])) $bitrix24_token['access_token'] = $settings->getSettingByHandle('bitrix24.access_token')['val'];
        if(!isset($bitrix24_token['refresh_token'])) $bitrix24_token['refresh_token'] = $settings->getSettingByHandle('bitrix24.refresh_token')['val'];

        // create a log channel
        $log = new Logger('bitrix24');
        $log->pushHandler(new StreamHandler(__DIR__."/../../logs/bitrix24.log", Logger::DEBUG));

        // init lib
        $this->obB24App = new \Bitrix24\Bitrix24(false, $log);
        $this->obB24App->setApplicationScope([ 'crm' ]);
        $this->obB24App->setApplicationId($appId);
        $this->obB24App->setApplicationSecret($secret);

        // set user-specific settings
        $this->obB24App->setDomain($domain);
        $this->obB24App->setMemberId($bitrix24_token['member_id']);
        $this->obB24App->setAccessToken($bitrix24_token['access_token']);
        $this->obB24App->setRefreshToken($bitrix24_token['refresh_token']);

        if ($this->obB24App->isAccessTokenExpire()) {
            $this->obB24App->setRedirectUri("https://".$_SERVER['HTTP_HOST']."/bitrix24/app");
            $newToken = $this->obB24App->getNewAccessToken();
            setcookie('bitrix24_token', base64_encode(gzencode(json_encode(['member_id' => $bitrix24_token['member_id'], 'access_token' => $newToken['access_token'], 'refresh_token' => $newToken['refresh_token']]))), 0, '/');
            $settings->setDefaultSettings(json_encode([['handle' => 'bitrix24.member_id', 'val' => $bitrix24_token['member_id']], ['handle' => 'bitrix24.access_token', 'val' => $newToken['access_token']], ['handle' => 'bitrix24.refresh_token', 'val' => $newToken['refresh_token']]]));
            $this->obB24App->setAccessToken($newToken['access_token']);
            $this->obB24App->setRefreshToken($newToken['refresh_token']);
        }

        global $app;
        $container = $app->getContainer();
        $this->db = $container['db'];
        $this->channel = $channel;
    }

    public function getobB24App() {
        return $this->obB24App;
    }

    /**
     * Run Bitrix24 REST API method telephony.externalcall.register.json
     *
     * @param int $exten (${EXTEN} from the Asterisk server, i.e. internal number)
     * @param int $callerid (${CALLERID(num)} from the Asterisk server, i.e. number which called us)
     *
     * @return array|false
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
    public function runInputCall($exten, $callerid, $type=2, $crmCreate=1, $lineNumber = "", $createTime = '', $channel = "", $crmCall = null) {
        $res = $this->getUserInnerIdByPhone($exten, $lineNumber, 'call/add');
        $userId = $res['userId'];
        $exten = $res['userPhone'];

        $createTime = $createTime == '' ? date("Y-m-d H:i:s") : date("Y-m-d H:i:s", strtotime($createTime));
        try {
            $result = $this->obB24App->call('telephony.externalcall.register', [
                'USER_PHONE_INNER' => $exten,
                'USER_ID' => $userId,
                'PHONE_NUMBER' => $callerid,
                'TYPE' => $type,
                'CALL_START_DATE' => $createTime,
                'CRM_CREATE' => $crmCreate,
                'LINE_NUMBER' => $lineNumber,
                'SHOW' => 0
            ]);
            if ($this->channel) {
                $this->logRequest(
                    $this->settings->getSettingByHandle('bitrix.api_url')['val']."telephony.externalcall.register",
                    json_encode([
                        'USER_PHONE_INNER' => $exten,
                        'USER_ID' => $userId,
                        'PHONE_NUMBER' => $callerid,
                        'TYPE' => $type,
                        'CALL_START_DATE' => $createTime,
                        'CRM_CREATE' => $crmCreate,
                        'LINE_NUMBER' => $lineNumber,
                        'SHOW' => 0
                    ]),
                    json_encode($result));
            }
            $this->logSync($channel, 1, $result['result']['CALL_ID'], json_encode($result));
            return $result['result']['CALL_ID'];
        } catch (\Bitrix24\Exceptions\Bitrix24ApiException $e) {
            $e = '"'.$e.'"';
            if ($channel == "" && $crmCall) {
                $channel = $crmCall['uid'];
            }
            $this->logSync($channel, 3, null, json_encode($e));
            if (!$crmCall) {
                return false;
            } else {
                return ['exception' => $e, 'uid' => $crmCall['uid']];
            }
        }
    }

    /**
     * Upload recorded file to Bitrix24.
     *
     * @param string $call_id
     * @param $recordedfile
     * @param string $intNum
     *
     * @param string $duration
     * @param $disposition
     * @param $lineNumber
     * @param string $channel
     * @param null $crmCall
     * @return array|int
     * @throws Bitrix24EmptyResponseException
     * @throws Bitrix24Exception
     * @throws Bitrix24IoException
     * @throws Bitrix24SecurityException
     */
    public function uploadRecordedFile($call_id, $recordedfile, $intNum, $duration, $disposition, $lineNumber, $channel = "", $crmCall = null){
        $res = $this->getUserInnerIdByPhone($intNum, $lineNumber, 'call/record');
        $userId = $res['userId'];
        $intNum = $res['userPhone'];

        $statusCode = $this->getStatusCodeByReason($disposition);
        $sipcode = $statusCode;
        if ($sipcode == 304 || $sipcode == 486) {
            $duration = 0;
        }

        try {
            $result = $this->obB24App->call('telephony.externalcall.finish', [
                'USER_PHONE_INNER' => $intNum,
                'USER_ID' => $userId,
                'CALL_ID' => $call_id, //идентификатор звонка из результатов вызова метода telephony.externalCall.register
                'STATUS_CODE' => $sipcode,
                'DURATION' => $duration, //длительность звонка в секундах
                'RECORD_URL' => $recordedfile //url на запись звонка для сохранения в Битрикс24
            ]);
            if ($this->channel) {
                $this->logRequest(
                    $this->settings->getSettingByHandle('bitrix.api_url')['val']."telephony.externalcall.finish",
                    json_encode([
                        'USER_PHONE_INNER' => $intNum,
                        'USER_ID' => $userId,
                        'CALL_ID' => $call_id, //идентификатор звонка из результатов вызова метода telephony.externalCall.register
                        'STATUS_CODE' => $sipcode,
                        'DURATION' => $duration, //длительность звонка в секундах
                        'RECORD_URL' => $recordedfile //url на запись звонка для сохранения в Битрикс24
                    ]),
                    json_encode($result));
            }
            $this->logSync($channel, 2, $call_id, json_encode($result));
            return $result;
        } catch (\Bitrix24\Exceptions\Bitrix24ApiException $e) {
            if (strpos ($e->getMessage(), "Call is not found")) {
                $cdr = new PBXCdr();
                $crmCalls = $cdr->getReportsByUid($channel, 1);
                foreach ($crmCalls as $crmCall) {
                    $helper = new EBitrix(0, $crmCall['uid']);
                    return $helper->addCall($crmCall);
                }
            }
            $e = '"'.$e.'"';
            if ($crmCall) {
                $channel = $crmCall['uid'];
            }
            $this->logSync($channel, 3, $call_id, json_encode($e));

            if ($channel && !$crmCall) {
                return false;
            } else {
                return ['exception' => $e, 'uid' => $crmCall['uid']];
            }
        }
    }

    public function logSync($crmCallUid, $status, $callId, $result) {
        $sql = "SELECT id FROM btx_call_sync WHERE u_id='$crmCallUid'";
        $res = $this->db->query($sql);
        $row = $res->fetch();

        $sql = $row ? "UPDATE" : "INSERT INTO";
        $sql .= " btx_call_sync SET sync_time = now()".
            ", u_id = '".$crmCallUid.
            "', status = '".$status.
            "', call_id = ".($callId ? "'$callId'" : "null").
            ", result = '".$result."'";
        if ($row) $sql .= " WHERE id=".$row['id'];

        $this->db->query($sql);
    }

    public function getSynchronizedCalls($u_id) {
        $sql = "SELECT id, status, call_id, result
                FROM btx_call_sync 
                WHERE u_id = '$u_id'";
        $res = $this->db->query($sql);

        return $res->fetch();
    }

    public function addCall($crmCall, $callId = 0) {
        if (!is_numeric($crmCall['dst'])) $crmCall['dst'] = preg_replace('/[^0-9]/', '', $crmCall['dst']);
        if (!is_numeric($crmCall['src'])) $crmCall['src'] = preg_replace('/[^0-9]/', '', $crmCall['src']);
        if (mb_strlen($crmCall['dst']) == 11) {
            $type = 1;
            $intnum = $crmCall['src'];
            $extnum = $crmCall['dst'];
        } else {
            $type = 2;
            $intnum = $crmCall['dst'];
            $extnum = $crmCall['src'];
        }
        if ($crmCall['userfield'] == "") {
            $settings = new PBXSettings();
            $crmCall['userfield'] = $settings->getDefaultSettingsByHandle('default_line')['value'];
        }
        if ($callId) {
            return $this->uploadRecordedFile($callId, '/recording/' . $crmCall['uid'] . '.mp3', $intnum, $crmCall['talk'], $crmCall['reason'], $crmCall['userfield'], $crmCall['uid'], $crmCall);
        } else {
            $callId = $this->runInputCall($intnum, $extnum, $type, 1, $crmCall['userfield'], $crmCall['time'], $crmCall['uid'], $crmCall);
            if (isset($callId['exception'])) {
                return $callId;
            } else {
                return $this->uploadRecordedFile($callId, '/recording/' . $crmCall['uid'] . '.mp3', $intnum, $crmCall['talk'], $crmCall['reason'], $crmCall['userfield'], $crmCall['uid'], $crmCall);
            }
        }
    }

    public function getStatusCodeByReason($reason) {
        switch ($reason) {
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
                break;
            case 'BUSY':
                $sipcode =  486; //  занято
                break;
            default:
                if(empty($reason)) $sipcode = 304; //если пустой пришел, то поставим неотвечено
                else $sipcode = 603; // отклонено, когда все остальное
                break;
        }

        return $sipcode;
    }

    public function getUserInnerIdByPhone($exten, $lineNumber = "", $type, $disposition = null) {
        $userFromBtx = null;
        if ($exten) {
            $result = $this->obB24App->call('user.get', ['FILTER' => ['UF_PHONE_INNER' => $exten, 'ACTIVE' => 'Y']]);
            if (isset($result['result'][0]['ID'])) $userFromBtx = $result['result'][0]['ID'];
        }

        $settings = new PBXSettings();
        $result = $settings->getDefaultSettingsByHandle($lineNumber)['value'];
        $user = new User();
        if ($result) {
            $result = $user->getNameById($result);
            if ($result && ctype_digit($result)) $extenLine = $result;
            $userInfo = $this->obB24App->call('user.get', ['FILTER' => ['UF_PHONE_INNER' => $extenLine, 'ACTIVE' => 'Y']]);
            $userFromLine = $userInfo['result'][0]['ID'];
        }

        $result = $settings->getDefaultSettingsByHandle('default_user')['value'];
        $result = $user->getNameById($result);
        if ($result) $extenDef = $result;
        $userInfo = $this->obB24App->call('user.get', ['FILTER' => ['UF_PHONE_INNER' => $extenDef, 'ACTIVE' => 'Y']]);
        $defaultUser = $userInfo['result'][0]['ID'];

        if ($type == 'call/add') {
            if ($userFromLine) {
                return ['userPhone' => $extenLine,'userId' => $userFromLine];
            } else if ($defaultUser) {
                return ['userPhone' => $extenDef, 'userId' => $defaultUser];
            } else {
                return ['userPhone' => $exten, 'userId' => $userFromBtx];
            }
        } elseif ($type == 'call/record') {
            if ($userFromBtx) {
                if ($disposition == 'ABANDON') {
                    if ($userFromLine) {
                        return ['userPhone' => $extenLine, 'userId' => $userFromLine];
                    } else {
                        return ['userPhone' => $extenDef, 'userId' => $defaultUser];
                    }
                }
                return ['userPhone' => $exten, 'userId' => $userFromBtx];
            } else {
                if ($userFromLine) {
                    return ['userPhone' => $extenLine, 'userId' => $userFromLine];
                } else {
                    return ['userPhone' => $extenDef, 'userId' => $defaultUser];
                }
            }
        }
    }

    public function getUsers() {
        $obB24User = new \Bitrix24\User\User($this->obB24App);
        $users = [];
        $i = 0;
        while (1) {
            $busers = $obB24User->get('NAME', 'ASC', ['ACTIVE' => 'Y'], $i);
            if (!count($busers['result'])) break;
            foreach ($busers['result'] as $u) {
                $users[] = [
                    "id"   => $u['ID'],
                    "name" => trim($u['NAME']),
                    "last_name" => trim($u['LAST_NAME']),
                    "phone" => $u['UF_PHONE_INNER']
                ];
            }
            if (!isset($busers['next'])) {
                break;
            }
            $i = $busers['next'];
        }
        if ($users) {
            return $users;
        }
    }

    public function getStatuses() {
      $result = $this->obB24App->call('crm.status.list', [
        'FILTER' => ['ENTITY_ID' => 'STATUS'],
      ]);
      if ($result['result']) {
        return $result['result'];
      }
    }

    public function getLeadStatus($leadId) {
        $result = $this->obB24App->call('crm.lead.get', ['ID' => $leadId]);

        if ($result['result']) {
            $lead = $result['result'];

            $statuses = $this->getStatuses();
            $foundedStatus = null;
            foreach ($statuses as $status) {
                if ($status['STATUS_ID'] == $lead['STATUS_ID']) {
                    $foundedStatus = $status;
                    break;
                }
            }

            return ['lead_id' => $lead['ID'], 'status_id' => $foundedStatus['ID'], 'status' => $foundedStatus['NAME']];
        }
    }

    public function updateLeadState($leadId, $state, $lead_status_user = 0)
    {
      try {
        $result = $this->obB24App->call('crm.lead.update', ['ID' => $leadId, 'FIELDS' => ['STATUS_ID' => $state]]);
        $this->logRequest(
            $this->settings->getSettingByHandle('bitrix.api_url')['val']."crm.lead.update",
            json_encode(['ID' => $leadId, 'FIELDS' => ['STATUS_ID' => $state]]),
            json_encode($result)
        );
        return $result['result'];
      } catch (Bitrix24\Exceptions\Bitrix24ApiException $e) {
        return $e->getMessage();
      }
    }

    public function logRequest($url, $query, $response) {
        $sql = "INSERT INTO bitrix24_requests SET datetime = Now(), channel = :channel, url = :url, query = :query, response = :response";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam('channel', $this->channel);
        $stmt->bindParam('url', $url);
        $stmt->bindParam('query', $query);
        $stmt->bindParam('response', $response);
        return $stmt->execute()?true:false;
    }
}