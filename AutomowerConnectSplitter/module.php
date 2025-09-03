<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class AutomowerConnectSplitter extends IPSModule
{
    use AutomowerConnect\StubsCommonLib;
    use AutomowerConnectLocalLib;

    private $oauthIdentifer = 'husqvarna';

    private $SemaphoreID;

    private function GetSemaphoreTM()
    {
        $curl_exec_timeout = $this->ReadPropertyInteger('curl_exec_timeout');
        $curl_exec_attempts = $this->ReadPropertyInteger('curl_exec_attempts');
        $curl_exec_delay = $this->ReadPropertyFloat('curl_exec_delay');
        $semaphoreTM = ((($curl_exec_timeout + ceil($curl_exec_delay)) * $curl_exec_attempts) + 1) * 1000;

        //$this->SendDebug(__FUNCTION__, 'semaphoreTM='.$semaphoreTM, 0);
        return $semaphoreTM;
    }

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonConstruct(__DIR__);
        $this->SemaphoreID = __CLASS__ . '_' . $InstanceID;
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyInteger('connection_type', self::$CONNECTION_OAUTH);

        $this->RegisterAttributeString('ApiRefreshToken', json_encode([]));
        $this->RegisterAttributeString('ApiAccessToken', json_encode([]));
        $this->RegisterAttributeInteger('ConnectionType', self::$CONNECTION_UNDEFINED);

        // Developer
        $this->RegisterPropertyString('username', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('api_key', '');
        $this->RegisterPropertyString('api_secret', '');

        $this->RegisterPropertyInteger('curl_exec_timeout', 15);
        $this->RegisterPropertyInteger('curl_exec_attempts', 3);
        $this->RegisterPropertyFloat('curl_exec_delay', 1);

        $this->RegisterPropertyBoolean('collectApiCallStats', true);

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->SetBuffer('LastApiCall', 0);

        $this->RegisterTimer('RenewTimer', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "RenewToken", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        $this->RequireParent('{D68FD31F-0E90-7019-F16C-1949BD3079EF}');
    }

    public function GetConfigurationForParent()
    {
        $headers = [];
        $access_token = $this->GetAccessToken();
        if ($access_token != '') {
            $headers[] = [
                'Name'  => 'Authorization',
                'Value' => 'Bearer ' . $access_token,
            ];
        }

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        $active = $module_disable != true && $headers != [];

        $r = IPS_GetConfiguration($this->GetConnectionID());
        $j = [
            'Active'            => $active,
            'Headers'           => json_encode($headers),
            'URL'               => 'wss://ws.openapi.husqvarna.dev/v1',
            'VerifyCertificate' => false,
        ];
        $d = json_encode($j);
        $this->SendDebug(__FUNCTION__, $d, 0);
        return $d;
    }

    // bei jeder Änderung des access_token muss dieser im WebsocketClient als Header gesetzt werden
    // Rücksprache mit NT per Mail am 26.04.2023
    // Das temporäre Inaktiv-Setzen des IO dient dazu, ein Problem im IO zu reparieren: es kommt unter
    // bestimmten Umstäbnen wohl dazu, das der IO keine Verbindung zur Gegenseite hat, sich aber auch
    // nicht mehr von alleine neu startet.
    // AKtiv wird der IO wieder durch das GetConfigurationForParent() // IPS_SetConfiguration() gesetzt.
    private function UpdateConfigurationForParent()
    {
        if (IPS_GetInstance($this->GetConnectionID())['InstanceStatus'] >= IS_EBASE) {
            if (IPS_GetProperty($this->GetConnectionID(), 'Active') == true) {
                $this->SendDebug(__FUNCTION__, 'set parent inactive', 0);
                IPS_SetProperty($this->GetConnectionID(), 'Active', false);
                IPS_ApplyChanges($this->GetConnectionID());
            }
        }
        $this->SendDebug(__FUNCTION__, 'update parent configuration', 0);
        $d = $this->GetConfigurationForParent();
        IPS_SetConfiguration($this->GetConnectionID(), $d);
        IPS_ApplyChanges($this->GetConnectionID());
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

    private function CheckModuleUpdate(array $oldInfo, array $newInfo)
    {
        $r = [];

        if ($this->version2num($oldInfo) < $this->version2num('3.6')) {
            $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
            if ($collectApiCallStats) {
                $r[] = $this->Translate('Set ident of media objects');
            }
        }

        return $r;
    }

    private function CompleteModuleUpdate(array $oldInfo, array $newInfo)
    {
        $r = '';

        if ($this->version2num($oldInfo) < $this->version2num('3.6')) {
            $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
            if ($collectApiCallStats) {
                $m = [
                    'ApiCallStats' => '.txt',
                ];

                foreach ($m as $ident => $extension) {
                    $filename = 'media' . DIRECTORY_SEPARATOR . $this->InstanceID . '-' . $ident . $extension;
                    @$mediaID = IPS_GetMediaIDByFile($filename);
                    if ($mediaID != false) {
                        IPS_SetIdent($mediaID, $ident);
                    }
                }
            }
        }

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        $this->UnregisterMessage($this->GetConnectionID(), IM_CHANGESTATUS);

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('RenewTimer', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('RenewTimer', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('RenewTimer', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $connection_type = $this->ReadPropertyInteger('connection_type');

        $vpos = 1000;

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        $this->MaintainMedia('ApiCallStats', $this->Translate('API call statistics'), MEDIATYPE_DOCUMENT, '.txt', false, $vpos++, $collectApiCallStats);

        if ($collectApiCallStats) {
            $apiLimits = [
                [
                    'value' => 1,
                    'unit'  => 'second',
                ],
            ];
            if ($connection_type == self::$CONNECTION_OAUTH) {
                $apiLimits[] = [
                    'value' => 100,
                    'unit'  => 'day',
                ];
            } else {
                $apiLimits[] = [
                    'value' => 10000,
                    'unit'  => 'month',
                ];
            }
            $apiNotes = '';
            $this->ApiCallSetInfo($apiLimits, $apiNotes);
        }

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('RenewTimer', 0);
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        if ($this->ReadAttributeInteger('ConnectionType') != $connection_type) {
            $this->ClearToken();
            $this->WriteAttributeInteger('ConnectionType', $connection_type);
        }

        $this->MaintainStatus(IS_ACTIVE);

        $this->RegisterMessage($this->GetConnectionID(), IM_CHANGESTATUS);

        if ($connection_type == self::$CONNECTION_OAUTH) {
            if ($this->GetConnectUrl() == false) {
                $this->MaintainStatus(self::$IS_NOSYMCONCONNECT);
                return;
            }
            if (IPS_GetKernelRunlevel() == KR_READY) {
                $this->RegisterOAuth($this->oauthIdentifer);
            }
            $refresh_token = $this->GetRefreshToken();
            if ($refresh_token == '') {
                $this->MaintainStatus(self::$IS_NOLOGIN);
                return;
            }
        } else {
            if (IPS_GetKernelRunlevel() == KR_READY) {
                if ($this->GetAccessToken() == '') {
                    $this->RenewToken();
                }
            }
        }

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetRefreshTimer();
        }
    }

    private function SetRefreshTimer()
    {
        $msec = 0;

        $jtoken = @json_decode($this->ReadAttributeString('ApiAccessToken'), true);
        if ($jtoken != false) {
            $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
            $expiration = isset($jtoken['expiration']) ? $jtoken['expiration'] : 0;
            if ($expiration) {
                $sec = $expiration - time() - (60 * 15);
                $msec = $sec > 0 ? $sec * 1000 : 100;
            }
        }

        $this->MaintainTimer('RenewTimer', $msec);
    }

    private function GetRefreshToken()
    {
        $jtoken = @json_decode($this->ReadAttributeString('ApiRefreshToken'), true);
        if ($jtoken != false) {
            $refresh_token = isset($jtoken['refresh_token']) ? $jtoken['refresh_token'] : '';
            if ($refresh_token != '') {
                $this->SendDebug(__FUNCTION__, 'old refresh_token', 0);
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'no saved refresh_token', 0);
            $refresh_token = '';
        }
        return $refresh_token;
    }

    private function SetRefreshToken($refresh_token = '')
    {
        $jtoken = [
            'tstamp'        => time(),
            'refresh_token' => $refresh_token,
        ];
        $this->WriteAttributeString('ApiRefreshToken', json_encode($jtoken));
        if ($refresh_token == '') {
            $this->SendDebug(__FUNCTION__, 'clear refresh_token', 0);
        } else {
            $this->SendDebug(__FUNCTION__, 'new refresh_token', 0);
        }
    }

    private function GetAccessToken()
    {
        $jtoken = @json_decode($this->ReadAttributeString('ApiAccessToken'), true);
        if ($jtoken != false) {
            $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
            $expiration = isset($jtoken['expiration']) ? $jtoken['expiration'] : 0;
            if ($expiration < time()) {
                $this->SendDebug(__FUNCTION__, 'access_token expired', 0);
                $access_token = '';
            }
            if ($access_token != '') {
                $this->SendDebug(__FUNCTION__, 'old access_token, valid until ' . date('d.m.y H:i:s', $expiration), 0);
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'no saved access_token', 0);
            $access_token = '';
        }
        return $access_token;
    }

    private function RenewToken()
    {
        $this->SendDebug(__FUNCTION__, '', 0);
        $this->GetApiAccessToken(true);
    }

    private function SetAccessToken($access_token = '', $expiration = 0)
    {
        $jtoken = [
            'tstamp'       => time(),
            'access_token' => $access_token,
            'expiration'   => $expiration,
        ];
        $this->WriteAttributeString('ApiAccessToken', json_encode($jtoken));
        if ($access_token == '') {
            $this->SendDebug(__FUNCTION__, 'clear access_token', 0);
        } else {
            $this->SendDebug(__FUNCTION__, 'new access_token, valid until ' . date('d.m.y H:i:s', $expiration), 0);
        }
        $this->UpdateConfigurationForParent();
        if ($expiration) {
            $this->SetRefreshTimer();
        }
    }

    public function MessageSink($timestamp, $senderID, $message, $data)
    {
        parent::MessageSink($timestamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
            $connection_type = $this->ReadPropertyInteger('connection_type');
            if ($connection_type == self::$CONNECTION_OAUTH) {
                $this->RegisterOAuth($this->oauthIdentifer);
            } else {
                if ($this->GetAccessToken() == '') {
                    $this->RenewToken();
                }
            }
            $this->SetRefreshTimer();
        }

        if (IPS_GetKernelRunlevel() == KR_READY && $message == IM_CHANGESTATUS && $senderID == $this->GetConnectionID()) {
            $this->SendDebug(__FUNCTION__, 'timestamp=' . $timestamp . ', senderID=' . $senderID . ', message=' . $message . ', data=' . print_r($data, true), 0);
            if ($data[0] == IS_ACTIVE && $data[1] != IS_ACTIVE) {
                $this->MaintainTimer('RenewTimer', 60 * 1000);
            }
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Husqvarna AutomowerConnect Splitter');

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

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'Label',
                    'caption' => 'Behavior of HTTP requests at the technical level'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'minimum' => 0,
                    'suffix'  => 'Seconds',
                    'name'    => 'curl_exec_timeout',
                    'caption' => 'Timeout of an HTTP call'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'minimum' => 0,
                    'name'    => 'curl_exec_attempts',
                    'caption' => 'Number of attempts after communication failure'
                ],
                [
                    'type'     => 'NumberSpinner',
                    'minimum'  => 0.1,
                    'maximum'  => 60,
                    'digits'   => 1,
                    'suffix'   => 'Seconds',
                    'name'     => 'curl_exec_delay',
                    'caption'  => 'Delay between attempts'
                ],
            ],
            'caption' => 'Communication'
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'collectApiCallStats',
            'caption' => 'Collect data of API calls'
        ];

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

        $items = [
            $this->GetInstallVarProfilesFormItem(),
            [
                'type'    => 'Button',
                'caption' => 'Clear token',
                'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ClearToken", "");',
            ],
        ];

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $items[] = $this->GetApiCallStatsFormItem();
        }

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => $items,
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'RenewToken':
                $this->RenewToken();
                break;
            default:
                $r = false;
                break;
        }
        return $r;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->LocalRequestAction($ident, $value)) {
            return;
        }
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }
        switch ($ident) {
            case 'TestAccount':
                $this->TestAccount();
                break;
            case 'ClearToken':
                $this->ClearToken();
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
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
            $this->SetRefreshToken('');
            $this->SetAccessToken('');
            $this->MaintainStatus(self::$IS_NOLOGIN);
            return;
        }

        $code = $_GET['code'];
        $this->SendDebug(__FUNCTION__, 'code=' . $code, 0);

        $jdata = $this->Call4ApiToken(['code' => $code]);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        if ($jdata == false) {
            $this->SendDebug(__FUNCTION__, 'got no token', 0);
            $this->SetRefreshToken('');
            $this->SetAccessToken('');
            return false;
        }

        $access_token = $jdata['access_token'];
        $expiration = time() + $jdata['expires_in'];
        $this->SetAccessToken($access_token, $expiration);
        $refresh_token = $jdata['refresh_token'];
        $this->SetRefreshToken($refresh_token);

        if ($this->GetStatus() == self::$IS_NOLOGIN) {
            $this->MaintainStatus(IS_ACTIVE);
        }
    }

    protected function Call4ApiToken($content)
    {
        $curl_exec_timeout = $this->ReadPropertyInteger('curl_exec_timeout');
        $curl_exec_attempts = $this->ReadPropertyInteger('curl_exec_attempts');
        $curl_exec_delay = $this->ReadPropertyFloat('curl_exec_delay');

        $url = 'https://oauth.ipmagic.de/access_token/' . $this->oauthIdentifer;
        $this->SendDebug(__FUNCTION__, 'url=' . $url, 0);
        $this->SendDebug(__FUNCTION__, '    content=' . print_r($content, true), 0);

        $headerfields = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $time_start = microtime(true);
        $curl_opts = [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $this->build_header($headerfields),
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => http_build_query($content),
            CURLOPT_HEADER         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $curl_exec_timeout,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $curl_opts);

        $statuscode = 0;
        $err = '';
        $jbody = false;

        $attempt = 1;
        do {
            $response = curl_exec($ch);
            $cerrno = curl_errno($ch);
            $cerror = $cerrno ? curl_error($ch) : '';
            if ($cerrno) {
                $this->SendDebug(__FUNCTION__, ' => attempt=' . $attempt . ', got curl-errno ' . $cerrno . ' (' . $cerror . ')', 0);
                IPS_Sleep((int) floor($curl_exec_delay * 1000));
            }
        } while ($cerrno && $attempt++ <= $curl_exec_attempts);

        $curl_info = curl_getinfo($ch);
        curl_close($ch);

        $httpcode = $curl_info['http_code'];

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's, attempts=' . $attempt, 0);

        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } else {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, ' => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, ' => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }
        if ($statuscode == 0) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_FORBIDDEN;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode;
            }
        }
        if ($statuscode == 0) {
            if ($body == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no data';
            } else {
                $jbody = json_decode($body, true);
                if ($jbody == '') {
                    $statuscode = self::$IS_NODATA;
                    $err = 'malformed response';
                } elseif (isset($jbody['refresh_token']) == false) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'malformed response';
                }
            }
        }

        if ($statuscode) {
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }
        return $jbody;
    }

    private function DeveloperApiAccessToken()
    {
        $username = $this->ReadPropertyString('username');
        $password = $this->ReadPropertyString('password');
        $api_key = $this->ReadPropertyString('api_key');
        $api_secret = $this->ReadPropertyString('api_secret');
        $curl_exec_timeout = $this->ReadPropertyInteger('curl_exec_timeout');
        $curl_exec_attempts = $this->ReadPropertyInteger('curl_exec_attempts');
        $curl_exec_delay = $this->ReadPropertyFloat('curl_exec_delay');

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

        $attempt = 1;
        do {
            $cdata = curl_exec($ch);
            $cerrno = curl_errno($ch);
            $cerror = $cerrno ? curl_error($ch) : '';
            if ($cerrno) {
                $this->SendDebug(__FUNCTION__, ' => attempt=' . $attempt . ', got curl-errno ' . $cerrno . ' (' . $cerror . ')', 0);
                IPS_Sleep((int) floor($curl_exec_delay * 1000));
            }
        } while ($cerrno && $attempt++ <= $curl_exec_attempts);

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's, attempts=' . $attempt, 0);
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
                $this->SetAccessToken('');
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

    private function GetApiAccessToken($renew = false)
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, $this->GetSemaphoreTM()) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        if ($renew == false) {
            $access_token = $this->GetAccessToken();
            if ($access_token != '') {
                IPS_SemaphoreLeave($this->SemaphoreID);
                return $access_token;
            }
        }

        $connection_type = $this->ReadPropertyInteger('connection_type');
        switch ($connection_type) {
            case self::$CONNECTION_OAUTH:
                $refresh_token = $this->GetRefreshToken();
                if ($refresh_token == '') {
                    $this->SendDebug(__FUNCTION__, 'has no refresh_token', 0);
                    $this->SetAccessToken('');
                    $this->MaintainStatus(self::$IS_NOLOGIN);
                    IPS_SemaphoreLeave($this->SemaphoreID);
                    return false;
                }
                $jdata = $this->Call4ApiToken(['refresh_token' => $refresh_token]);
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
            $this->SetAccessToken('');
            IPS_SemaphoreLeave($this->SemaphoreID);
            return false;
        }

        $access_token = $jdata['access_token'];
        $expiration = time() + $jdata['expires_in'];
        $this->SetAccessToken($access_token, $expiration);
        if (isset($jdata['refresh_token'])) {
            $refresh_token = $jdata['refresh_token'];
            $this->SetRefreshToken($refresh_token);
        }

        IPS_SemaphoreLeave($this->SemaphoreID);
        $this->MaintainStatus(IS_ACTIVE);
        return $access_token;
    }

    protected function SendData($data)
    {
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToChildren(json_encode(['DataID' => '{277691A0-EF84-1883-2094-45C56419748A}', 'Buffer' => $data]));
    }

    public function ReceiveData($data)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);
        $this->SendDataToChildren(json_encode(['DataID' => '{D62B246F-6BE0-9D6C-C415-FD12560D70C9}', 'Buffer' => $jdata['Buffer']]));
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
        $this->SetRefreshToken('');
        $this->SetAccessToken('');
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

        $connection_type = $this->ReadPropertyInteger('connection_type');
        switch ($connection_type) {
            case self::$CONNECTION_OAUTH:
                $url = 'https://oauth.ipmagic.de/proxy/husqvarna/v1/' . $cmd;
                $api_key = '';
                break;
            case self::$CONNECTION_DEVELOPER:
                $url = 'https://api.amc.husqvarna.dev/v1/' . $cmd;
                $api_key = $this->ReadPropertyString('api_key');
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unknown connection_type ' . $connection_type, 0);
                return;
        }

        $access_token = $this->GetApiAccessToken();

        if (IPS_SemaphoreEnter($this->SemaphoreID, $this->GetSemaphoreTM()) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return;
        }

        $ts = intval($this->GetBuffer('LastApiCall'));
        if ($ts == time()) {
            $this->SendDebug(__FUNCTION__, 'multiple calls/second - wait a little bit', 0);
            while ($ts == time()) {
                IPS_Sleep(100);
            }
        }

        $header = [
            'Accept: application/vnd.api+json',
            'Content-Type: application/vnd.api+json',
            'Authorization: Bearer ' . $access_token,
            'Authorization-Provider: husqvarna',
        ];
        if ($api_key != '') {
            $header[] = 'X-Api-Key: ' . $api_key;
        }

        $cdata = $this->do_HttpRequest($url, $header, $postdata);

        $this->MaintainStatus(IS_ACTIVE);

        $this->SetBuffer('LastApiCall', time());

        IPS_SemaphoreLeave($this->SemaphoreID);

        return $cdata;
    }

    private function do_HttpRequest($url, $header = '', $postdata = '')
    {
        $curl_exec_timeout = $this->ReadPropertyInteger('curl_exec_timeout');
        $curl_exec_attempts = $this->ReadPropertyInteger('curl_exec_attempts');
        $curl_exec_delay = $this->ReadPropertyFloat('curl_exec_delay');

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

        $attempt = 1;
        do {
            $cdata = curl_exec($ch);
            $cerrno = curl_errno($ch);
            $cerror = $cerrno ? curl_error($ch) : '';
            if ($cerrno) {
                $this->SendDebug(__FUNCTION__, ' => attempt=' . $attempt . ', got curl-errno ' . $cerrno . ' (' . $cerror . ')', 0);
                IPS_Sleep((int) floor($curl_exec_delay * 1000));
            }
        } while ($cerrno && $attempt++ <= $curl_exec_attempts);

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's, attempts=' . $attempt, 0);
        $this->SendDebug(__FUNCTION__, ' => cdata=' . $cdata, 0);

        $statuscode = 0;
        $err = '';
        $data = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200 && $httpcode != 201) {
            if ($httpcode == 202) {
                // 202 = command queued
                $data = json_encode(['status' => 'ok']);
            } elseif ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
                // force new Token
                $this->SetAccessToken('');
            } elseif ($httpcode == 400 || $httpcode == 404) {
                // 400 = Failure, bad request
                // 404 = Failure, requested resource not found (e.a.)
                $jdata = json_decode($cdata, true);
                if ($jdata == '') {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'malformed response';
                } else {
                    if (isset($jdata['errors'][0]['code'])) {
                        $code = $jdata['errors'][0]['code'];
                        $data = json_encode(['status' => $code]);
                    } else {
                        $data = json_encode(['status' => 'failure']);
                    }
                }
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
                $data = $cdata;
            }
        }

        if ($statuscode) {
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

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

        $this->UpdateConfigurationForParent();

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

    private function build_url($url, $params)
    {
        $n = 0;
        if (is_array($params)) {
            foreach ($params as $param => $value) {
                $url .= ($n++ ? '&' : '?') . $param . '=' . rawurlencode(strval($value));
            }
        }
        return $url;
    }

    private function build_header($headerfields)
    {
        $header = [];
        foreach ($headerfields as $key => $value) {
            $header[] = $key . ': ' . $value;
        }
        return $header;
    }
}
