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
