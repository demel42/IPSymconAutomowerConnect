<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';	// globale Funktionen
require_once __DIR__ . '/../libs/local.php';	// lokale Funktionen

class AutomowerDevice extends IPSModule
{
    use AutomowerCommonLib;
    use AutomowerLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('device_id', '');
        $this->RegisterPropertyString('model', '');

        $this->RegisterPropertyBoolean('with_gps', true);
        $this->RegisterPropertyBoolean('save_position', false);

        $this->RegisterPropertyInteger('update_interval', '5');

        $this->RegisterTimer('UpdateStatus', 0, 'Automower_UpdateStatus(' . $this->InstanceID . ');');
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        $associations = [];
        $associations[] = ['Wert' => self::$AUTOMOWER_ACTION_PARK, 'Name' => $this->Translate('park'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$AUTOMOWER_ACTION_START, 'Name' => $this->Translate('start'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$AUTOMOWER_ACTION_STOP, 'Name' => $this->Translate('stop'), 'Farbe' => -1];
        $this->CreateVarProfile('Automower.Action', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [];
        $associations[] = ['Wert' => self::$AUTOMOWER_ACTIVITY_ERROR, 'Name' => $this->Translate('error'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$AUTOMOWER_ACTIVITY_DISABLED, 'Name' => $this->Translate('disabled'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$AUTOMOWER_ACTIVITY_PARKED, 'Name' => $this->Translate('parked'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$AUTOMOWER_ACTIVITY_CHARGING, 'Name' => $this->Translate('charging'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$AUTOMOWER_ACTIVITY_PAUSED, 'Name' => $this->Translate('paused'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$AUTOMOWER_ACTIVITY_MOVING, 'Name' => $this->Translate('moving'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$AUTOMOWER_ACTIVITY_CUTTING, 'Name' => $this->Translate('cutting'), 'Farbe' => -1];
        $this->CreateVarProfile('Automower.Activity', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [];
        $associations[] = ['Wert' =>  0, 'Name' => '-', 'Farbe' => -1];
        $associations[] = ['Wert' =>  1, 'Name' => $this->Translate('Outside working area'), 'Farbe' => 0xFFA500];
        $associations[] = ['Wert' =>  2, 'Name' => $this->Translate('No loop signal'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' =>  3, 'Name' => $this->Translate('Wrong loop signal'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' =>  4, 'Name' => $this->Translate('Loop sensor problem, front'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' =>  5, 'Name' => $this->Translate('Loop sensor problem, rear'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' =>  6, 'Name' => $this->Translate('Loop sensor problem, left'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' =>  7, 'Name' => $this->Translate('Loop sensor problem, right'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' =>  8, 'Name' => $this->Translate('Wrong PIN code'), 'Farbe' => 0x9932CC];
        $associations[] = ['Wert' =>  9, 'Name' => $this->Translate('Trapped'), 'Farbe' => 0x1874CD];
        $associations[] = ['Wert' => 10, 'Name' => $this->Translate('Upside down'), 'Farbe' => 0x1874CD];
        $associations[] = ['Wert' => 11, 'Name' => $this->Translate('Low battery'), 'Farbe' => 0x1874CD];
        $associations[] = ['Wert' => 12, 'Name' => $this->Translate('Empty battery'), 'Farbe' => 0xFFA500];
        $associations[] = ['Wert' => 13, 'Name' => $this->Translate('No drive'), 'Farbe' => 0x1874CD];
        $associations[] = ['Wert' => 14, 'Name' => $this->Translate('Mower lifted'), 'Farbe' => 0x1874CD];
        $associations[] = ['Wert' => 15, 'Name' => $this->Translate('Lifted'), 'Farbe' => 0x1874CD];
        $associations[] = ['Wert' => 16, 'Name' => $this->Translate('Stuck in charging station'), 'Farbe' => 0xFFA500];
        $associations[] = ['Wert' => 17, 'Name' => $this->Translate('Charging station blocked'), 'Farbe' => 0xFFA500];
        $associations[] = ['Wert' => 18, 'Name' => $this->Translate('Collision sensor problem, rear'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 19, 'Name' => $this->Translate('Collision sensor problem, front'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 20, 'Name' => $this->Translate('Wheel motor blocked, right'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 21, 'Name' => $this->Translate('Wheel motor blocked, left'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 22, 'Name' => $this->Translate('Wheel drive problem, right'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 23, 'Name' => $this->Translate('Wheel drive problem, left'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 24, 'Name' => $this->Translate('Cutting system blocked'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 25, 'Name' => $this->Translate('Cutting system blocked'), 'Farbe' => 0xFFA500];
        $associations[] = ['Wert' => 26, 'Name' => $this->Translate('Invalid sub-device combination'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 27, 'Name' => $this->Translate('Settings restored'), 'Farbe' => -1];
        $associations[] = ['Wert' => 28, 'Name' => $this->Translate('Memory circuit problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 29, 'Name' => $this->Translate('Slope too steep'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 30, 'Name' => $this->Translate('Charging system problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 31, 'Name' => $this->Translate('STOP button problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 32, 'Name' => $this->Translate('Tilt sensor problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 33, 'Name' => $this->Translate('Mower tilted'), 'Farbe' => 0x1874CD];
        $associations[] = ['Wert' => 34, 'Name' => $this->Translate('Cutting stopped - slope too steep'), 'Farbe' => 0x1874CD];
        $associations[] = ['Wert' => 35, 'Name' => $this->Translate('Wheel motor overloaded, right'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 36, 'Name' => $this->Translate('Wheel motor overloaded, left'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 37, 'Name' => $this->Translate('Charging current too high'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 38, 'Name' => $this->Translate('Electronic problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 39, 'Name' => $this->Translate('Cutting motor problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 40, 'Name' => $this->Translate('Limited cutting height range'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 41, 'Name' => $this->Translate('Unexpected cutting height adj'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 42, 'Name' => $this->Translate('Limited cutting height range'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 43, 'Name' => $this->Translate('Cutting height problem, drive'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 44, 'Name' => $this->Translate('Cutting height problem, curr'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 45, 'Name' => $this->Translate('Cutting height problem, dir'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 46, 'Name' => $this->Translate('Cutting height blocked'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 47, 'Name' => $this->Translate('Cutting height problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 48, 'Name' => $this->Translate('No response from charger'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 49, 'Name' => $this->Translate('Ultrasonic problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 50, 'Name' => $this->Translate('Guide 1 not found'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 51, 'Name' => $this->Translate('Guide 2 not found'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 52, 'Name' => $this->Translate('Guide 3 not found'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 53, 'Name' => $this->Translate('GPS navigation problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 54, 'Name' => $this->Translate('Weak GPS signal'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 55, 'Name' => $this->Translate('Difficult finding home'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 56, 'Name' => $this->Translate('Guide calibration accomplished'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 57, 'Name' => $this->Translate('Guide calibration failed'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 58, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 59, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 60, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 61, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 62, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 63, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 64, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 65, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 66, 'Name' => $this->Translate('Battery problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 67, 'Name' => $this->Translate('Battery problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 68, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 69, 'Name' => $this->Translate('Alarm! Mower switched off'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 70, 'Name' => $this->Translate('Alarm! Mower stopped'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 71, 'Name' => $this->Translate('Alarm! Mower lifted'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 72, 'Name' => $this->Translate('Alarm! Mower tilted'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 73, 'Name' => $this->Translate('Alarm! Mower in motion'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 74, 'Name' => $this->Translate('Alarm! Outside geofence'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 75, 'Name' => $this->Translate('Connection changed'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 76, 'Name' => $this->Translate('Connection NOT changed'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 77, 'Name' => $this->Translate('Com board not available'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 78, 'Name' => $this->Translate('Mower has slipped'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 79, 'Name' => $this->Translate('Invalid battery combination'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 80, 'Name' => $this->Translate('Cutting system imbalance Warning'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 81, 'Name' => $this->Translate('Safety function faulty'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 82, 'Name' => $this->Translate('Wheel motor blocked, rear right'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 83, 'Name' => $this->Translate('Wheel motor blocked, rear left'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 84, 'Name' => $this->Translate('Wheel drive problem, rear right'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 85, 'Name' => $this->Translate('Wheel drive problem, rear left'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 86, 'Name' => $this->Translate('Wheel motor overloaded, rear right'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 87, 'Name' => $this->Translate('Wheel motor overloaded, rear left'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 88, 'Name' => $this->Translate('Angular sensor problem'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 89, 'Name' => $this->Translate('Invalid system configuration'), 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 90, 'Name' => $this->Translate('No power in charging station'), 'Farbe' => 0xFF0000];
        $this->CreateVarProfile('Automower.Error', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [];
        $associations[] = ['Wert' => false, 'Name' => $this->Translate('Disconnected'), 'Farbe' => 0xEE0000];
        $associations[] = ['Wert' => true, 'Name' => $this->Translate('Connected'), 'Farbe' => -1];
        $this->CreateVarProfile('Automower.Connection', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 1, 'Alarm', $associations);

        $this->CreateVarProfile('Automower.Battery', VARIABLETYPE_INTEGER, ' %', 0, 0, 0, 0, 'Battery');
        $this->CreateVarProfile('Automower.Location', VARIABLETYPE_FLOAT, ' °', 0, 0, 0, 5, '');
        $this->CreateVarProfile('Automower.Duration', VARIABLETYPE_INTEGER, ' min', 0, 0, 0, 0, 'Hourglass');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        $device_id = $this->ReadPropertyString('device_id');
        $model = $this->ReadPropertyString('model');
        $with_gps = $this->ReadPropertyBoolean('with_gps');
        $save_position = $this->ReadPropertyBoolean('save_position');

        $vpos = 0;
        $this->MaintainVariable('Connected', $this->Translate('Connected'), VARIABLETYPE_BOOLEAN, 'Automower.Connection', $vpos++, true);
        $this->MaintainVariable('Battery', $this->Translate('Battery capacity'), VARIABLETYPE_INTEGER, 'Automower.Battery', $vpos++, true);
        $this->MaintainVariable('OperationMode', $this->Translate('Operation mode'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('MowerStatus', $this->Translate('Mower status'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('MowerActivity', $this->Translate('Mower activity'), VARIABLETYPE_INTEGER, 'Automower.Activity', $vpos++, true);
        $this->MaintainVariable('MowerAction', $this->Translate('Mower action'), VARIABLETYPE_INTEGER, 'Automower.Action', $vpos++, true);
        $this->MaintainVariable('NextStart', $this->Translate('Next start'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('DailyReference', $this->Translate('Day of cumulation'), VARIABLETYPE_INTEGER, '~UnixTimestampDate', $vpos++, true);
        $this->MaintainVariable('DailyWorking', $this->Translate('Working time (day)'), VARIABLETYPE_INTEGER, 'Automower.Duration', $vpos++, true);
        $this->MaintainVariable('LastErrorCode', $this->Translate('Last error'), VARIABLETYPE_INTEGER, 'Automower.Error', $vpos++, true);
        $this->MaintainVariable('LastErrorTimestamp', $this->Translate('Timestamp of last error'), VARIABLETYPE_INTEGER, '~UnixTimestampDate', $vpos++, true);
        $this->MaintainVariable('LastLongitude', $this->Translate('Last position (longitude)'), VARIABLETYPE_FLOAT, 'Automower.Location', $vpos++, $with_gps);
        $this->MaintainVariable('LastLatitude', $this->Translate('Last position (latitude)'), VARIABLETYPE_FLOAT, 'Automower.Location', $vpos++, $with_gps);
        $this->MaintainVariable('LastStatus', $this->Translate('Last status'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('Position', $this->Translate('Position'), VARIABLETYPE_STRING, '', $vpos++, $save_position);

        $this->MaintainAction('MowerAction', true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetTimerInterval('UpdateStatus', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        if ($user != '' || $password != '' || $device_id != '') {
            $this->SetUpdateInterval();
            if (IPS_GetKernelRunlevel() == KR_READY) {
                $this->UpdateStatus();
            }
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->SetStatus(IS_INACTIVE);
        }

        $this->SetSummary($device_id);
    }

    private function GetFormElements()
    {
        $formElements = [];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'module_disable', 'caption' => 'Instance is disabled'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'user', 'caption' => 'User'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'password', 'caption' => 'Password'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'device_id', 'caption' => 'Device-ID'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'model', 'caption' => 'Model'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_gps', 'caption' => 'with GPS-Data'];
        $formElements[] = ['type' => 'Label', 'caption' => 'save position to (logged) variable \'Position\''];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'save_position', 'caption' => 'save position'];
        $formElements[] = ['type' => 'Label', 'caption' => ''];
        $formElements[] = ['type' => 'Label', 'caption' => 'Update status every X minutes'];
        $formElements[] = ['type' => 'NumberSpinner', 'name' => 'update_interval', 'caption' => 'Minutes'];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];
        $formActions[] = ['type' => 'Button', 'caption' => 'Test account', 'onClick' => 'Automower_TestAccount($id);'];
        $formActions[] = ['type' => 'Button', 'caption' => 'Update status', 'onClick' => 'Automower_UpdateStatus($id);'];

        return $formActions;
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

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->UpdateStatus();
        }
    }

    public function SetUpdateInterval(int $sec = null)
    {
        if ($sec > 0) {
            $this->SendDebug(__FUNCTION__, 'override interval with sec=' . $sec, 0);
            $msec = $sec * 1000;
        } else {
            $min = $this->ReadPropertyInteger('update_interval');
            $this->SendDebug(__FUNCTION__, 'default interval with min=' . $min, 0);
            $msec = $min * 60 * 1000;
        }
        $this->SetTimerInterval('UpdateStatus', $msec);
    }

    public function UpdateStatus()
    {
        $device_id = $this->ReadPropertyString('device_id');
        $model = $this->ReadPropertyString('model');
        $with_gps = $this->ReadPropertyBoolean('with_gps');
        $save_position = $this->ReadPropertyBoolean('save_position');

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
        $this->SendDebug(__FUNCTION__, 'mowerStatus="' . $status['mowerStatus'] . '" => MowerStatus=' . $mowerStatus, 0);
        $this->SetValue('MowerStatus', $mowerStatus);

        $oldActivity = $this->GetValue('MowerActivity');
        switch ($oldActivity) {
            case self::$AUTOMOWER_ACTIVITY_MOVING:
            case self::$AUTOMOWER_ACTIVITY_CUTTING:
                $wasWorking = true;
                break;
            default:
                $wasWorking = false;
                break;
        }
        $this->SendDebug(__FUNCTION__, 'wasWorking=' . $wasWorking, 0);

        $mowerActivity = $this->normalize_mowerStatus($status['mowerStatus']);
        $this->SendDebug(__FUNCTION__, 'MowerActivity=' . $mowerActivity, 0);
        $this->SetValue('MowerActivity', $mowerActivity);

        switch ($mowerActivity) {
            case self::$AUTOMOWER_ACTIVITY_DISABLED:
            case self::$AUTOMOWER_ACTIVITY_PAUSED:
            case self::$AUTOMOWER_ACTIVITY_PARKED:
            case self::$AUTOMOWER_ACTIVITY_CHARGING:
                $action = self::$AUTOMOWER_ACTION_START;
                break;
            case self::$AUTOMOWER_ACTIVITY_MOVING:
            case self::$AUTOMOWER_ACTIVITY_CUTTING:
                $action = self::$AUTOMOWER_ACTION_PARK;
                break;
            default:
                $action = self::$AUTOMOWER_ACTION_STOP;
                break;
        }
        $this->SendDebug(__FUNCTION__, 'MowerAction=' . $action, 0);
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
        $this->SendDebug(__FUNCTION__, 'operatingMode="' . $status['operatingMode'] . '" => OperationMode=' . $operatingMode, 0);
        $this->SetValue('OperationMode', $operatingMode);

        if ($with_gps) {
            if (isset($status['lastLocations'][0]['longitude'])) {
                $lon = $status['lastLocations'][0]['longitude'];
                $this->SetValue('LastLongitude', $lon);
            }
            if (isset($status['lastLocations'][0]['latitude'])) {
                $lat = $status['lastLocations'][0]['latitude'];
                $this->SetValue('LastLatitude', $lat);
            }
        }

        $this->SetValue('LastStatus', time());

        $lastErrorCode = $status['lastErrorCode'];
        $lastErrorCodeTimestamp = $status['lastErrorCodeTimestamp'];
        if ($lastErrorCode) {
            if (!$this->CheckVarProfile4Value('Automower.Error', $lastErrorCode)) {
                $msg = __FUNCTION__ . ': unknown error-code=' . $lastErrorCode . ' @' . date('d-m-Y H:i:s', $lastErrorCodeTimestamp);
                $this->LogMessage($msg, KL_WARNING);
            }
        } else {
            $lastErrorCodeTimestamp = 0;
        }
        $this->SetValue('LastErrorCode', $lastErrorCode);
        $this->SetValue('LastErrorTimestamp', $lastErrorCodeTimestamp);

        $dt = new DateTime(date('d.m.Y 00:00:00'));
        $ts_today = (int) $dt->format('U');
        $ts_watch = $this->GetValue('DailyReference');
        if ($ts_today != $ts_watch) {
            $this->SetValue('DailyReference', $ts_today);
            $this->SetValue('DailyWorking', 0);
        }
        switch ($mowerActivity) {
            case self::$AUTOMOWER_ACTIVITY_MOVING:
            case self::$AUTOMOWER_ACTIVITY_CUTTING:
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

        if (isset($status['lastLocations'])) {
            $lastLocations = $status['lastLocations'];
            $this->SetBuffer('LastLocations', json_encode($lastLocations));
            if ($save_position && ($wasWorking || $isWorking)) {
                if (count($lastLocations)) {
                    $latitude = (float) $this->format_float($lastLocations[0]['latitude'], 6);
                    $longitude = (float) $this->format_float($lastLocations[0]['longitude'], 6);
                    $pos = json_encode(['latitude'  => $latitude, 'longitude' => $longitude]);
                    if ($this->GetValue('Position') != $pos) {
                        $this->SetValue('Position', $pos);
                        $this->SendDebug(__FUNCTION__, 'changed Position=' . $pos, 0);
                    }
                }
            }
        }

        // bisher unausgewertet url's:
        //  - $this->url_track . 'mowers/' . $device_id . '/settings'
        //  - $this->url_track . 'mowers/' . $device_id . '/geofence'
    }

    public function TestAccount()
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            echo $this->translate('Instance is inactive') . PHP_EOL;
            return;
        }

        $device_id = $this->ReadPropertyString('device_id');

        $mowers = $this->GetMowerList();
        if ($mowers == '') {
            $this->SetStatus(self::$IS_UNAUTHORIZED);
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
            $this->SetStatus(self::$IS_DEVICE_MISSING);
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
                    case self::$AUTOMOWER_ACTION_PARK:
                        $this->ParkMower();
                        break;
                    case self::$AUTOMOWER_ACTION_START:
                        $this->StartMower();
                        break;
                    case self::$AUTOMOWER_ACTION_STOP:
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
            'HOME'               => 'remain in base',
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
            'PARKED_AUTOTIMER'            => 'automatic parked',
            'PARKED_PARKED_SELECTED'      => 'manual parked',

            'PAUSED'                      => 'paused',

            'OFF_DISABLED'                => 'disabled',
            'OFF_HATCH_OPEN'              => 'hatch open',
            'OFF_HATCH_CLOSED'            => 'hatch closed',
            'OFF_HATCH_CLOSED_DISABLED'   => 'hatch closed and disabled',

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
            'ERROR'                       => self::$AUTOMOWER_ACTIVITY_ERROR,

            'OK_CUTTING'                  => self::$AUTOMOWER_ACTIVITY_CUTTING,
            'OK_CUTTING_NOT_AUTO'         => self::$AUTOMOWER_ACTIVITY_CUTTING,
            'OK_CUTTING_TIMER_OVERRIDDEN' => self::$AUTOMOWER_ACTIVITY_CUTTING,

            'PARKED_TIMER'                => self::$AUTOMOWER_ACTIVITY_PARKED,
            'PARKED_AUTOTIMER'            => self::$AUTOMOWER_ACTIVITY_PARKED,
            'PARKED_PARKED_SELECTED'      => self::$AUTOMOWER_ACTIVITY_PARKED,

            'PAUSED'                      => self::$AUTOMOWER_ACTIVITY_PAUSED,

            'OFF_DISABLED'                => self::$AUTOMOWER_ACTIVITY_DISABLED,
            'OFF_HATCH_OPEN'              => self::$AUTOMOWER_ACTIVITY_DISABLED,
            'OFF_HATCH_CLOSED'            => self::$AUTOMOWER_ACTIVITY_DISABLED,
            'OFF_HATCH_CLOSED_DISABLED'   => self::$AUTOMOWER_ACTIVITY_DISABLED,

            'OK_SEARCHING'                => self::$AUTOMOWER_ACTIVITY_MOVING,
            'OK_LEAVING'                  => self::$AUTOMOWER_ACTIVITY_MOVING,

            'OK_CHARGING'                 => self::$AUTOMOWER_ACTIVITY_CHARGING,
        ];

        if (isset($val2code[$val])) {
            $code = $val2code[$val];
        } else {
            $msg = 'unknown value "' . $val . '"';
            $this->LogMessage(__FUNCTION__ . ': ' . $msg, KL_WARNING);
            $this->SendDebug(__FUNCTION__, $msg, 0);
            $code = self::$AUTOMOWER_ACTIVITY_ERROR;
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

    public function GetRawData(string $name)
    {
        $data = $this->GetBuffer($name);
        $this->SendDebug(__FUNCTION__, 'name=' . $name . ', size=' . strlen($data) . ', data=' . $data, 0);
        return $data;
    }
}
