<?php

if (!defined('IS_UNAUTHORIZED')) {
    define('IS_UNAUTHORIZED', IS_EBASE + 1);
    define('IS_SERVERERROR', IS_EBASE + 2);
    define('IS_HTTPERROR', IS_EBASE + 3);
    define('IS_INVALIDDATA', IS_EBASE + 4);
    define('IS_DEVICE_MISSING', IS_EBASE + 5);
}

trait AutomowerLibrary
{
    private $url_im = 'https://iam-api.dss.husqvarnagroup.net/api/v3/';
    private $url_track = 'https://amc-api.dss.husqvarnagroup.net/v1/';

    public function GetMowerList()
    {
        $cdata = $this->do_ApiCall($this->url_track . 'mowers');
        if ($cdata == '') {
            return false;
        }
        $mowers = json_decode($cdata, true);
        return $mowers;
    }

    private function getToken()
    {
        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');

        $dtoken = $this->GetBuffer('Token');
        $jtoken = json_decode($dtoken, true);
        $token = isset($jtoken['token']) ? $jtoken['token'] : '';
        $provider = isset($jtoken['provider']) ? $jtoken['provider'] : '';
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

            $ctoken = $this->do_HttpRequest($this->url_im . 'token', $header, $postdata, true);
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
            $this->SetBuffer('Token', json_encode($jtoken));
        }

        return $jtoken;
    }

    private function do_ApiCall($url, $postdata = '')
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $jtoken = $this->getToken();
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
            $statuscode = IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200 && $httpcode != 201) {
            if ($httpcode == 401) {
                $statuscode = IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode == 204) {
                // 204 = No Content	= Die Anfrage wurde erfolgreich durchgeführt, die Antwort enthält jedoch bewusst keine Daten.
                // kommt zB bei senden von SMS
                $data = json_encode(['status' => 'ok']);
            } else {
                $statuscode = IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode;
            }
        } elseif ($cdata == '') {
            $statuscode = IS_INVALIDDATA;
            $err = 'no data';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = IS_INVALIDDATA;
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
}
