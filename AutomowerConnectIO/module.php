<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/library.php';

class AutomowerConnectIO extends IPSModule
{
    use AutomowerConnectCommon;
    use AutomowerConnectLibrary;

    private $oauthIdentifer = 'husqvarna';

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');

        $this->RegisterAttributeString('ApiRefreshToken', ''); // OAuth
        $this->RegisterAttributeString('AppRefreshToken', ''); // REST-Login

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->RegisterOAuth($this->oauthIdentifer);
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        if ($user != '' && $password == '') {
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        if ($this->GetConnectUrl() == false) {
            $this->SetStatus(self::$IS_NOSYMCONCONNECT);
            return;
        }
        $refresh_token = $this->ReadAttributeString('ApiRefreshToken');
        if ($refresh_token == '') {
            $this->SetStatus(self::$IS_NOLOGIN);
        } else {
            $this->SetStatus(IS_ACTIVE);
        }

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->RegisterOAuth($this->oauthIdentifer);
        }
    }

    private function RegisterOAuth($WebOAuth)
    {
        $ids = IPS_GetInstanceListByModuleID('{F99BF07D-CECA-438B-A497-E4B55F139D37}');
        if (count($ids) > 0) {
            $clientIDs = json_decode(IPS_GetProperty($ids[0], 'ClientIDs'), true);
            $found = false;
            foreach ($clientIDs as $index => $clientID) {
                if ($clientID['ClientID'] == $WebOAuth) {
                    if ($clientID['TargetID'] == $this->InstanceID) {
                        return;
                    }
                    $clientIDs[$index]['TargetID'] = $this->InstanceID;
                    $found = true;
                }
            }
            if (!$found) {
                $clientIDs[] = ['ClientID' => $WebOAuth, 'TargetID' => $this->InstanceID];
            }
            IPS_SetProperty($ids[0], 'ClientIDs', json_encode($clientIDs));
            IPS_ApplyChanges($ids[0]);
        }
    }

    public function Login()
    {
        $url = 'https://oauth.ipmagic.de/authorize/' . $this->oauthIdentifer . '?username=' . urlencode(IPS_GetLicensee());
        $this->SendDebug(__FUNCTION__, 'url=' . $url, 0);
        return $url;
    }

    protected function Call4ApiAccessToken($content)
    {
        $url = 'https://oauth.ipmagic.de/access_token/' . $this->oauthIdentifer;
        $this->SendDebug(__FUNCTION__, 'url=' . $url, 0);
        $this->SendDebug(__FUNCTION__, '    content=' . print_r($content, true), 0);

        $statuscode = 0;
        $err = '';
        $jdata = false;

        $time_start = microtime(true);
        $options = [
            'http' => [
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($content)
            ]
        ];
        $context = stream_context_create($options);
        $cdata = @file_get_contents($url, false, $context);
        $duration = round(microtime(true) - $time_start, 2);
        $httpcode = 0;
        if ($cdata == false) {
            $this->LogMessage('file_get_contents() failed: url=' . $url . ', context=' . print_r($context, true), KL_WARNING);
            $this->SendDebug(__FUNCTION__, 'file_get_contents() failed: url=' . $url . ', context=' . print_r($context, true), 0);
        } elseif (isset($http_response_header[0]) && preg_match('/HTTP\/[0-9\.]+\s+([0-9]*)/', $http_response_header[0], $r)) {
            $httpcode = $r[1];
        } else {
            $this->LogMessage('missing http_response_header, cdata=' . $cdata, KL_WARNING);
            $this->SendDebug(__FUNCTION__, 'missing http_response_header, cdata=' . $cdata, 0);
        }
        $this->SendDebug(__FUNCTION__, ' => httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, '    cdata=' . $cdata, 0);

        if ($httpcode != 200) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_FORBIDDEN;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode == 409) {
                $data = $cdata;
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } else {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode;
            }
        } elseif ($cdata == '') {
            $statuscode = self::$IS_NODATA;
            $err = 'no data';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            } else {
                if (!isset($jdata['refresh_token'])) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'malformed response';
                }
            }
        }
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->SetStatus($statuscode);
            return false;
        }
        return $jdata;
    }

    private function FetchApiRefreshToken($code)
    {
        $this->SendDebug(__FUNCTION__, 'code=' . $code, 0);
        $jdata = $this->Call4ApiAccessToken(['code' => $code]);
        if ($jdata == false) {
            $this->SendDebug(__FUNCTION__, 'got no token', 0);
            $this->SetBuffer('ApiAccessToken', '');
            return false;
        }
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        $access_token = $jdata['access_token'];
        $expiration = time() + $jdata['expires_in'];
        $refresh_token = $jdata['refresh_token'];
        $this->FetchApiAccessToken($access_token, $expiration);
        return $refresh_token;
    }

    private function FetchApiAccessToken($access_token = '', $expiration = 0)
    {
        if ($access_token == '' && $expiration == 0) {
            $data = $this->GetBuffer('ApiAccessToken');
            if ($data != '') {
                $jtoken = json_decode($data, true);
                $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
                $expiration = isset($jtoken['expiration']) ? $jtoken['expiration'] : 0;
                if ($expiration < time()) {
                    $this->SendDebug(__FUNCTION__, 'access_token expired', 0);
                    $access_token = '';
                }
                if ($access_token != '') {
                    $this->SendDebug(__FUNCTION__, 'access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
                    return $access_token;
                }
            } else {
                $this->SendDebug(__FUNCTION__, 'no saved access_token', 0);
            }
            $refresh_token = $this->ReadAttributeString('ApiRefreshToken');
            $this->SendDebug(__FUNCTION__, 'refresh_token=' . print_r($refresh_token, true), 0);
            if ($refresh_token == '') {
                $this->SendDebug(__FUNCTION__, 'has no refresh_token', 0);
                $this->WriteAttributeString('ApiRefreshToken', '');
                $this->SetBuffer('ApiAccessToken', '');
                $this->SetStatus(self::$IS_NOLOGIN);
                return false;
            }
            $jdata = $this->Call4ApiAccessToken(['refresh_token' => $refresh_token]);
            if ($jdata == false) {
                $this->SendDebug(__FUNCTION__, 'got no access_token', 0);
                $this->SetBuffer('ApiAccessToken', '');
                return false;
            }
            $access_token = $jdata['access_token'];
            $expiration = time() + $jdata['expires_in'];
            if (isset($jdata['refresh_token'])) {
                $refresh_token = $jdata['refresh_token'];
                $this->SendDebug(__FUNCTION__, 'new refresh_token=' . $refresh_token, 0);
                $this->WriteAttributeString('ApiRefreshToken', $refresh_token);
            }
        }
        $this->SendDebug(__FUNCTION__, 'new access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
        $jtoken = [
            'access_token' => $access_token,
            'expiration'   => $expiration,
        ];
        $this->SetBuffer('ApiAccessToken', json_encode($jtoken));
        return $access_token;
    }

    protected function ProcessOAuthData()
    {
        if (!isset($_GET['code'])) {
            $this->SendDebug(__FUNCTION__, 'code missing, _GET=' . print_r($_GET, true), 0);
            $this->WriteAttributeString('ApiRefreshToken', '');
            $this->SetBuffer('ApiAccessToken', '');
            $this->SetStatus(self::$IS_NOLOGIN);
            return;
        }
        $refresh_token = $this->FetchApiRefreshToken($_GET['code']);
        $this->SendDebug(__FUNCTION__, 'refresh_token=' . $refresh_token, 0);
        $this->WriteAttributeString('ApiRefreshToken', $refresh_token);
        if ($this->GetStatus() == self::$IS_NOLOGIN) {
            $this->SetStatus(IS_ACTIVE);
        }
    }

    public function GetConfigurationForm()
    {
        $formElements = $this->GetFormElements();
        $formActions = $this->GetFormActions();
        $formStatus = $this->GetFormStatus();

        $form = json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
        if ($form == '') {
            $this->SendDebug(__FUNCTION__, 'json_error=' . json_last_error_msg(), 0);
            $this->SendDebug(__FUNCTION__, '=> formElements=' . print_r($formElements, true), 0);
            $this->SendDebug(__FUNCTION__, '=> formActions=' . print_r($formActions, true), 0);
            $this->SendDebug(__FUNCTION__, '=> formStatus=' . print_r($formStatus, true), 0);
        }
        return $form;
    }

    protected function GetFormElements()
    {
        $formElements = [];
        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Instance is disabled'
        ];

        $instID = IPS_GetInstanceListByModuleID('{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}')[0];
        if (IPS_GetInstance($instID)['InstanceStatus'] != IS_ACTIVE) {
            $msg = 'Error: Symcon Connect is not active!';
        } else {
            $msg = 'Status: Symcon Connect is OK!';
        }
        $formElements[] = [
            'type'    => 'Label',
            'caption' => $msg
        ];

        $items = [];
        $items[] = [
            'type'    => 'Label',
            'caption' => 'Push "Login at Husqvarna" in the action part of this configuration form.'
        ];
        $items[] = [
            'type'    => 'Label',
            'caption' => 'At the webpage from Husqvarna log in with your Husqvarna username and password.'
        ];
        $items[] = [
            'type'    => 'Label',
            'caption' => 'If the connection to IP-Symcon was successfull you get the message: "Husqvarna successfully connected!". Close the browser window.'
        ];
        $items[] = [
            'type'    => 'Label',
            'caption' => 'Return to this configuration form.'
        ];
        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => $items,
            'caption' => 'Husqvarna Login'
        ];

        $items = [];
        $items[] = [
            'type'    => 'Label',
            'caption' => 'This information is only required if position data of the mowers are to be fetched and requires a model with a GPS module.'
        ];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'user',
            'caption' => 'Username'
        ];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'password',
            'caption' => 'Password'
        ];
        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => $items,
            'caption' => 'Husqvarna Account-Details'
        ];

        return $formElements;
    }

    protected function GetFormActions()
    {
        $formActions = [];

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Login at Husqvarna',
            'onClick' => 'echo AutomowerConnect_Login($id);'
        ];

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Test access',
            'onClick' => 'AutomowerConnect_TestAccount($id);'];

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Clear Token',
            'onClick' => 'AutomowerConnect_ClearToken($id);'
        ];

        return $formActions;
    }

    protected function SendData($data, $source)
    {
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToChildren(json_encode(['DataID' => '{5F947426-53FB-4DD9-A725-F95590CBD97C}', 'Source' => $source, 'Buffer' => $data]));
    }

    public function ForwardData($data)
    {
        if ($this->CheckStatus() == STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $ret = '';
        if (isset($jdata['Function'])) {
            switch ($jdata['Function']) {
                case 'MowerList':
                    $ret = $this->GetMowerList();
                    break;
                case 'MowerStatus':
                    $ret = $this->GetMowerStatus($jdata['api_id']);
                    break;
                case 'MowerList4App':
                    $ret = $this->GetMowerList4App();
                    break;
                case 'MowerStatus4App':
                    $ret = $this->GetMowerStatus4App($jdata['app_id']);
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'unknown function "' . $jdata['Function'] . '"', 0);
                    break;
                }
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown message-structure', 0);
        }

        $this->SendDebug(__FUNCTION__, 'ret=' . print_r($ret, true), 0);
        return $ret;
    }

    private function GetApiAccessToken()
    {
        return $this->FetchApiAccessToken();
    }

    private function GetAppAccessToken()
    {
        $url = 'https://iam-api.dss.husqvarnagroup.net/api/v3/token';

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');

        $jtoken = json_decode($this->GetBuffer('AppAccessToken'), true);
        $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
        $expiration = isset($jtoken['expiration']) ? $jtoken['expiration'] : 0;

        if ($expiration < time()) {
            $postdata = [
                'data' => [
                    'attributes' => [
                        'username' => $user,
                        'password' => $password
                    ],
                    'type' => 'token'
                ]
            ];

            $header = [
                'Accept: application/json',
                'Content-Type: application/json'
            ];

            $ctoken = $this->do_HttpRequest($url, $header, $postdata, true);
            $this->SendDebug(__FUNCTION__, 'ctoken=' . print_r($ctoken, true), 0);
            if ($ctoken == '') {
                return false;
            }
            $jtoken = json_decode($ctoken, true);
            $this->SendDebug(__FUNCTION__, 'jtoken=' . print_r($jtoken, true), 0);

            $token = $jtoken['data']['id'];
            $provider = $jtoken['data']['attributes']['provider'];
            $expires_in = $jtoken['data']['attributes']['expires_in'];
            $user_id = $jtoken['data']['attributes']['user_id'];

            $jtoken = [
                'token'            => $token,
                'provider'         => $provider,
                'user_id'          => $user_id,
                'expiration'       => time() + $expires_in
            ];
            $this->SetBuffer('AppAccessToken', json_encode($jtoken));
        }

        return $jtoken;
    }

    public function ClearToken()
    {
        $refresh_token = $this->ReadAttributeString('ApiRefreshToken');
        $this->SendDebug(__FUNCTION__, 'clear refresh_token=' . $refresh_token, 0);
        $this->WriteAttributeString('ApiRefreshToken', '');

        $access_token = $this->GetApiAccessToken();
        $this->SendDebug(__FUNCTION__, 'clear access_token=' . $access_token, 0);
        $this->SetBuffer('ApiAccessToken', '');
    }

    private function GetMowerList()
    {
        return $this->do_ApiCall('mowers');
    }

    private function GetMowerStatus($api_id)
    {
        return $this->do_ApiCall('mowers/' . $api_id);
    }

    private function GetMowerList4App()
    {
        $user = $this->ReadPropertyString('user');
        if ($user == '') {
            return false;
        }

        return $this->do_AppCall('mowers');
    }

    private function GetMowerStatus4App($app_id)
    {
        $user = $this->ReadPropertyString('user');
        if ($user == '') {
            return false;
        }
        return $this->do_AppCall('mowers/' . $app_id . '/status');
    }

    private function do_ApiCall($cmd, $postdata = '')
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $token = $this->GetApiAccessToken();

        $header = [];
        $header[] = 'Accept: application/vnd.api+json';
        $header[] = 'Content-Type: application/json';
        $header[] = 'Authorization: Bearer ' . $token;
        $header[] = 'Authorization-Provider: husqvarna';
        $header[] = 'X-Api-Key: b42b22bf-5482-4f0b-b78a-9c5558ff5b4a';

        $url = 'https://api.amc.husqvarna.dev/v1/' . $cmd;

        $cdata = $this->do_HttpRequest($url, $header, $postdata);
        $this->SendDebug(__FUNCTION__, 'cdata=' . print_r($cdata, true), 0);

        $this->SetStatus(IS_ACTIVE);
        return $cdata;
    }

    private function do_HttpRequest($url, $header = '', $postdata = '')
    {
        $req = $postdata != '' ? 'post' : 'get';

        $this->SendDebug(__FUNCTION__, 'http-' . $req . ': url=' . $url, 0);
        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($header != '') {
            $this->SendDebug(__FUNCTION__, '    header=' . print_r($header, true), 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        if ($postdata != '') {
            $this->SendDebug(__FUNCTION__, '    postdata=' . json_encode($postdata), 0);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $cdata = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, ' => cdata=' . $cdata, 0);

        $statuscode = 0;
        $err = '';
        $data = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200 && $httpcode != 201) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
                // force new Token
                $this->SetBuffer('Token', '');
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode == 204) {
                // 204 = No Content	= Die Anfrage wurde erfolgreich durchgeführt, die Antwort enthält jedoch bewusst keine Daten.
                // kommt zB bei senden von SMS
                $data = json_encode(['status' => 'ok']);
            } else {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode;
            }
        } elseif ($cdata == '') {
            $statuscode = self::$IS_INVALIDDATA;
            $err = 'no data';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            } else {
                $data = $cdata;
            }
        }

        if ($statuscode) {
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->SetStatus($statuscode);
        }

        return $data;
    }

    private function do_AppCall($cmd, $postdata = '')
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $jtoken = $this->GetAppAccessToken();
        if ($jtoken == '') {
            return false;
        }
        $token = $jtoken['token'];
        $provider = $jtoken['provider'];

        $header = [];
        $header[] = 'Accept: application/json';
        $header[] = 'Content-Type: application/json';
        $header[] = 'Authorization: Bearer ' . $token;
        $header[] = 'Authorization-Provider: ' . $provider;

        $url = 'https://amc-api.dss.husqvarnagroup.net/v1/' . $cmd;
        $cdata = $this->do_HttpRequest($url, $header, $postdata);
        $this->SendDebug(__FUNCTION__, 'cdata=' . print_r($cdata, true), 0);

        $this->SetStatus(IS_ACTIVE);
        return $cdata;
    }

    public function TestAccount()
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            echo $this->translate('Instance is disabled') . PHP_EOL;
            return;
        }

        $apiAccessToken = $this->GetApiAccessToken();
        if ($apiAccessToken == false) {
            $this->SetStatus(self::$IS_UNAUTHORIZED);
            echo $this->translate('Invalid registration with Husqvarna') . PHP_EOL;
            return;
        }

        $cdata = $this->GetMowerList();
        $mowers = $cdata != '' ? json_decode($cdata, true) : '';
        $this->SendDebug(__FUNCTION__, 'mowers=' . print_r($mowers, true), 0);
        if ($mowers == '') {
            $this->SetStatus(self::$IS_UNAUTHORIZED);
            echo $this->Translate('invalid account-data');
            return;
        }

        $app_mowers = [];
        $user = $this->ReadPropertyString('user');
        if ($user != '') {
            $appAccessToken = $this->GetAppAccessToken();
            if ($appAccessToken == false) {
                $this->SetStatus(self::$IS_INVALIDACCOUNT);
                echo $this->translate('Invalid login details') . PHP_EOL;
                return;
            }
            $cdata = $this->GetMowerList4App();
            $app_mowers = $cdata != '' ? json_decode($cdata, true) : '';
        }

        $msg = '';
        foreach ($mowers['data'] as $mower) {
            $this->SendDebug(__FUNCTION__, 'mower=' . print_r($mower, true), 0);
            $api_id = $this->GetArrayElem($mower, 'id', '');
            $name = $this->GetArrayElem($mower, 'attributes.system.name', '');
            $model = $this->GetArrayElem($mower, 'attributes.system.model', '');
            $serial = $this->GetArrayElem($mower, 'attributes.system.serialNumber', '');

            $msg = $this->Translate('Mower') . ' "' . $name . '", ' . $this->Translate('Model') . '=' . $model;
            $app_id = false;
            if ($app_mowers != '') {
                foreach ($app_mowers as $app_mower) {
                    $id = $app_mower['id'];
                    $ids = explode('-', $id);
                    if ($serial == $ids[0]) {
                        $app_id = $id;
                        break;
                    }
                }
            }
            $this->SendDebug(__FUNCTION__, 'api_id=' . $api_id . ', name=' . $name . ', model=' . $model . ', app_id=' . $app_id, 0);
        }

        echo $this->translate('valid account-data') . "\n" . $msg;
    }
}
