<?php

use AmoCRM\OAuth2\Client\Provider\AmoCRM;

class EAmoCRM {
    
    private $db;
    public $settings;
    private $provider;
    public $accessToken;
    public $refreshToken;
    public $expire;
    public $domain;

    public function __construct()
    {
        global $app;
        $container = $app->getContainer();
        $conf = $container['dbConfig'];
        $this->db = $container['db'];
        $this->settings = new PBXSettings();
        $this->provider = new AmoCRM([
            'clientId' => $conf['amo_client_id'],
            'clientSecret' => $conf['amo_secret'],
            'redirectUri' => $conf['amo_redirect_uri'],
        ]);
        $this->domain = $this->settings->getSettingByHandle('amocrm.domain')['val'];
        $this->accessToken = $this->settings->getSettingByHandle('amocrm.access_token')['val'];
        $this->refreshToken = $this->settings->getSettingByHandle('amocrm.refresh_token')['val'];
        $this->expire = $this->settings->getSettingByHandle('amocrm.expire')['val'];
    }

    public function getOAuthScript($client_id, $instance) {
        return '<script
            class="amocrm_oauth"
            charset="utf-8"
            data-client-id="' . $client_id . '"
            data-title="Установить интеграцию"
            data-compact="false"
            data-class-name="className"
            data-color="default"
            data-state="' . $instance . '"
            data-error-callback="handleOauthError"
            data-mode="post_message"
            src="https://www.amocrm.ru/auth/button.min.js"
        ></script>';
    }

    public function getOAuthScriptError() {
        return '<script>
            handleOauthError = function(event) {
                alert(\'ID клиента - \' + event.client_id + \' Ошибка - \' + event.error);
            }
        </script>';
    }

    public function getProvider() {
        return $this->provider;
    }

    public function saveDeafaultSettings($accessToken)
    {
        if (!$this->getAccessToken || $this->provider->getBaseDomain()) return

        $this->settings->setDefaultSettings(json_encode([
            ['handle' => 'amocrm.domain', 'val' => $this->provider->getBaseDomain()], 
            ['handle' => 'amocrm.access_token', 'val' => $accessToken->getToken()], 
            ['handle' => 'amocrm.refresh_token', 'val' => $accessToken->getRefreshToken()],
            ['handle' => 'amocrm.expire', 'val' => $accessToken->getExpires()]
        ]));
    }

    public function getUsers() {
      $data = $this->provider->getHttpClient()
      ->request('GET', $this->provider->urlAccount() . 'api/v4/users', [
          'headers' => $this->provider->getHeaders($this->accessToken)
      ]);
      $ar = json_decode($data->getBody()->getContents(), 1);
      if (is_array($ar['_embedded']) && is_array($ar['_embedded']['users'])) {
        return $ar['_embedded']['users'];
      }
      return [];
    }

    public function getContact(string $phone) {
        $data = $this->provider->getHttpClient()
        ->request('GET', $this->provider->urlAccount() . 'api/v4/contacts?query='.$phone, [
            'headers' => $this->provider->getHeaders($this->accessToken)
        ]);
        $parsedBody = json_decode($data->getBody()->getContents(), true);

        if (
            isset($parsedBody['_embedded']) && 
            is_array($parsedBody['_embedded']) && 
            isset($parsedBody['_embedded']['contacts']) && 
            count($parsedBody['_embedded']['contacts'])
        ) {
            return $parsedBody['_embedded']['contacts'][0];
        } else {
            return [];
        }
    }

    public function logCall(array $call) {
        $data = $this->provider->getHttpClient()
            ->request('POST', $this->provider->urlAccount() . 'api/v2/calls', [
                'headers' => $this->provider->getHeaders($this->accessToken),
                'json'    => [ 'add' => [ $call ]]
            ]);

        $parsedBody = json_decode($data->getBody()->getContents(), true);        

        $addCallRespond = $parsedBody;
        $addLeadRespond = null;

        if ($direction == "inbound" && 
            is_array($parsedBody['_embedded']) && 
            count($parsedBody['_embedded']) && 
            is_array($parsedBody['_embedded']['errors']) &&
            count($parsedBody['_embedded']['errors']) 
            ) {      
          
          if ($parsedBody['_embedded']['errors'][0]['msg'] == 'Entity not found') {
            
            // Create new
            $entity = [
              'source_uid'  => 'erpicopbx_'.$call['uniq'],
              'source_name' => "ErpicoPBX",
              'created_at'  => time(),
              '_embedded'   => [
                "contacts" => [
                  [
                    "name" => $call['phone_number'],
                    "created_by" => $user_id
                  ]
                  ],
                  "leads" => [
                    [
                      "name" => $call['phone_number'],
                      "created_by" => $user_id
                    ]
                  ]
              ],
              'metadata'    => [
                "is_call_event_needed" => true,
                "uniq"                 => $call['uniq'],
                "duration"             => $call['duration'],
                "service_code"         => "erpicopbx",
                "link"                 => $call['link'],
                "phone"                => $call['phone_number'],
                "called_at"            => $call['created_at'],
                "from"                 => $call['phone_number']
              ]
            ];

            $data = $this->provider->getHttpClient()
              ->request('POST', $this->provider->urlAccount() . 'api/v4/leads/unsorted/sip', [
                'headers' => $this->provider->getHeaders($this->accessToken),
                'json'    => [ $entity ]
            ]);

            $addLeadRespond = json_decode($data->getBody()->getContents(), true);            
          }          
        }
        $result = [
          "call" => $addCallRespond,
          "lead" => $addLeadRespond
        ];

        return $result;
    }
}