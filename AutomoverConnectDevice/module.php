<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/library.php';  // modul-bezogene Funktionen

if (@constant('IPS_BASE') == null) {
    // --- BASE MESSAGE
    define('IPS_BASE', 10000);							// Base Message
    define('IPS_KERNELSHUTDOWN', IPS_BASE + 1);			// Pre Shutdown Message, Runlevel UNINIT Follows
    define('IPS_KERNELSTARTED', IPS_BASE + 2);			// Post Ready Message
    // --- KERNEL
    define('IPS_KERNELMESSAGE', IPS_BASE + 100);		// Kernel Message
    define('KR_CREATE', IPS_KERNELMESSAGE + 1);			// Kernel is beeing created
    define('KR_INIT', IPS_KERNELMESSAGE + 2);			// Kernel Components are beeing initialised, Modules loaded, Settings read
    define('KR_READY', IPS_KERNELMESSAGE + 3);			// Kernel is ready and running
    define('KR_UNINIT', IPS_KERNELMESSAGE + 4);			// Got Shutdown Message, unloading all stuff
    define('KR_SHUTDOWN', IPS_KERNELMESSAGE + 5);		// Uninit Complete, Destroying Kernel Inteface
    // --- KERNEL LOGMESSAGE
    define('IPS_LOGMESSAGE', IPS_BASE + 200);			// Logmessage Message
    define('KL_MESSAGE', IPS_LOGMESSAGE + 1);			// Normal Message
    define('KL_SUCCESS', IPS_LOGMESSAGE + 2);			// Success Message
    define('KL_NOTIFY', IPS_LOGMESSAGE + 3);			// Notiy about Changes
    define('KL_WARNING', IPS_LOGMESSAGE + 4);			// Warnings
    define('KL_ERROR', IPS_LOGMESSAGE + 5);				// Error Message
    define('KL_DEBUG', IPS_LOGMESSAGE + 6);				// Debug Informations + Script Results
    define('KL_CUSTOM', IPS_LOGMESSAGE + 7);			// User Message
}

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

// normalized MowerStatus
if (!defined('AUTOMOWER_ACTIVITY_ERROR')) {
    define('AUTOMOWER_ACTIVITY_ERROR', -1);
}
if (!defined('AUTOMOWER_ACTIVITY_DISABLED')) {
    define('AUTOMOWER_ACTIVITY_DISABLED', 0);
}
if (!defined('AUTOMOWER_ACTIVITY_PARKED')) {
    define('AUTOMOWER_ACTIVITY_PARKED', 1);
}
if (!defined('AUTOMOWER_ACTIVITY_CHARGING')) {
    define('AUTOMOWER_ACTIVITY_CHARGING', 2);
}
if (!defined('AUTOMOWER_ACTIVITY_PAUSED')) {
    define('AUTOMOWER_ACTIVITY_PAUSED', 3);
}
if (!defined('AUTOMOWER_ACTIVITY_MOVING')) {
    define('AUTOMOWER_ACTIVITY_MOVING', 4);
}
if (!defined('AUTOMOWER_ACTIVITY_CUTTING')) {
    define('AUTOMOWER_ACTIVITY_CUTTING', 5);
}

