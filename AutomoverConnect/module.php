<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

if (!defined('IPS_BOOLEAN')) {
    define('IPS_BOOLEAN', 0);
}
if (!defined('IPS_INTEGER')) {
    define('IPS_INTEGER', 1);
}
if (!defined('IPS_FLOAT')) {
    define('IPS_FLOAT', 2);
}
if (!defined('IPS_STRING')) {
    define('IPS_STRING', 3);
}

class Automower extends IPSModule
{
    use AutomowerCommon;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');

        $ok = true;
        if ($user == '' || $password == '') {
            $ok = false;
        }
        $this->SetStatus($ok ? 102 : 201);
    }

    public function TestAccount()
    {
    }

    private function getToken()
    {
        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');

        $dtoken = $this->GetBuffer('Token');
        $jtoken = json_decode($dtoken, true);
        $token = isset($jtoken['token']) ? $jtoken['token'] : '';
        $token_expiration = isset($jtoken['token_expiration']) ? $jtoken['token_expiration'] : 0;

        if ($token_expiration < time()) {
            $postdata = [
                    'username' => $user,
                    'password' => $password
                ];

            $header = [
                    'Accept: application/json',
                    'Content-Type: application/json'
                ];

            $ctoken = $this->do_HttpRequest('/authorization/token', $header, $postdata, true);
            $this->SendDebug(__FUNCTION__, 'ctoken=' . print_r($ctoken, true), 0);
            if ($ctoken == '') {
                return false;
            }
            $jtoken = json_decode($ctoken, true);
            $token = $jtoken['token'];

            $jtoken = [
                    'token'            => $token,
                    'token_expiration' => time() + 300
                ];
            $this->SetBuffer('Token', json_encode($jtoken));
        }

        return $token;
    }

    private function do_ApiCall($cmd_url, $postdata = '', $isJson = true, $customrequest = '')
    {
        $token = $this->getToken();
        if ($token == '') {
            return false;
        }

        $header = [];
        $header[] = 'Accept: application/json';

        if ($postdata != '') {
            $header[] = 'Content-Type: application/json';
            $header[] = 'Content-Length: ' . strlen(json_encode($postdata));
        } elseif ($customrequest == '') {
            $header[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $header[] = 'Authorization: Bearer ' . $token;

        $cdata = $this->do_HttpRequest($cmd_url, $header, $postdata, $isJson, $customrequest);
        $this->SendDebug(__FUNCTION__, 'cdata=' . print_r($cdata, true), 0);

        $this->SetStatus(102);
        return $cdata;
    }

    private function do_HttpRequest($cmd_url, $header = '', $postdata = '', $isJson = true, $customrequest = '')
    {
        $base_url = 'https://api.sipgate.com/v2';

        $url = $base_url . $cmd_url;

        if ($customrequest != '') {
            $req = $customrequest;
        } elseif ($postdata != '') {
            $req = 'post';
        } else {
            $req = 'get';
        }
        //$req = $customrequest != '' ? $customrequest : $postdata != '' ? 'post' : 'get';
        $this->SendDebug(__FUNCTION__, 'cmd_url=' . $cmd_url . ', customrequest=' . $customrequest . ', req=' . $req, 0);

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
            if ($customrequest == '') {
                curl_setopt($ch, CURLOPT_POST, true);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
        }
        if ($customrequest != '') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $customrequest);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $cdata = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $duration = floor((microtime(true) - $time_start) * 100) / 100;
        $this->SendDebug(__FUNCTION__, ' => httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        $data = '';
        if ($httpcode != 200) {
            if ($httpcode == 401) {
                $statuscode = 201;
                $err = "got http-code $httpcode (unauthorized)";
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = 202;
                $err = "got http-code $httpcode (server error)";
            } elseif ($httpcode == 204) {
                // 204 = No Content	= Die Anfrage wurde erfolgreich durchgeführt, die Antwort enthält jedoch bewusst keine Daten.
                // kommt zB bei senden von SMS
                $data = json_encode(['status' => 'ok']);
            } else {
                $statuscode = 203;
                $err = "got http-code $httpcode";
            }
        } elseif ($cdata == '') {
            $statuscode = 204;
            $err = 'no data';
        } else {
            if ($isJson) {
                $jdata = json_decode($cdata, true);
                if ($jdata == '') {
                    $statuscode = 204;
                    $err = 'malformed response';
                } else {
                    $data = $cdata;
                }
            } else {
                $data = $cdata;
            }
        }

        if ($statuscode) {
            echo "url=$url => statuscode=$statuscode, err=$err\n";
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->SetStatus($statuscode);
        }

        return $data;
    }
}
