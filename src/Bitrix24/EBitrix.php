<?php


use Erpico\User;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class EBitrix {

    protected $obB24App;
    protected $db;

    public function __construct($request)
    {
        $settings = new PBXSettings();
        $appId = $settings->getSettingByHandle('bitrix.app_id')['val'];
        $secret = $settings->getSettingByHandle('bitrix.secret')['val'];
        $domain = $settings->getSettingByHandle('bitrix.domain')['val'];

        if ($request) $bitrix24_token = json_decode(gzdecode(base64_decode($request->getCookieParam('bitrix24_token'))), true);
        if(!isset($bitrix24_token['member_id'])) $bitrix24_token['member_id'] = $settings->getSettingByHandle('bitrix24.member_id')['val'];
        if(!isset($bitrix24_token['access_token'])) $bitrix24_token['access_token'] = $settings->getSettingByHandle('bitrix24.access_token')['val'];
        if(!isset($bitrix24_token['refresh_token'])) $bitrix24_token['refresh_token'] = $settings->getSettingByHandle('bitrix24.refresh_token')['val'];

        // create a log channel
        $log = new Logger('bitrix24');
        $log->pushHandler(new StreamHandler(__DIR__."/../logs/bitrix24.log", Logger::DEBUG));

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
        $userId = $this->getUserInnerIdByPhone($exten, $lineNumber, 'call/add');

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
            return $result['result']['CALL_ID'];
        } catch (\Bitrix24\Exceptions\Bitrix24ApiException $e) {
            $e = '"'.$e.'"';
            if ($channel == "" && $crmCall) {
                $channel = $crmCall['uid'];
            }
            $this->logSync($channel, 0, 0, 0, $e);
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
     * @param string $recordingfile
     * @param string $duration
     * @param string $intNum
     *
     * @return array|int
     */
    public function uploadRecordedFile($call_id, $recordedfile, $intNum, $duration, $disposition, $lineNumber, $channel = "", $crmCall = null){
        $userId = $this->getUserInnerIdByPhone($intNum, $lineNumber, 'call/record');
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
            return $result;
        } catch (\Bitrix24\Exceptions\Bitrix24ApiException $e) {
            $e = '"'.$e.'"';
            if ($crmCall) {
                $channel = $crmCall['uid'];
            }
            $this->logSync($channel, 0, 0, 0, $e);

            if ($channel && !$crmCall) {
                return false;
            } else {
                return ['exception' => $e, 'uid' => $crmCall['uid']];
            }
        }
    }



    public function logSync($crmCallUid, $duration, $userId, $status_code, $result) {
        if (!$duration || $duration == '') $duration = 0;
        if (!$userId || $userId == '') $userId = 0;
        if (!$status_code || $status_code == '') $status_code = 0;

        $sql = "INSERT INTO btx_call_sync SET sync_time = now()".
", u_id = '".$crmCallUid.
"', duration = '".$duration.
"', int_number = '".$userId.
"', status_code = '".$status_code.
"', result = '".$result."'";
        $this->db->query($sql);
    }

    public function getSynchronizedCalls($u_id, $duration, $int_number, $status_code) {
        $sql = "SELECT id, sync_time, u_id, duration, int_number, status_code, result 
                FROM btx_call_sync 
                WHERE u_id = '".$u_id."' 
                AND duration = '".$duration."' 
                AND int_number = '".$int_number."' 
                AND status_code = '".$status_code."'";
        $res = $this->db->query($sql);

        return $res->fetch();
    }

    public function addCall($crmCall) {
        if (mb_strlen($crmCall['dst']) == 11) {
            $type = 1;
            $intnum = $crmCall['src'];
            $extnum = $crmCall['dst'];
        } else {
            $type = 2;
            $intnum = $crmCall['dst'];
            $extnum = $crmCall['src'];
        }
        $crm_create = 1;
        if ($crmCall['userfield'] == "") {
          $settings = new PBXSettings();
          $crmCall['userfield'] = $settings->getDefaultSettingsByHandle('default_line')['value'];
        }
        $callId = $this->runInputCall($intnum, $extnum, $type, $crm_create, $crmCall['userfield'], $crmCall['time'], "", $crmCall);
        if (isset($callId['exception'])) {
            return $callId;
        } else {
            $result = $this->uploadRecordedFile($callId, '/recording/' . $crmCall['uid'] . '.mp3', $intnum, $crmCall['talk'], $crmCall['reason'], $crmCall['userfield'], "", $crmCall);
            $this->logSync($crmCall['uid'], $crmCall['talk'], $intnum, $crmCall['status_code'], json_encode($result));
            return $result;
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

    public function getUserInnerIdByPhone($exten, $lineNumber = "", $type) {
        $userFromBtx = null;
        if ($exten) {
            $result = $this->obB24App->call('user.get', ['FILTER' => ['UF_PHONE_INNER' => $exten, 'ACTIVE' => 'Y']]);
            if (isset($result['result'][0]['ID'])) $userFromBtx = $result['result'][0]['ID'];
        }
        $userFromLine = null;
        if ($lineNumber != "") {
            $settings = new PBXSettings();
            $result = $settings->getDefaultSettingsByHandle($lineNumber)['value'];
            if ($result) {
                $user = new User();
                $result = $user->getNameById($result);
                if ($result && ctype_digit($result)) $exten = $result;
                $userInfo = $this->obB24App->call('user.get', ['FILTER' => ['UF_PHONE_INNER' => $exten, 'ACTIVE' => 'Y']]);
                $userFromLine = $userInfo['result'][0]['ID'];
            } else {
                $result = $settings->getDefaultSettingsByHandle('default_user')['value'];
                if ($result) $exten = $result;
                $userInfo = $this->obB24App->call('user.get', ['FILTER' => ['UF_PHONE_INNER' => $exten, 'ACTIVE' => 'Y']]);
                $userFromLine = $userInfo['result'][0]['ID'];
            }
        }

        if ($type == 'call/add') {
            if ($userFromLine) {
                return $userFromLine;
            } else {
                return $userFromBtx;
            }
        } elseif ($type == 'call/record') {
            if ($userFromBtx) {
                return $userFromBtx;
            } else {
                return $userFromLine;
            }
        }
    }
}