if (!defined('AUTOMOWER_ACTION_PARK')) {
    define('AUTOMOWER_ACTION_PARK', 0);
}
if (!defined('AUTOMOWER_ACTION_START')) {
    define('AUTOMOWER_ACTION_START', 1);
}
if (!defined('AUTOMOWER_ACTION_STOP')) {
    define('AUTOMOWER_ACTION_STOP', 2);
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

        $associations = [];
        $associations[] = ['Wert' => AUTOMOWER_ACTION_PARK, 'Name' => $this->Translate('park'), 'Farbe' => -1];
        $associations[] = ['Wert' => AUTOMOWER_ACTION_START, 'Name' => $this->Translate('start'), 'Farbe' => -1];
        $associations[] = ['Wert' => AUTOMOWER_ACTION_STOP, 'Name' => $this->Translate('stop'), 'Farbe' => -1];
        $this->CreateVarProfile('Automower.Action', IPS_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [];
        $associations[] = ['Wert' => AUTOMOWER_ACTIVITY_ERROR, 'Name' => $this->Translate('error'), 'Farbe' => -1];
        $associations[] = ['Wert' => AUTOMOWER_ACTIVITY_DISABLED, 'Name' => $this->Translate('disabled'), 'Farbe' => -1];
        $associations[] = ['Wert' => AUTOMOWER_ACTIVITY_PARKED, 'Name' => $this->Translate('parked'), 'Farbe' => -1];
        $associations[] = ['Wert' => AUTOMOWER_ACTIVITY_CHARGING, 'Name' => $this->Translate('charging'), 'Farbe' => -1];
        $associations[] = ['Wert' => AUTOMOWER_ACTIVITY_PAUSED, 'Name' => $this->Translate('paused'), 'Farbe' => -1];
        $associations[] = ['Wert' => AUTOMOWER_ACTIVITY_MOVING, 'Name' => $this->Translate('moving'), 'Farbe' => -1];
        $associations[] = ['Wert' => AUTOMOWER_ACTIVITY_CUTTING, 'Name' => $this->Translate('cutting'), 'Farbe' => -1];
        $this->CreateVarProfile('Automower.Activity', IPS_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [];
        $associations[] = ['Wert' =>  0, 'Name' => '-', 'Farbe' => -1];
        $associations[] = ['Wert' =>  1, 'Name' => $this->Translate('outside mowing area'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  2, 'Name' => $this->Translate('empty battery'), 'Farbe' => -1];
        $associations[] = ['Wert' => 10, 'Name' => $this->Translate('upside down'), 'Farbe' => -1];
        $associations[] = ['Wert' => 15, 'Name' => $this->Translate('lifted'), 'Farbe' => -1];
        $associations[] = ['Wert' => 18, 'Name' => $this->Translate('problem with rear bumper'), 'Farbe' => -1];
        $associations[] = ['Wert' => 19, 'Name' => $this->Translate('problem with front bumper'), 'Farbe' => -1];
        $this->CreateVarProfile('Automower.Error', IPS_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $this->CreateVarProfile('Automower.Battery', IPS_INTEGER, ' %', 0, 0, 0, 0, 'Battery');
        $this->CreateVarProfile('Automower.Location', IPS_FLOAT, ' °', 0, 0, 0, 5, '');
        $this->CreateVarProfile('Automower.Duration', IPS_INTEGER, ' min', 0, 0, 0, 0, 'Hourglass');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        $device_id = $this->ReadPropertyString('device_id');
        $model = $this->ReadPropertyString('model');

        $vpos = 0;
        $this->MaintainVariable('Connected', $this->Translate('Connected'), IPS_BOOLEAN, '~Alert.Reversed', $vpos++, true);
        $this->MaintainVariable('Battery', $this->Translate('Battery capacity'), IPS_INTEGER, 'Automower.Battery', $vpos++, true);
        $this->MaintainVariable('OperationMode', $this->Translate('Operation mode'), IPS_STRING, '', $vpos++, true);
        $this->MaintainVariable('MowerStatus', $this->Translate('Mower status'), IPS_STRING, '', $vpos++, true);
        $this->MaintainVariable('MowerActivity', $this->Translate('Mower activity'), IPS_INTEGER, 'Automower.Activity', $vpos++, true);
        $this->MaintainVariable('MowerAction', $this->Translate('Mower action'), IPS_INTEGER, 'Automower.Action', $vpos++, true);
        $this->MaintainVariable('NextStart', $this->Translate('Next start'), IPS_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('DailyReference', $this->Translate('Day of cumulation'), IPS_INTEGER, '~UnixTimestampDate', $vpos++, true);
        $this->MaintainVariable('DailyWorking', $this->Translate('Working time (day)'), IPS_INTEGER, 'Automower.Duration', $vpos++, true);
        $this->MaintainVariable('LastErrorCode', $this->Translate('Last error'), IPS_INTEGER, 'Automower.Error', $vpos++, true);
        $this->MaintainVariable('LastErrorTimestamp', $this->Translate('Timestamp of last error'), IPS_INTEGER, '~UnixTimestampDate', $vpos++, true);
        $this->MaintainVariable('LastLongitude', $this->Translate('Last position (longitude)'), IPS_FLOAT, 'Automower.Location', $vpos++, $model == 'G');
        $this->MaintainVariable('LastLatitude', $this->Translate('Last position (latitude)'), IPS_FLOAT, 'Automower.Location', $vpos++, $model == 'G');
        $this->MaintainVariable('LastStatus', $this->Translate('Last status'), IPS_INTEGER, '~UnixTimestamp', $vpos++, true);

        $this->MaintainAction('MowerAction', true);

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
            $this->SetValue('Connected', false);
            return false;
        }
        $status = json_decode($cdata, true);
        $this->SendDebug(__FUNCTION__, 'status=' . print_r($status, true), 0);

        $batteryPercent = $status['batteryPercent'];
        $this->SetValue('Battery', $batteryPercent);

        $connected = $status['connected'];
        $this->SetValue('Connected', $connected);

        $mowerStatus = $this->decode_mowerStatus($status['mowerStatus']);
        $this->SetValue('MowerStatus', $mowerStatus);

        $mowerActivity = $this->normalize_mowerStatus($status['mowerStatus']);
        $this->SetValue('MowerActivity', $mowerActivity);

        switch ($mowerActivity) {
            case AUTOMOWER_ACTIVITY_DISABLED:
            case AUTOMOWER_ACTIVITY_PAUSED:
            case AUTOMOWER_ACTIVITY_PARKED:
            case AUTOMOWER_ACTIVITY_CHARGING:
                $action = AUTOMOWER_ACTION_START;
                break;
            case AUTOMOWER_ACTIVITY_MOVING:
            case AUTOMOWER_ACTIVITY_CUTTING:
                $action = AUTOMOWER_ACTION_PARK;
                break;
            default:
                $action = AUTOMOWER_ACTION_STOP;
                break;
        }
        $this->SetValue('MowerAction', $action);

        $nextStartSource = $status['nextStartSource'];

        $nextStartTimestamp = $status['nextStartTimestamp'];
        if ($nextStartTimestamp > 0) {
            // 'nextStartTimestamp' ist nicht UTC sondern auf localtime umgerechnet.
            $ts = strtotime(gmdate('Y-m-d H:i', $nextStartTimestamp));
        } else {
            $ts = 0;
        }
        $this->SetValue('NextStart', $ts);

        $operatingMode = $this->decode_operatingMode($status['operatingMode']);
        $this->SetValue('OperationMode', $operatingMode);

        if (isset($status['lastLocations'][0]['longitude'])) {
            $lon = $status['lastLocations'][0]['longitude'];
            $this->SetValue('LastLongitude', $lon);
        }
        if (isset($status['lastLocations'][0]['latitude'])) {
            $lat = $status['lastLocations'][0]['latitude'];
            $this->SetValue('LastLatitude', $lat);
        }

        $this->SetValue('LastStatus', time());

        $lastErrorCode = $status['lastErrorCode'];
        $lastErrorCodeTimestamp = $status['lastErrorCodeTimestamp'];
        if ($lastErrorCode) {
            $msg = __FUNCTION__ . ': error-code=' . $lastErrorCode . ' @' . date('d-m-Y H:i:s', $lastErrorCodeTimestamp);
            $this->LogMessage($msg, KL_WARNING);
        } else {
            $lastErrorCodeTimestamp = 0;
        }
        $this->SetValue('LastErrorCode', $lastErrorCode);
        $this->SetValue('LastErrorTimestamp', $lastErrorCodeTimestamp);

        $dt = new DateTime(date('d.m.Y 00:00:00'));
        $ts_today = $dt->format('U');
        $ts_watch = $this->GetValue('DailyReference');
        if ($ts_today != $ts_watch) {
            $this->SetValue('DailyReference', $ts_today);
            $this->SetValue('DailyWorking', 0);
        }
        switch ($mowerActivity) {
            case AUTOMOWER_ACTIVITY_MOVING:
            case AUTOMOWER_ACTIVITY_CUTTING:
                $isWorking = true;
                break;
            default:
                $isWorking = false;
                break;
        }
        $tstamp = $this->GetBuffer('Working');
        $this->SendDebug(__FUNCTION__, 'isWorking=' . $isWorking . ', tstamp[GET]=' . $tstamp, 0);
        if ($tstamp != '') {
            $daily_working = $this->GetBuffer('DailyWorking');
            $duration = $daily_working + ((time() - $tstamp) / 60);
            $this->SetValue('DailyWorking', $duration);
            $this->SendDebug(__FUNCTION__, 'daily_working[GET]=' . $daily_working . ', duration=' . $duration, 0);
            if (!$isWorking) {
                $tstamp = '';
                $this->SetBuffer('Working', '');
                $this->SetBuffer('DailyWorking', 0);
                $this->SendDebug(__FUNCTION__, 'tstamp[CLR], daily_working[CLR]', 0);
            }
        } else {
            if ($isWorking) {
                $tstamp = time();
                $this->SetBuffer('Working', $tstamp);
                $daily_working = $this->GetValue('DailyWorking');
                $this->SetBuffer('DailyWorking', $daily_working);
                $this->SendDebug(__FUNCTION__, 'tstamp[SET]=' . $tstamp . ', daily_working[SET]=' . $daily_working, 0);
            }
        }

		$this->SetBuffer('LastLocations', json_encode($status['lastLocations']));
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

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'MowerAction':
                $this->SendDebug(__FUNCTION__, "$Ident=$Value", 0);
                switch ($Value) {
                    case AUTOMOWER_ACTION_PARK:
                        $this->ParkMower();
                        break;
                    case AUTOMOWER_ACTION_START:
                        $this->StartMower();
                        break;
                    case AUTOMOWER_ACTION_STOP:
                        $this->StopMower();
                        break;
                    default:
                        $this->SendDebug(__FUNCTION__, "invalid value \"$Value\" for $Ident", 0);
                        break;
                }
                break;
            default:
                $this->SendDebug(__FUNCTION__, "invalid ident $Ident", 0);
                break;
        }
    }

    private function decode_operatingMode($val)
    {
        $val2txt = [
                'AUTO'               => 'automatic',
                'MAIN_AREA'          => 'main area',
                'SECONDARY_AREA'     => 'secondary area',
                'OVERRIDE_TIMER'     => 'override timer',
                'SPOT_CUTTING'       => 'spot cutting',
            ];

        if (isset($val2txt[$val])) {
            $txt = $this->Translate($val2txt[$val]);
        } else {
            $msg = 'unknown value "' . $val . '"';
            $this->LogMessage(__FUNCTION__ . ': ' . $msg, KL_WARNING);
            $this->SendDebug(__FUNCTION__, $msg, 0);
            $txt = $val;
        }
        return $txt;
    }

    private function decode_mowerStatus($val)
    {
        $val2txt = [
                'ERROR'                       => 'error',

                'OK_CUTTING'                  => 'cutting',
                'OK_CUTTING_NOT_AUTO'         => 'manual cutting',
                'OK_CUTTING_TIMER_OVERRIDDEN' => 'manual cutting',

                'PARKED_TIMER'                => 'parked',
                'PARKED_PARKED_SELECTED'      => 'manual parked',

                'PAUSED'                      => 'paused',

                'OFF_DISABLED'                => 'disabled',
                'OFF_HATCH_OPEN'              => 'hatch open',
                'OFF_HATCH_CLOSED'            => 'hatch closed',

                'OK_SEARCHING'                => 'searching base',
                'OK_LEAVING'                  => 'leaving base',

                'OK_CHARGING'                 => 'charging',
            ];

        if (isset($val2txt[$val])) {
            $txt = $this->Translate($val2txt[$val]);
        } else {
            $msg = 'unknown value "' . $val . '"';
            $this->LogMessage(__FUNCTION__ . ': ' . $msg, KL_WARNING);
            $this->SendDebug(__FUNCTION__, $msg, 0);
            $txt = $val;
        }
        return $txt;
    }

    private function normalize_mowerStatus($val)
    {
        $val2code = [
                'ERROR'                       => AUTOMOWER_ACTIVITY_ERROR,

                'OK_CUTTING'                  => AUTOMOWER_ACTIVITY_CUTTING,
                'OK_CUTTING_NOT_AUTO'         => AUTOMOWER_ACTIVITY_CUTTING,
                'OK_CUTTING_TIMER_OVERRIDDEN' => AUTOMOWER_ACTIVITY_CUTTING,

                'PARKED_TIMER'                => AUTOMOWER_ACTIVITY_PARKED,
                'PARKED_PARKED_SELECTED'      => AUTOMOWER_ACTIVITY_PARKED,

                'PAUSED'                      => AUTOMOWER_ACTIVITY_PAUSED,

                'OFF_DISABLED'                => AUTOMOWER_ACTIVITY_DISABLED,
                'OFF_HATCH_OPEN'              => AUTOMOWER_ACTIVITY_DISABLED,
                'OFF_HATCH_CLOSED'            => AUTOMOWER_ACTIVITY_DISABLED,

                'OK_SEARCHING'                => AUTOMOWER_ACTIVITY_MOVING,
                'OK_LEAVING'                  => AUTOMOWER_ACTIVITY_MOVING,

                'OK_CHARGING'                 => AUTOMOWER_ACTIVITY_CHARGING,
            ];

        if (isset($val2code[$val])) {
            $code = $val2code[$val];
        } else {
            $msg = 'unknown value "' . $val . '"';
            $this->LogMessage(__FUNCTION__ . ': ' . $msg, KL_WARNING);
            $this->SendDebug(__FUNCTION__, $msg, 0);
            $code = AUTOMOWER_ACTIVITY_ERROR;
        }
        return $code;
    }

    public function ParkMower()
    {
        return $this->MowerCmd('PARK');
    }

    public function StartMower()
    {
        return $this->MowerCmd('START');
    }

    public function StopMower()
    {
        return $this->MowerCmd('STOP');
    }

    private function MowerCmd($cmd)
    {
        $device_id = $this->ReadPropertyString('device_id');

        $postdata = [
                'action' => $cmd
            ];

        $cdata = $this->do_ApiCall($this->url_track . 'mowers/' . $device_id . '/control', $postdata);
        if ($cdata == '') {
            return false;
        }
        $jdata = json_decode($cdata, true);
        $status = $jdata['status'];
        if ($status == 'OK') {
            return true;
        }
        $errorCode = $jdata['errorCode'];
        $this->SendDebug(__FUNCTION__, 'command failed, status=' . $status . ', errorCode=' . $errorCode, 0);
    }

    protected function SetBuffer($name, $data)
	{
		$this->SendDebug(__FUNCTION__, 'name=' . $name . ', size=' . strlen($data) . ', data=' . $data, 0);
		parent::SetBuffer($name, $data);
	}

	public function GetRawData(string $name)
	{
		$data = $this->GetBuffer($name);
		$this->SendDebug(__FUNCTION__, 'name=' . $name . ', size=' . strlen($data) . ', data=' . $data, 0);
		return $data;
	}
}
