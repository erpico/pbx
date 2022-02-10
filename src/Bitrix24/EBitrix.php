<?php


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

        $bitrix24_token = json_decode(gzdecode(base64_decode($request->getCookieParam('bitrix24_token'))), true);
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
    public function runInputCall($exten, $callerid, $type=2, $crmCreate=1, $lineNumber = "", $createTime = '') {
        $userInfo = $this->obB24App->call('user.get', ['FILTER' => ['UF_PHONE_INNER' => $exten, 'ACTIVE' => 'Y']]);
        $userId = $userInfo['result'][0]['ID'];

        $settings = new PBXSettings();
        $result = $settings->getDefaultSettingsByHandle('line.'.$lineNumber)['value'];
        if ($result) $userId = $result;

        $result = $this->obB24App->call('telephony.externalcall.register', [
            'USER_PHONE_INNER' => $exten,
            'USER_ID' => $userId,
            'PHONE_NUMBER' => $callerid,
            'TYPE' => $type,
            'CALL_START_DATE' => (new DateTime($createTime))->format('Y-m-d\TH:i:s+03:00'),
            'CRM_CREATE' => $crmCreate,
            'LINE_NUMBER' => $lineNumber,
            'SHOW' => 0
        ]);
        if (isset($result['result'])) {
            return $result['result']['CALL_ID'];
        } else {
            return false;
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
     * @return int internal user number
     */
    public function uploadRecordedFile($call_id, $recordedfile, $intNum, $duration, $disposition, $lineNumber){
        $userInfo = $this->obB24App->call('user.get', ['FILTER' => ['UF_PHONE_INNER' => $intNum, 'ACTIVE' => 'Y']]);
        $userId = $userInfo['result'][0]['ID'];

        $settings = new PBXSettings();
        $result = $settings->getDefaultSettingsByHandle('line.'.$lineNumber)['value'];
        if ($result) $userId = $result;

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

        $result = $this->obB24App->call('telephony.externalcall.finish', [
            'USER_PHONE_INNER' => $intNum,
            'USER_ID' => $userId,
            'CALL_ID' => $call_id, //идентификатор звонка из результатов вызова метода telephony.externalCall.register
            'STATUS_CODE' => $sipcode,
            'DURATION' => $duration, //длительность звонка в секундах
            'RECORD_URL' => $recordedfile //url на запись звонка для сохранения в Битрикс24
        ]);

        if ($result){
            return $result;
        } else {
            return false;
        }
    }



    public function logSync($crmCallUid, $src, $dst, $duration, $call_start_time, $reason, $result) {
        $sql = "INSERT INTO btx_call_sync SET sync_time = now()".
", u_id = '".$crmCallUid.
"', src = '".$src.
"', dst = '".$dst.
"', duration = '".$duration.
"', call_start_time = '".$call_start_time.
"', reason = '".$reason.
"', result = '".$result."'";

        $this->db->query($sql);
    }

    public function getSynchronizedCalls($u_id) {
        $sql = "SELECT id, sync_time, u_id, src, dst, duration, call_start_time, reason, result FROM btx_call_sync WHERE u_id = '".$u_id."'";
        $res = $this->db->query($sql);

        $calls = [];
        while ($row = $res->fetch()) {
            $calls[] = $row;
        }

        return $calls;
    }

    public function checkSynchronizedCall($crmCall) {
        $synchronizedCallsLog = $this->getSynchronizedCalls($crmCall['suid']);
        $synchronized = 0;
        if ($synchronizedCallsLog) {
            foreach ($synchronizedCallsLog as $synchronizedCall) {
                if (
                    $synchronizedCall['u_id'] == $crmCall['suid'] &&
                    $synchronizedCall['src'] == $crmCall['src'] &&
                    $synchronizedCall['dst'] == $crmCall['dst'] &&
                    $synchronizedCall['duration'] == $crmCall['talk'] &&
                    $synchronizedCall['call_start_time'] == $crmCall['time'] &&
                    $synchronizedCall['reason'] == $crmCall['reason']
                ) {
                    $synchronized = 1;
                }
            }
        }
        return $synchronized;
    }

    public function addCall($crmCall) {
        if (!is_numeric($crmCall['dst'])) $crmCall['dst'] = preg_replace('/[^0-9]/', '', $crmCall['dst']);
        if (!is_numeric($crmCall['src'])) $crmCall['src'] = preg_replace('/[^0-9]/', '', $crmCall['src']);
        if (mb_strlen($crmCall['dst']) == 11){
            $type = 1;
            $intnum = $crmCall['src'];
            $extnum = $crmCall['dst'];
        } else {
            $type = 2;
            $intnum = $crmCall['dst'];
            $extnum = $crmCall['src'];
        }
        $crm_create = 1;
        try {
            $callId = $this->runInputCall($intnum, $extnum, $type, $crm_create, $crmCall['userfield'], $crmCall['time']);
            $result = $this->uploadRecordedFile($callId, '/recording/' . $crmCall['uid'] . '.mp3', $intnum, $crmCall['talk'], $crmCall['reason'], $crmCall['userfield']);
            $this->logSync($crmCall['suid'], $crmCall['src'], $crmCall['dst'], $crmCall['talk'], $crmCall['time'], $crmCall['reason'],  json_encode($result));
            return $result;
        } catch (\Bitrix24\Exceptions\Bitrix24ApiException $e) {
            $e = '"'.$e.'"';
            $this->logSync($crmCall['suid'], 0, 0, 0, $crmCall['time'], 0, $e);
            return ['exception' => $e, 'uid' => $crmCall['suid']];
        }
    }
}