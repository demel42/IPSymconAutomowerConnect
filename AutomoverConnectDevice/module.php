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

		$this->RegisterPropertyInteger('update_interval', '5');

		$this->RegisterTimer('UpdateStatus', 0, 'AutomowerDevice_UpdateStatus(' . $this->InstanceID . ');');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        $device_id = $this->ReadPropertyString('device_id');

        if ($user != '' || $password != '' || $device_id != '') {
			$this->RegisterMessage(0, IPS_KERNELMESSAGE);
			$this->SetUpdateInterval();
			$this->SetStatus(102);
		} else {
			$this->SetStatus(104);
		}

        $this->SetSummary($device_id);
    }

	// Inspired by module SymconTest/HookServe
	public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
	{
		parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

		if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
			$this->UpdateStatus();
		}
	}

	protected function SetUpdateInterval()
	{
		$min = $this->ReadPropertyInteger('update_interval');
		$msec = $min > 0 ? $min * 1000 * 60 : 0;
		$this->SetTimerInterval('UpdateStatus', $msec);
	}

	public function UpdateStatus()
	{
        $device_id = $this->ReadPropertyString('device_id');

        $cdata = $this->do_ApiCall($this->url_track . 'mowers/' . $device_id . '/status');
        if ($cdata == '') {
            return false;
        }
        $status = json_decode($cdata, true);
		$this->SendDebug(__FUNCTION__, 'status=' . print_r($status, true), 0);
	}

	public function TestAccount()
    {
        $device_id = $this->ReadPropertyString('device_id');

        $mowers = $this->GetMowerList();
        if ($mowers == '') {
            $this->SetStatus(201);
            echo $this->Translate('invalid account-data');
            return;
        }

        $msg = '';
        $mower_found = false;
        foreach ($mowers as $mower) {
            if ($device_id == $mower['id']) {
                $mower_found = true;
            }
            $name = $mower['name'];
            $model = $mower['model'];

            $msg = $this->Translate('mower') . ' "' . $name . '", ' . $this->Translate('model') . '=' . $model;
            $this->SendDebug(__FUNCTION__, 'device_id=' . $device_id . ', name=' . $name . ', model=' . $model, 0);
        }

        if (!$mower_found) {
            $this->SetStatus(205);
            echo $this->Translate('device not found');
            return;
        }

        echo $this->translate('valid account-data') . "\n" . $msg;
    }
}
