<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class AutomowerConnectIO extends IPSModule
{
    use AutomowerConnect\StubsCommonLib;
    use AutomowerConnectLocalLib;

    private $oauthIdentifer = 'husqvarna';
    private $oauthAppKey = '66679300-6f0d-43ed-b5b1-08e83fec88de';

    private static $semaphoreTM = 5 * 1000;

    private $ModuleDir;
    private $SemaphoreID;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->ModuleDir = __DIR__;
        $this->SemaphoreID = __CLASS__ . '_' . $InstanceID;
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyInteger('connection_type', self::$CONNECTION_OAUTH);

        // OAuth
        $this->RegisterAttributeString('ApiRefreshToken', '');

        // Developer
        $this->RegisterPropertyString('username', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('api_key', '');
        $this->RegisterPropertyString('api_secret', '');

        $this->RegisterAttributeString('UpdateInfo', '');
        $this->RegisterAttributeString('ApiCallStats', json_encode([]));

        $this->SetBuffer('ApiAccessToken', '');
        $this->SetBuffer('ConnectionType', '');

        $this->SetBuffer('LastApiCall', 0);

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $connection_type = $this->ReadPropertyInteger('connection_type');
        if ($connection_type == self::$CONNECTION_DEVELOPER) {
            $api_key = $this->ReadPropertyString('api_key');
            if ($api_key == '') {
                $this->SendDebug(__FUNCTION__, '"api_key" is needed', 0);
                $r[] = $this->Translate('\'Application key\' must be specified');
            }
            $api_secret = $this->ReadPropertyString('api_secret');
            $username = $this->ReadPropertyString('username');
            $password = $this->ReadPropertyString('password');
            if ($api_secret == '' && ($username == '' || $password == '')) {
                $this->SendDebug(__FUNCTION__, '"api_secret" or "username" and "password" is needed', 0);
                $r[] = $this->Translate('\'Application secret\' or Username and Password must be specified');
            }
        }

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $apiLimits = [
            [
                'value' => 1,
                'unit'  => 'second',
            ],
            [
                'value' => 10000,
                'unit'  => 'month',
            ],
        ];
        $this->ApiCallsSetInfo($apiLimits, '');

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $connection_type = $this->ReadPropertyInteger('connection_type');
        if ($this->GetBuffer('ConnectionType') != $connection_type) {
            if ($this->GetBuffer('ConnectionType') == '') {
                $this->ClearToken();
            }
            $this->SetBuffer('ConnectionType', $connection_type);
        }

        if ($connection_type == self::$CONNECTION_OAUTH) {
            if ($this->GetConnectUrl() == false) {
                $this->MaintainStatus(self::$IS_NOSYMCONCONNECT);
                return;
            }
            if (IPS_GetKernelRunlevel() == KR_READY) {
                $this->RegisterOAuth($this->oauthIdentifer);
            }
            $refresh_token = $this->ReadAttributeString('ApiRefreshToken');
            if ($refresh_token == '') {
                $this->MaintainStatus(self::$IS_NOLOGIN);
                return;
            }
        }

        $this->MaintainStatus(IS_ACTIVE);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $connection_type = $this->ReadPropertyInteger('connection_type');
            if ($connection_type == self::$CONNECTION_OAUTH) {
                $this->RegisterOAuth($this->oauthIdentifer);
            }
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Husqvarna AutomowerConnect I/O');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $connection_type = $this->ReadPropertyInteger('connection_type');
        if ($connection_type == self::$CONNECTION_OAUTH) {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => $this->GetConnectStatusText(),
            ];
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'    => 'Select',
            'name'    => 'connection_type',
            'caption' => 'Connection type',
            'options' => [
                [
                    'caption' => 'Please select a connection type',
                    'value'   => self::$CONNECTION_UNDEFINED
                ],
                [
                    'caption' => 'via IP-Symcon Connect',
                    'value'   => self::$CONNECTION_OAUTH
                ],
                [
                    'caption' => 'with Husqvarna Application Key',
                    'value'   => self::$CONNECTION_DEVELOPER
                ]
            ]
        ];

        switch ($connection_type) {
            case self::$CONNECTION_OAUTH:
                $formElements[] = [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Husqvarna Login',
                    'items'   => [
                        [
                            'type'    => 'Label',
                            'caption' => 'Push "Login at Husqvarna" in the action part of this configuration form.'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => 'At the webpage from Husqvarna log in with your Husqvarna username and password.'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => 'If the connection to IP-Symcon was successfull you get the message: "Husqvarna successfully connected!". Close the browser window.'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => 'Return to this configuration form.'
                        ],
                    ],
                ];
                break;
            case self::$CONNECTION_DEVELOPER:
                $formElements[] = [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Husqvarna Account-Details',
                    'items'   => [
                        [
                            'name'    => 'api_key',
                            'type'    => 'ValidationTextBox',
                            'width'   => '400px',
                            'caption' => 'Application key'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => '\'Application secret\' or Username and Password must be specified'
                        ],
                        [
                            'name'    => 'api_secret',
                            'type'    => 'PasswordTextBox',
                            'width'   => '400px',
                            'caption' => 'Application secret'
                        ],
                        [
                            'name'    => 'username',
                            'type'    => 'ValidationTextBox',
                            'caption' => 'User-ID (email)'
                        ],
                        [
                            'name'    => 'password',
                            'type'    => 'PasswordTextBox',
                            'caption' => 'Password'
                        ],
                    ],
                ];
                break;
        }

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $connection_type = $this->ReadPropertyInteger('connection_type');
        if ($connection_type == self::$CONNECTION_OAUTH) {
            $formActions[] = [
                'type'    => 'Button',
                'caption' => 'Login at Husqvarna',
                'onClick' => 'echo "' . $this->Login() . '";',
            ];
        }

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Test access',
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "TestAccount", "");',
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
                [
                    'type'    => 'Button',
                    'caption' => 'Clear token',
                    'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ClearToken", "");',
                ],
                $this->GetApiCallStatsFormItem(),
            ]
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->CommonRequestAction($Ident, $Value)) {
            return;
        }
        switch ($Ident) {
            case 'TestAccount':
                $this->TestAccount();
                break;
            case 'ClearToken':
                $this->ClearToken();
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $Ident, 0);
                break;
        }
    }

    private function Login()
    {
        $url = 'https://oauth.ipmagic.de/authorize/' . $this->oauthIdentifer . '?username=' . urlencode(IPS_GetLicensee());
        $this->SendDebug(__FUNCTION__, 'url=' . $url, 0);
        return $url;
    }

    protected function ProcessOAuthData()
    {
        if (!isset($_GET['code'])) {
            $this->SendDebug(__FUNCTION__, 'code missing, _GET=' . print_r($_GET, true), 0);
            $this->WriteAttributeString('ApiRefreshToken', '');
            $this->SetBuffer('ApiAccessToken', '');
            $this->MaintainStatus(self::$IS_NOLOGIN);
            return;
        }
        $refresh_token = $this->GetApiRefreshToken($_GET['code']);
        $this->SendDebug(__FUNCTION__, 'refresh_token=' . $refresh_token, 0);
        $this->WriteAttributeString('ApiRefreshToken', $refresh_token);
        if ($this->GetStatus() == self::$IS_NOLOGIN) {
            $this->MaintainStatus(IS_ACTIVE);
        }
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
            $statuscode = self::$IS_INVALIDDATA;
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
            $this->MaintainStatus($statuscode);
            return false;
        }
        return $jdata;
    }

    private function DeveloperApiAccessToken()
    {
        $username = $this->ReadPropertyString('username');
        $password = $this->ReadPropertyString('password');
        $api_key = $this->ReadPropertyString('api_key');
        $api_secret = $this->ReadPropertyString('api_secret');

        $url = 'https://api.authentication.husqvarnagroup.dev/v1/oauth2/token';

        $header = [
            'Content-Type: application/x-www-form-urlencoded',
        ];

        if ($api_secret != '') {
            $postdata = [
                'grant_type'    => 'client_credentials',
                'client_id'     => $api_key,
                'client_secret' => $api_secret,
            ];
        } else {
            $postdata = [
                'grant_type'    => 'password',
                'client_id'     => $api_key,
                'username'      => $username,
                'password'      => $password,
            ];
        }

        $this->SendDebug(__FUNCTION__, 'http-post: url=' . $url, 0);
        $this->SendDebug(__FUNCTION__, '    header=' . print_r($header, true), 0);
        $this->SendDebug(__FUNCTION__, '    postdata=' . http_build_query($postdata), 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
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
        } elseif ($httpcode != 200) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
                // force new Token
                $this->SetBuffer('ApiAccessToken', '');
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
            }
        }
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }
        return $jdata;
    }

    private function GetApiAccessToken($access_token = '', $expiration = 0)
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

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
                    IPS_SemaphoreLeave($this->SemaphoreID);
                    return $access_token;
                }
            } else {
                $this->SendDebug(__FUNCTION__, 'no saved access_token', 0);
            }
            $connection_type = $this->ReadPropertyInteger('connection_type');
            switch ($connection_type) {
                case self::$CONNECTION_OAUTH:
                    $refresh_token = $this->ReadAttributeString('ApiRefreshToken');
                    $this->SendDebug(__FUNCTION__, 'refresh_token=' . print_r($refresh_token, true), 0);
                    if ($refresh_token == '') {
                        $this->SendDebug(__FUNCTION__, 'has no refresh_token', 0);
                        $this->WriteAttributeString('ApiRefreshToken', '');
                        $this->SetBuffer('ApiAccessToken', '');
                        $this->MaintainStatus(self::$IS_NOLOGIN);
                        IPS_SemaphoreLeave($this->SemaphoreID);
                        return false;
                    }
                    $jdata = $this->Call4ApiAccessToken(['refresh_token' => $refresh_token]);
                    break;
                case self::$CONNECTION_DEVELOPER:
                    $jdata = $this->DeveloperApiAccessToken();
                    break;
                default:
                    $jdata = false;
                    break;
            }
            if ($jdata == false) {
                $this->SendDebug(__FUNCTION__, 'got no access_token', 0);
                $this->SetBuffer('ApiAccessToken', '');
                IPS_SemaphoreLeave($this->SemaphoreID);
                return false;
            }
            $access_token = $jdata['access_token'];
            $expiration = time() + $jdata['expires_in'];
            if ($connection_type == self::$CONNECTION_OAUTH) {
                if (isset($jdata['refresh_token'])) {
                    $refresh_token = $jdata['refresh_token'];
                    $this->SendDebug(__FUNCTION__, 'new refresh_token=' . $refresh_token, 0);
                    $this->WriteAttributeString('ApiRefreshToken', $refresh_token);
                }
            }
        }
        $this->SendDebug(__FUNCTION__, 'new access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
        $jtoken = [
            'access_token' => $access_token,
            'expiration'   => $expiration,
        ];
        $this->SetBuffer('ApiAccessToken', json_encode($jtoken));
        IPS_SemaphoreLeave($this->SemaphoreID);

        return $access_token;
    }

    private function GetApiRefreshToken($code)
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
        $this->GetApiAccessToken($access_token, $expiration);
        return $refresh_token;
    }

    protected function SendData($data)
    {
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToChildren(json_encode(['DataID' => '{277691A0-EF84-1883-2094-45C56419748A}', 'Buffer' => $data]));
    }

    public function ForwardData($data)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $callerID = $jdata['CallerID'];
        $this->SendDebug(__FUNCTION__, 'caller=' . $callerID . '(' . IPS_GetName($callerID) . ')', 0);
        $_IPS['CallerID'] = $callerID;

        $ret = '';
        if (isset($jdata['Function'])) {
            switch ($jdata['Function']) {
                case 'MowerList':
                    $ret = $this->GetMowerList();
                    break;
                case 'MowerStatus':
                    $ret = $this->GetMowerStatus($jdata['id']);
                    break;
                case 'MowerCmd':
                    $ret = $this->DoMowerCmd($jdata['id'], $jdata['command'], $jdata['data']);
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

    private function ClearToken()
    {
        $refresh_token = $this->ReadAttributeString('ApiRefreshToken');
        $this->SendDebug(__FUNCTION__, 'clear refresh_token=' . $refresh_token, 0);
        $this->WriteAttributeString('ApiRefreshToken', '');

        $jtoken = json_decode($this->GetBuffer('ApiAccessToken'), true);
        $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
        $this->SendDebug(__FUNCTION__, 'clear access_token=' . $access_token, 0);
        $this->SetBuffer('ApiAccessToken', '');

        $this->MaintainStatus(self::$IS_NOLOGIN);
    }

    private function GetMowerList()
    {
        return $this->do_ApiCall('mowers');
    }

    private function GetMowerStatus($id)
    {
        return $this->do_ApiCall('mowers/' . $id);
    }

    private function DoMowerCmd($id, $command, $data)
    {
        $postdata = [
            'data' => $data
        ];
        return $this->do_ApiCall('mowers/' . $id . '/' . $command, $postdata);
    }

    private function do_ApiCall($cmd, $postdata = '')
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $token = $this->GetApiAccessToken();

        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return;
        }

        $ts = intval($this->GetBuffer('LastApiCall'));
        if ($ts == time()) {
            $this->SendDebug(__FUNCTION__, 'multiple call/second', 0);
            while ($ts == time()) {
                IPS_Sleep(100);
            }
        }

        $connection_type = $this->ReadPropertyInteger('connection_type');
        switch ($connection_type) {
            case self::$CONNECTION_OAUTH:
                $api_key = $this->oauthAppKey;
                break;
            case self::$CONNECTION_DEVELOPER:
                $api_key = $this->ReadPropertyString('api_key');
                break;
            default:
                $api_key = '';
                break;
        }

        $header = [
            'Accept: application/vnd.api+json',
            'Content-Type: application/vnd.api+json',
            'Authorization: Bearer ' . $token,
            'Authorization-Provider: husqvarna',
            'X-Api-Key: ' . $api_key,
        ];

        $url = 'https://api.amc.husqvarna.dev/v1/' . $cmd;

        $cdata = $this->do_HttpRequest($url, $header, $postdata);
        $this->SendDebug(__FUNCTION__, 'cdata=' . print_r($cdata, true), 0);

        $this->MaintainStatus(IS_ACTIVE);

        $this->SetBuffer('LastApiCall', time());

        IPS_SemaphoreLeave($this->SemaphoreID);

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
                $this->SetBuffer('ApiAccessToken', '');
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode == 202) {
                // 202 = command queued
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
            $this->MaintainStatus($statuscode);
        }

        $this->ApiCallsCollect($url, $err, $statuscode);

        return $data;
    }

    private function TestAccount()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            $msg = $this->GetStatusText() . PHP_EOL;
            $this->PopupMessage($msg);
            return;
        }

        $access_token = $this->GetApiAccessToken();
        if ($access_token == false) {
            $this->MaintainStatus(self::$IS_UNAUTHORIZED);
            $msg = $this->Translate('Invalid registration with Husqvarna') . PHP_EOL;
            $this->PopupMessage($msg);
            return;
        }

        $cdata = $this->GetMowerList();
        $mowers = $cdata != '' ? json_decode($cdata, true) : '';
        $this->SendDebug(__FUNCTION__, 'mowers=' . print_r($mowers, true), 0);
        if ($mowers == '') {
            $this->MaintainStatus(self::$IS_UNAUTHORIZED);
            $msg = $this->Translate('Invalid account-data');
            $this->PopupMessage($msg);
            return;
        }

        $msg = $this->Translate('Valid account-data') . PHP_EOL;
        foreach ($mowers['data'] as $mower) {
            $this->SendDebug(__FUNCTION__, 'mower=' . print_r($mower, true), 0);
            $id = $this->GetArrayElem($mower, 'id', '');
            $name = $this->GetArrayElem($mower, 'attributes.system.name', '');
            $model = $this->GetArrayElem($mower, 'attributes.system.model', '');
            $serial = $this->GetArrayElem($mower, 'attributes.system.serialNumber', '');

            $msg = $this->Translate('Mower') . ' "' . $name . '", ' . $this->Translate('Model') . '=' . $model . PHP_EOL;
            $this->SendDebug(__FUNCTION__, 'id=' . $id . ', name=' . $name . ', model=' . $model, 0);
        }
        $this->PopupMessage($msg);
    }
}
