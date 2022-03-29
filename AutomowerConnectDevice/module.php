<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/CommonStubs/common.php'; // globale Funktionen
require_once __DIR__ . '/../libs/local.php';   // lokale Funktionen

class AutomowerConnectDevice extends IPSModule
{
    use StubsCommonLib;
    use AutomowerConnectLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('model', '');
        $this->RegisterPropertyString('serial', '');
        $this->RegisterPropertyString('id', '');

        $this->RegisterPropertyString('device_id', ''); // alt, nur noch für Update!

        $this->RegisterPropertyBoolean('with_gps', true);
        $this->RegisterPropertyBoolean('save_position', false);

        $this->RegisterPropertyInteger('update_interval', '5');

        $this->RegisterTimer('UpdateStatus', 0, 'AutomowerConnect_UpdateStatus(' . $this->InstanceID . ');');
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        $this->ConnectParent('{AEEFAA3E-8802-086D-6620-E971C03CBEFC}');

        $this->InstallVarProfiles(false);

        $this->SetBuffer('LastLocations', '');
    }

    private function CheckConfiguration()
    {
        $s = '';
        $r = [];

        $serial = $this->ReadPropertyString('serial');
        if ($serial == '') {
            $this->SendDebug(__FUNCTION__, '"serial" is empty', 0);
            $r[] = $this->Translate('Field "Serial" is empty');
        }

        $id = $this->ReadPropertyString('id');
        if ($id == '') {
            $this->SendDebug(__FUNCTION__, '"id" is empty', 0);
            $r[] = $this->Translate('Field "Device-ID" is empty');
        }

        if ($r != []) {
            $s = $this->Translate('The following points of the configuration are incorrect') . ':' . PHP_EOL;
            foreach ($r as $p) {
                $s .= '- ' . $p . PHP_EOL;
            }
        }

        return $s;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $with_gps = $this->ReadPropertyBoolean('with_gps');
        $save_position = $this->ReadPropertyBoolean('save_position');

        $vpos = 0;
        $this->MaintainVariable('Connected', $this->Translate('Connection status'), VARIABLETYPE_BOOLEAN, 'Automower.Connection', $vpos++, true);
        $this->MaintainVariable('Battery', $this->Translate('Battery capacity'), VARIABLETYPE_INTEGER, 'Automower.Battery', $vpos++, true);
        $this->MaintainVariable('OperationMode', $this->Translate('Operation mode'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('MowerStatus', $this->Translate('Mower status'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('MowerActivity', $this->Translate('Mower activity'), VARIABLETYPE_INTEGER, 'Automower.Activity', $vpos++, true);
        $this->MaintainVariable('RestrictedReason', $this->Translate('Restricted reason'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('NextStart', $this->Translate('Next start'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $this->MaintainVariable('MowerActionStart', $this->Translate('Start action'), VARIABLETYPE_INTEGER, 'Automower.ActionStart', $vpos++, true);
        $this->MaintainAction('MowerActionStart', true);
        $this->MaintainVariable('MowerActionPause', $this->Translate('Pause action'), VARIABLETYPE_INTEGER, 'Automower.ActionPause', $vpos++, true);
        $this->MaintainAction('MowerActionPause', true);
        $this->MaintainVariable('MowerActionPark', $this->Translate('Park action'), VARIABLETYPE_INTEGER, 'Automower.ActionPark', $vpos++, true);
        $this->MaintainAction('MowerActionPark', true);

        $this->MaintainVariable('DailyReference', $this->Translate('Day of cumulation'), VARIABLETYPE_INTEGER, '~UnixTimestampDate', $vpos++, true);
        $this->MaintainVariable('DailyWorking', $this->Translate('Working time (day)'), VARIABLETYPE_INTEGER, 'Automower.Duration', $vpos++, true);

        $this->MaintainVariable('LastErrorCode', $this->Translate('Last error'), VARIABLETYPE_INTEGER, 'Automower.Error', $vpos++, true);
        $this->MaintainVariable('LastErrorTimestamp', $this->Translate('Timestamp of last error'), VARIABLETYPE_INTEGER, '~UnixTimestampDate', $vpos++, true);
        $this->MaintainVariable('LastLongitude', $this->Translate('Last position (longitude)'), VARIABLETYPE_FLOAT, 'Automower.Location', $vpos++, $with_gps);
        $this->MaintainVariable('LastLatitude', $this->Translate('Last position (latitude)'), VARIABLETYPE_FLOAT, 'Automower.Location', $vpos++, $with_gps);
        $this->MaintainVariable('LastStatus', $this->Translate('Last status'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('Position', $this->Translate('Position'), VARIABLETYPE_STRING, '', $vpos++, $save_position);

        $this->MaintainVariable('HeadlightMode', $this->Translate('Headlight mode'), VARIABLETYPE_INTEGER, 'Automower.HeadlightMode', $vpos++, true);
        $this->MaintainAction('HeadlightMode', true);
        $this->MaintainVariable('CuttingHeight', $this->Translate('Cutting height'), VARIABLETYPE_INTEGER, 'Automower.CuttingHeight', $vpos++, true);
        $this->MaintainAction('CuttingHeight', true);

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        $propertyNames = [];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid >= 10000) {
                $this->RegisterReference($oid);
            }
        }

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetTimerInterval('UpdateStatus', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->SetTimerInterval('UpdateStatus', 0);
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $this->SetUpdateInterval();
        $this->SetStatus(IS_ACTIVE);

        $model = $this->ReadPropertyString('model');
        $serial = $this->ReadPropertyString('serial');
        $this->SetSummary($model . '(#' . $serial . ')');
    }

    protected function GetFormElements()
    {
        $formElements = [];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Husqvarna Automower Connect',
        ];

        if ($this->HasActiveParent() == false) {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => 'Instance has no active parent instance',
            ];
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Basic configuration (don\'t change)',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'serial',
                    'caption' => 'Serial',
                    'enabled' => false
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'model',
                    'caption' => 'Model',
                    'enabled' => false
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'id',
                    'caption' => 'ID',
                    'enabled' => false
                ],
            ],
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_gps',
            'caption' => 'with GPS-Data'
        ];
        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'save position to (logged) variable \'Position\''
        ];
        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'save_position',
            'caption' => 'save position'
        ];
        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'update_interval',
            'suffix'  => 'Minutes',
            'caption' => 'Update interval',
        ];

        return $formElements;
    }

    protected function GetFormActions()
    {
        $formActions = [];

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Update status',
            'onClick' => 'AutomowerConnect_UpdateStatus($id);'
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded ' => false,
            'items'     => [
                [
                    'type'    => 'TestCenter',
                ]
            ]
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded ' => false,
            'items'     => [
                [
                    'type'    => 'Button',
                    'caption' => 'Re-install variable-profiles',
                    'onClick' => 'AutomowerConnect_InstallVarProfiles($id, true);'
                ],
            ]
        ];

        $formActions[] = $this->GetInformationForm();
        $formActions[] = $this->GetReferencesForm();

        return $formActions;
    }

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
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return;
        }

        $id = $this->ReadPropertyString('id');
        if ($id == '') {
            return false;
        }
        $sdata = [
            'DataID'    => '{4C746488-C0FD-A850-3532-8DEBC042C970}',
            'Function'  => 'MowerStatus',
            'id'        => $id,
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $cdata = $this->SendDataToParent(json_encode($sdata));
        if ($cdata == '') {
            $this->SendDebug(__FUNCTION__, 'got no data', 0);
            $this->SetValue('Connected', false);
            return false;
        }

        $mower = json_decode($cdata, true);
        $this->SendDebug(__FUNCTION__, 'mower=' . print_r($mower, true), 0);

        $attributes = $this->GetArrayElem($mower, 'data.attributes', '');

        $batteryPercent = $this->GetArrayElem($attributes, 'battery.batteryPercent', 0);
        $this->SetValue('Battery', $batteryPercent);

        $connected = (bool) $this->GetArrayElem($attributes, 'metadata.connected', false);
        $this->SetValue('Connected', $connected);

        $mower_mode = $this->GetArrayElem($attributes, 'mower.mode', '');
        $operatingMode = $this->decode_operatingMode($mower_mode);
        $this->SendDebug(__FUNCTION__, 'mower_mode="' . $mower_mode . '" => OperationMode=' . $operatingMode, 0);
        $this->SetValue('OperationMode', $operatingMode);

        $mower_state = $this->GetArrayElem($attributes, 'mower.state', '');
        $mowerStatus = $this->decode_mowerStatus($mower_state);
        $this->SendDebug(__FUNCTION__, 'mower_state="' . $mower_state . '" => MowerStatus=' . $mowerStatus, 0);
        $this->SetValue('MowerStatus', $mowerStatus);

        $oldActivity = $this->GetValue('MowerActivity');
        switch ($oldActivity) {
            case self::$ACTIVITY_GOING_HOME:
            case self::$ACTIVITY_CUTTING:
                $wasWorking = true;
                break;
            default:
                $wasWorking = false;
                break;
        }
        $this->SendDebug(__FUNCTION__, 'wasWorking=' . $wasWorking, 0);

        $mower_activity = $this->GetArrayElem($attributes, 'mower.activity', '');
        $mowerActivity = $this->decode_mowerActivity($mower_activity);
        $s = $this->CheckVarProfile4Value('Automower.Activity', $mowerActivity);
        $this->SendDebug(__FUNCTION__, 'mower_activity="' . $mower_activity . '" => ' . $mowerActivity . '(' . $s . ')', 0);
        $this->SetValue('MowerActivity', $mowerActivity);

        $enableStart = false;
        $enablePause = false;
        $enablePark = false;

        switch ($mowerActivity) {
            case self::$ACTIVITY_NOT_APPLICABLE:
                if ($mower_state == 'PAUSED') {
                    $enableStart = true;
                    $enablePark = true;
                }
                break;
            case self::$ACTIVITY_DISABLED:
            case self::$ACTIVITY_CHARGING:
            case self::$ACTIVITY_STOPPED:
                $enableStart = true;
                break;
            case self::$ACTIVITY_LEAVING:
            case self::$ACTIVITY_GOING_HOME:
            case self::$ACTIVITY_CUTTING:
                $enablePause = true;
                $enablePark = true;
                break;
            case self::$ACTIVITY_PARKED:
            case self::$ACTIVITY_PAUSED:
                $enableStart = true;
                $enablePark = true;
                break;
            default:
                $enableStart = true;
                $enablePark = true;
                break;
        }

        $this->SendDebug(__FUNCTION__, 'enable ActionStart=' . $this->bool2str($enableStart) . ',  ActionPause=' . $this->bool2str($enablePause) . ', ActionPark=' . $this->bool2str($enablePark), 0);

        $chg = $this->AdjustAction('MowerActionStart', $enableStart);
        $chg |= $this->AdjustAction('MowerActionPause', $enablePause);
        $chg |= $this->AdjustAction('MowerActionPark', $enablePark);
        if ($chg) {
            $this->ReloadForm();
        }

        if ($mowerActivity == self::$ACTIVITY_PARKED) {
            $this->SetValue('MowerActionStart', self::$ACTION_RESUME_SCHEDULE);
        }

        $lastErrorCode = $this->GetArrayElem($attributes, 'mower.errorCode', 0);
        $lastErrorCodeTimestamp = $this->calc_ts((string) $this->GetArrayElem($attributes, 'mower.errorCodeTimestamp', '0'));
        $err = '';
        if ($lastErrorCode) {
            $s = $this->CheckVarProfile4Value('Automower.Error', $lastErrorCode);
            if ($s == false) {
                $msg = __FUNCTION__ . ': unknown error-code=' . $lastErrorCode . ' @' . date('d-m-Y H:i:s', $lastErrorCodeTimestamp);
                $this->LogMessage($msg, KL_WARNING);
            } else {
                $err = '(' . $s . ')';
            }
        } else {
            $lastErrorCodeTimestamp = 0;
        }
        $ts = ($lastErrorCodeTimestamp != 0 ? date('d.m.y H:i:s', $lastErrorCodeTimestamp) : '');
        $this->SendDebug(__FUNCTION__, 'lastErrorCode=' . $lastErrorCode . $err . ', lastErrorCodeTimestamp=' . $ts, 0);
        $this->SetValue('LastErrorCode', $lastErrorCode);
        $this->SetValue('LastErrorTimestamp', $lastErrorCodeTimestamp);

        $nextStartTimestamp = $this->calc_ts((string) $this->GetArrayElem($attributes, 'planner.nextStartTimestamp', '0'));
        $this->SendDebug(__FUNCTION__, 'nextStartTimestamp=' . ($nextStartTimestamp != 0 ? date('d.m.y H:i:s', $nextStartTimestamp) : ''), 0);
        $this->SetValue('NextStart', $nextStartTimestamp);

        // NOT_ACTIVE, FORCE_PARK, FORCE_MOW
        $planner_override = $this->GetArrayElem($attributes, 'planner.override.action', '');
        $this->SendDebug(__FUNCTION__, 'planner_override=' . $planner_override, 0);

        $restricted_reason = $this->GetArrayElem($attributes, 'planner.restrictedReason', '');
        if ($mower_state == 'RESTRICTED') {
            if ($restricted_reason == 'NOT_APPLICABLE' && $mower_activity == 'PARKED_IN_CS' && $nextStartTimestamp == 0) {
                $restricted_reason = 'UNTIL_FURTHER_NOTICE';
            }
            $restrictedReason = $this->decode_restrictedReason($restricted_reason);
        } else {
            $restrictedReason = '';
        }

        $this->SendDebug(__FUNCTION__, 'restricted_reason="' . $restricted_reason . '" => ' . $restrictedReason, 0);
        $this->SetValue('RestrictedReason', $restrictedReason);

        $dt = new DateTime(date('d.m.Y 00:00:00'));
        $ts_today = (int) $dt->format('U');
        $ts_watch = $this->GetValue('DailyReference');
        if ($ts_today != $ts_watch) {
            $this->SetValue('DailyReference', $ts_today);
            $this->SetValue('DailyWorking', 0);
        }
        switch ($mowerActivity) {
            case self::$ACTIVITY_GOING_HOME:
            case self::$ACTIVITY_CUTTING:
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

        $cuttingHeight = $this->GetArrayElem($attributes, 'settings.cuttingHeight', 0);
        $this->SendDebug(__FUNCTION__, 'cuttingHeight=' . $cuttingHeight, 0);
        $this->SetValue('CuttingHeight', $cuttingHeight);

        $headlight_mode = $this->GetArrayElem($attributes, 'settings.headlight.mode', 0);
        $headlightMode = $this->decode_headlightMode($headlight_mode);
        $this->SendDebug(__FUNCTION__, 'headlight_mode="' . $headlight_mode . '" => headlightMode=' . $headlightMode, 0);
        $this->SetValue('HeadlightMode', $headlightMode);

        $with_gps = $this->ReadPropertyBoolean('with_gps');
        if ($with_gps && isset($attributes['positions'])) {
            $positions = (array) $this->GetArrayElem($attributes, 'positions', []);
            $this->SendDebug(__FUNCTION__, 'positions #=' . count($positions), 0);

            $this->SetBuffer('LastLocations', json_encode($positions));

            if (count($positions)) {
                $latitude = $this->GetValue('LastLatitude');
                $longitude = $this->GetValue('LastLongitude');
                $pos = $this->GetValue('Position');
                $this->SendDebug(__FUNCTION__, 'last latitude=' . $latitude . ', longitude=' . $longitude . ', pos=' . $pos, 0);

                for ($i = 0; $i < count($positions) && $i < 10; $i++) {
                    $latitude = $positions[$i]['latitude'];
                    $longitude = $positions[$i]['longitude'];
                    $this->SendDebug(__FUNCTION__, '#' . $i . ' latitude=' . $latitude . ', longitude=' . $longitude, 0);
                }

                $this->SetValue('LastLongitude', $positions[0]['longitude']);
                $this->SetValue('LastLatitude', $positions[0]['latitude']);

                $save_position = $this->ReadPropertyBoolean('save_position');
                $this->SendDebug(__FUNCTION__, 'save_position=' . $this->bool2str($save_position) . ', wasWorking=' . $this->bool2str($wasWorking) . ', isWorking=' . $this->bool2str($isWorking), 0);
                if ($save_position && ($wasWorking || $isWorking)) {
                    $latitude = (float) $this->format_float($positions[0]['latitude'], 6);
                    $longitude = (float) $this->format_float($positions[0]['longitude'], 6);
                    $pos = json_encode(['latitude'  => $latitude, 'longitude' => $longitude]);
                    if ($this->GetValue('Position') != $pos) {
                        $this->SetValue('Position', $pos);
                        $this->SendDebug(__FUNCTION__, 'changed Position=' . $pos, 0);
                    }
                }
            }
        }

        $this->SetValue('LastStatus', time());

        $this->SetUpdateInterval();
    }

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $r = false;
        switch ($ident) {
            case 'MowerActionStart':
                $r = $this->StartMower((int) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'MowerActionPause':
                $r = $this->PauseMower();
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'MowerActionPark':
                $r = $this->ParkMower((int) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'CuttingHeight':
                $r = $this->SetCuttingHeight((int) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'HeadlightMode':
                $r = $this->SetHeadlightMode((int) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            default:
                $this->SendDebug(__FUNCTION__, "invalid ident $ident", 0);
                break;
        }
        if ($r) {
            $this->Setvalue($ident, $value);
        }
    }

    private function decode_operatingMode($val)
    {
        // MAIN_AREA, SECONDARY_AREA, HOME, DEMO, UNKNOWN
        $val2txt = [
            'HOME'               => 'remain in base',
            'AUTO'               => 'automatic',
            'MAIN_AREA'          => 'main area',
            'SECONDARY_AREA'     => 'secondary area',
            'OVERRIDE_TIMER'     => 'override timer',
            'SPOT_CUTTING'       => 'spot cutting',
            'UNKNOWN'            => 'unknown',
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
            'UNKNOWN'           => 'unknown',
            'NOT_APPLICABLE'    => 'not applicable',
            'PAUSED'            => 'paused',
            'IN_OPERATION'      => 'in operation',
            'WAIT_UPDATING'     => 'wait updating',
            'WAIT_POWER_UP'     => 'wait power up',
            'RESTRICTED'        => 'restricted',
            'OFF'               => 'off',
            'STOPPED'           => 'stopped',
            'ERROR'             => 'error',
            'FATAL_ERROR'       => 'fatal error',
            'ERROR_AT_POWER_UP' => 'error at power up',
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

    private function decode_restrictedReason($val)
    {
        $val2txt = [
            'NONE'                 => 'none',
            'UNKNOWN'              => 'unknown',
            'NOT_APPLICABLE'       => 'not applicable',
            'WEEK_SCHEDULE'        => 'week schedule',
            'PARK_OVERRIDE'        => 'park overwrite',
            'SENSOR'               => 'sensor',
            'DAILY_LIMIT'          => 'daily limit',
            'UNTIL_FURTHER_NOTICE' => 'until further notice',
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

    private function decode_mowerActivity($val)
    {
        $val2code = [
            'UNKNOWN'           => self::$ACTIVITY_UNKNOWN,
            'NOT_APPLICABLE'    => self::$ACTIVITY_NOT_APPLICABLE,
            'MOWING'            => self::$ACTIVITY_CUTTING,
            'GOING_HOME'        => self::$ACTIVITY_GOING_HOME,
            'CHARGING'          => self::$ACTIVITY_CHARGING,
            'LEAVING'           => self::$ACTIVITY_LEAVING,
            'PARKED_IN_CS'      => self::$ACTIVITY_PARKED,
            'STOPPED_IN_GARDEN' => self::$ACTIVITY_STOPPED,
        ];

        if (isset($val2code[$val])) {
            $code = $val2code[$val];
        } else {
            $msg = 'unknown value "' . $val . '"';
            $this->LogMessage(__FUNCTION__ . ': ' . $msg, KL_WARNING);
            $this->SendDebug(__FUNCTION__, $msg, 0);
            $code = self::$ACTIVITY_ERROR;
        }
        return $code;
    }

    private function decode_headlightMode($val)
    {
        $val2code = [
            'ALWAYS_ON'         => self::$HEADLIGHT_ALWAYS_ON,
            'ALWAYS_OFF'        => self::$HEADLIGHT_ALWAYS_OFF,
            'EVENING_ONLY'      => self::$HEADLIGHT_EVENING_ONLY,
            'EVENING_AND_NIGHT' => self::$HEADLIGHT_EVENING_AND_NIGHT,
        ];

        if (isset($val2code[$val])) {
            $code = $val2code[$val];
        } else {
            $msg = 'unknown value "' . $val . '"';
            $this->LogMessage(__FUNCTION__ . ': ' . $msg, KL_WARNING);
            $this->SendDebug(__FUNCTION__, $msg, 0);
            $code = self::$HEADLIGHT_ALWAYS_ON;
        }
        return $code;
    }

    /*

    - Start + Duration (3h 6h 12h 1d 2d 3d4d 5d 6d 7d)
    - ResumeSchedule
    - Pause
    - Park + Duration (3h 6h 12h 1d 2d 3d4d 5d 6d 7d)
    - ParkUntilNextSchedule
    - ParkUntilFurtherNotice

     */

    private function ParkMower(int $value)
    {
        switch ($value) {
            case self::$ACTION_PARK_UNTIL_FURTHER_NOTICE:
                $data = [
                    'type' => 'ParkUntilFurtherNotice',
                ];
                break;
            case self::$ACTION_PARK_UNTIL_NEXT_SCHEDULE:
                $data = [
                    'type' => 'ParkUntilNextSchedule',
                ];
                break;
            default:
                $hour = (int) $value;
                if ($hour < 1) {
                    $this->SendDebug(__FUNCTION__, 'value ' . $value . ' ist not a valid int or below 1', 0);
                    return false;
                }
                $min = $hour * 60;
                $data = [
                    'type'        => 'Park',
                    'attributes'  => [
                        'duration'=> $min
                    ]
                ];
                break;
        }
        $this->SendDebug(__FUNCTION__, 'value=' . $value . ', data=' . print_r($data, true), 0);
        return $this->MowerCmd('actions', $data);
    }

    private function StartMower(int $value)
    {
        switch ($value) {
            case self::$ACTION_RESUME_SCHEDULE:
                $data = [
                    'type' => 'ResumeSchedule',
                ];
                break;
            default:
                $hour = (int) $value;
                if ($hour < 1) {
                    $this->SendDebug(__FUNCTION__, 'value ' . $value . ' ist not a valid int or below 1', 0);
                    return false;
                }
                $min = $hour * 60;
                $data = [
                    'type'       => 'Start',
                    'attributes' => [
                        'duration' => $min
                    ]
                ];
                break;
        }
        $this->SendDebug(__FUNCTION__, 'value=' . $value . ', data=' . print_r($data, true), 0);
        return $this->MowerCmd('actions', $data);
    }

    private function PauseMower()
    {
        $data = [
            'type' => 'Pause',
        ];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        return $this->MowerCmd('actions', $data);
    }

    private function SetCuttingHeight(int $value)
    {
        $data = [
            'type'        => 'settings',
            'attributes'  => [
                'cuttingHeight'=> $value
            ],
        ];
        $this->SendDebug(__FUNCTION__, 'value=' . $value . ', data=' . print_r($data, true), 0);
        return $this->MowerCmd('settings', $data);
    }

    private function SetHeadlightMode(int $value)
    {
        switch ($value) {
            case self::$HEADLIGHT_ALWAYS_ON:
                $mode = 'ALWAYS_ON';
                break;
            case self::$HEADLIGHT_ALWAYS_OFF:
                $mode = 'ALWAYS_OFF';
                break;
            case self::$HEADLIGHT_EVENING_ONLY:
                $mode = 'EVENING_ONLY';
                break;
            case self::$HEADLIGHT_EVENING_AND_NIGHT:
                $mode = 'EVENING_AND_NIGHT';
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid headlightMode ' . $value, 0);
                return false;
        }
        $data = [
            'type'          => 'settings',
            'attributes'    => [
                'headlight'  => [
                    'mode'   => $mode,
                ],
            ],
        ];
        $this->SendDebug(__FUNCTION__, 'value=' . $value . ', data=' . print_r($data, true), 0);
        return $this->MowerCmd('settings', $data);
    }

    private function MowerCmd($command, $data)
    {
        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return false;
        }

        $id = $this->ReadPropertyString('id');
        if ($id == '') {
            return false;
        }
        $sdata = [
            'DataID'    => '{4C746488-C0FD-A850-3532-8DEBC042C970}',
            'Function'  => 'MowerCmd',
            'id'        => $id,
            'command'   => $command,
            'data'      => $data
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $cdata = $this->SendDataToParent(json_encode($sdata));
        $this->SendDebug(__FUNCTION__, 'cdata=' . $cdata, 0);
        if ($cdata == '') {
            return false;
        }
        $jdata = json_decode($cdata, true);
        $status = $jdata['status'];
        if ($status != 'ok') {
            return false;
        }
        $this->SetTimerInterval('UpdateStatus', 15 * 1000);
        return true;
    }

    public function GetRawData(string $name)
    {
        $data = $this->GetBuffer($name);
        $this->SendDebug(__FUNCTION__, 'name=' . $name . ', size=' . strlen($data) . ', data=' . $data, 0);
        return $data;
    }

    private function calc_ts(string $ts_s)
    {
        if ($ts_s == '') {
            $ts = 0;
        } else {
            $ts = (int) substr($ts_s, 0, -3);
        }
        if ($ts > 0) {
            // 'ts' ist nicht UTC sondern auf localtime umgerechnet.
            $ts = strtotime(gmdate('Y-m-d H:i', $ts));
        }

        return $ts;
    }
}
