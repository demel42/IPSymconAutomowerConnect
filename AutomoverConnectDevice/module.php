<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/library.php';  // modul-bezogene Funktionen

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

class AutomowerDevice extends IPSModule
{
    use AutomowerCommon;
    use AutomowerLibrary;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('device_id', '');
        $this->RegisterPropertyString('model', '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        $device_id = $this->ReadPropertyString('device_id');

        $ok = true;
        if ($user == '' || $password == '' || $device_id == '') {
            $ok = false;
        }
        $this->SetStatus($ok ? 102 : 201);

        $this->SetSummary($device_id);
    }

    public function TestAccount()
    {
        $mowers = $this->GetMowerList();
        foreach ($mowers as $mower) {
            $device_id = $mower['id'];
            $name = $mower['name'];
            $model = $mower['model'];
            $this->SendDebug(__FUNCTION__, 'device_id=' . $device_id . ', name=' . $name . ', model=' . $model, 0);
        }

        /*
            jdata=[
                    {
                        "id":"174300218-172830223"
                        "name":"Automower"
                        "model":"G"
                        "valueFound":true
                            "status": {
                                "batteryPercent":100
                                "connected":true
                                "lastErrorCode":0
                                "lastErrorCodeTimestamp":0
                                "mowerStatus":"PARKED_PARKED_SELECTED"
                                "nextStartSource":"COUNTDOWN_TIMER"
                                "nextStartTimestamp":1528707634
                                "operatingMode":"AUTO"
                                "storedTimestamp":1528552264029
                                "showAsDisconnected":false
                                "valueFound":true
                            }
                    }
                ]
        */
    }
}
