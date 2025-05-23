<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class AutomowerConnectDevice extends IPSModule
{
    use AutomowerConnect\StubsCommonLib;
    use AutomowerConnectLocalLib;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonConstruct(__DIR__);
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyBoolean('log_no_parent', true);

        $this->RegisterPropertyString('model', '');
        $this->RegisterPropertyString('serial', '');
        $this->RegisterPropertyString('id', '');

        $this->RegisterPropertyString('device_id', ''); // alt, nur noch für Update!

        $this->RegisterPropertyBoolean('with_cuttingHeight', true);
        $this->RegisterPropertyBoolean('with_headlightMode', true);

        $this->RegisterPropertyBoolean('with_gps', true);
        $this->RegisterPropertyBoolean('save_position', false);

        $this->RegisterPropertyBoolean('with_statistics', true);

        $this->RegisterPropertyInteger('update_interval', 60);

        $this->RegisterAttributeString('ManualUpdateInterval', '');
        $this->RegisterAttributeInteger('WorkingStart', 0);
        $this->RegisterAttributeInteger('DailyWorking', 0);

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->SetBuffer('LastLocations', '');

        $this->ConnectParent('{AEEFAA3E-8802-086D-6620-E971C03CBEFC}');

        $this->RegisterTimer('UpdateStatus', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateStatus", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    private function CheckModuleUpdate(array $oldInfo, array $newInfo)
    {
        $r = [];

        if ($this->version2num($oldInfo) < $this->version2num('2.4')) {
            @$varID = $this->GetIDForIdent('MowerAction');
            if (@$varID != false) {
                $r[] = $this->Translate('Delete variable \'MowerAction\'');
            }
            if (IPS_VariableProfileExists('Automower.Action')) {
                $r[] = $this->Translate('Delete variable-profile \'Automower.Action\'');
            }
        }

        if ($this->version2num($oldInfo) < $this->version2num('2.6')) {
            $r[] = $this->Translate('Spelling error in variableprofile \'Automower.Error\'');
        }

        if ($this->version2num($oldInfo) < $this->version2num('3.0')) {
            $r[] = $this->Translate('Change of polling interval to hourly (due to the change to push messages)');
            $r[] = $this->Translate('Wrong dimension in variableprofile \'Automower.CuttingHeight\'');
            $r[] = $this->Translate('Spelling error in variableprofile \'Automower.Error\'');
            $r[] = $this->Translate('Variable \'MowerStatus\' of type string will be deleted and replaced by \'MowerState\' of type int.');
            $r[] = $this->Translate('Please check and correct a possible usage of the old variable.');
        }

        if ($this->version2num($oldInfo) < $this->version2num('3.8')) {
            $r[] = $this->Translate('Variableprofile \'Automower.CuttingHeight\' will be corrected');
        }

        return $r;
    }

    private function CompleteModuleUpdate(array $oldInfo, array $newInfo)
    {
        if ($this->version2num($oldInfo) < $this->version2num('2.4')) {
            @$varID = $this->GetIDForIdent('MowerAction');
            if (@$varID != false) {
                $this->UnregisterVariable('MowerAction');
            }
            if (IPS_VariableProfileExists('Automower.Action')) {
                IPS_DeleteVariableProfile('Automower.Action');
            }
            if (IPS_VariableProfileExists('Automower.Error')) {
                IPS_DeleteVariableProfile('Automower.Error');
            }
            $this->InstallVarProfiles(false);
        }

        if ($this->version2num($oldInfo) < $this->version2num('2.6')) {
            if (IPS_VariableProfileExists('Automower.Error')) {
                IPS_DeleteVariableProfile('Automower.Error');
            }
            $this->InstallVarProfiles(false);
        }

        if ($this->version2num($oldInfo) < $this->version2num('3.0')) {
            IPS_SetProperty($this->InstanceID, 'update_interval', 60);
            if (IPS_VariableProfileExists('Automower.CuttingHeight')) {
                IPS_DeleteVariableProfile('Automower.CuttingHeight');
            }
            if (IPS_VariableProfileExists('Automower.Error')) {
                IPS_DeleteVariableProfile('Automower.Error');
            }
            $this->InstallVarProfiles(false);
            @$varID = $this->GetIDForIdent('MowerStatus');
            if (@$varID != false) {
                $this->UnregisterVariable('MowerStatus');
            }
        }

        if ($this->version2num($oldInfo) < $this->version2num('3.8')) {
            if (IPS_VariableProfileExists('Automower.CuttingHeight')) {
                IPS_DeleteVariableProfile('Automower.CuttingHeight');
            }
            $this->InstallVarProfiles(false);
        }

        return '';
    }

    private function CheckModuleConfiguration()
    {
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

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $with_gps = $this->ReadPropertyBoolean('with_gps');
        $save_position = $this->ReadPropertyBoolean('save_position');
        $with_cuttingHeight = $this->ReadPropertyBoolean('with_cuttingHeight');
        $with_headlightMode = $this->ReadPropertyBoolean('with_headlightMode');
        $with_statistics = $this->ReadPropertyBoolean('with_statistics');

        $vpos = 0;
        $this->MaintainVariable('Connected', $this->Translate('Connection status'), VARIABLETYPE_BOOLEAN, 'Automower.Connection', $vpos++, true);
        $this->MaintainVariable('Battery', $this->Translate('Battery capacity'), VARIABLETYPE_INTEGER, 'Automower.Battery', $vpos++, true);
        $this->MaintainVariable('OperationMode', $this->Translate('Operation mode'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('MowerState', $this->Translate('Mower status'), VARIABLETYPE_INTEGER, 'Automower.State', $vpos++, true);
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
        if ($save_position) {
            $this->SetVariableLogging('Position', 0 /* Standard */);
        }

        $this->MaintainVariable('HeadlightMode', $this->Translate('Headlight mode'), VARIABLETYPE_INTEGER, 'Automower.HeadlightMode', $vpos++, $with_headlightMode);
        if ($with_headlightMode) {
            $this->MaintainAction('HeadlightMode', true);
        }
        $this->MaintainVariable('CuttingHeight', $this->Translate('Cutting height'), VARIABLETYPE_INTEGER, 'Automower.CuttingHeight', $vpos++, $with_cuttingHeight);
        if ($with_cuttingHeight) {
            $this->MaintainAction('CuttingHeight', true);
        }

        $this->MaintainVariable('TotalChargingTime', $this->Translate('Total charging time'), VARIABLETYPE_INTEGER, 'Automower.Time', $vpos++, $with_statistics);
        $this->MaintainVariable('TotalCuttingTime', $this->Translate('Total cutting time'), VARIABLETYPE_INTEGER, 'Automower.Time', $vpos++, $with_statistics);
        $this->MaintainVariable('TotalRunningTime', $this->Translate('Total running time'), VARIABLETYPE_INTEGER, 'Automower.Time', $vpos++, $with_statistics);
        $this->MaintainVariable('TotalSearchingTime', $this->Translate('Total searching time'), VARIABLETYPE_INTEGER, 'Automower.Time', $vpos++, $with_statistics);
        $this->MaintainVariable('NumberOfChargingCycles', $this->Translate('Number of charging cycles'), VARIABLETYPE_INTEGER, '', $vpos++, $with_statistics);
        $this->MaintainVariable('NumberOfCollisions', $this->Translate('Number of collisions'), VARIABLETYPE_INTEGER, '', $vpos++, $with_statistics);
        $this->MaintainVariable('CuttingBladeUsageTime', $this->Translate('Cutting blade usage time'), VARIABLETYPE_INTEGER, 'Automower.Time', $vpos++, $with_statistics);

        if ($with_statistics) {
            $this->SetVariableLogging('TotalChargingTime', 1 /* Zähler */);
            $this->SetVariableLogging('TotalCuttingTime', 1 /* Zähler */);
            $this->SetVariableLogging('TotalRunningTime', 1 /* Zähler */);
            $this->SetVariableLogging('TotalSearchingTime', 1 /* Zähler */);
            $this->SetVariableLogging('NumberOfChargingCycles', 0 /* Standard */);
            $this->SetVariableLogging('NumberOfCollisions', 0 /* Standard */);
            $this->SetVariableLogging('CuttingBladeUsageTime', 1 /* Zähler */);
        }

        $model = $this->ReadPropertyString('model');
        $serial = $this->ReadPropertyString('serial');
        $this->SetSummary($model . '(#' . $serial . ')');

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetUpdateTimer();
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Husqvarna AutomowerConnect Mower');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
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
                    'name'    => 'serial',
                    'type'    => 'ValidationTextBox',
                    'enabled' => false,
                    'caption' => 'Serial',
                ],
                [
                    'name'    => 'model',
                    'type'    => 'ValidationTextBox',
                    'enabled' => false,
                    'caption' => 'Model',
                ],
                [
                    'name'    => 'id',
                    'type'    => 'ValidationTextBox',
                    'width'   => '400px',
                    'enabled' => false,
                    'caption' => 'ID',
                ],
            ],
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_gps',
            'caption' => 'with GPS-Data'
        ];
        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'save_position',
            'caption' => 'save position'
        ];
        $formElements[] = [
            'type'    => 'Label',
            'caption' => ' ... by activating this switch, a additional variable is created and logged',
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_cuttingHeight',
            'caption' => 'with cutting height adjustment'
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_headlightMode',
            'caption' => 'with headlight mode'
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_statistics',
            'caption' => 'save statistic data'
        ];
        $formElements[] = [
            'type'    => 'Label',
            'caption' => ' ... by activating this switch, additional variables are created and logged as counters',
        ];

        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'update_interval',
            'suffix'  => 'Minutes',
            'minimum' => 5,
            'caption' => 'Update interval',
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'log_no_parent',
            'caption' => 'Generate message when the gateway is inactive',
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

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Update status',
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateStatus", "");',
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded'  => false,
            'items'     => [
                [
                    'type'    => 'TestCenter',
                ]
            ]
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
            ]
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->SetUpdateTimer();
        }
    }

    private function SetUpdateTimer(int $min = null)
    {
        if (is_null($min)) {
            $min = $this->ReadAttributeString('ManualUpdateInterval');
            if ($min == '') {
                $min = $this->ReadPropertyInteger('update_interval');
            }
        }
        $msec = $min > 0 ? $min * 1000 * 60 : 0;
        $this->MaintainTimer('UpdateStatus', $msec);
    }

    public function SetUpdateInterval(int $min = null)
    {
        if (is_null($min)) {
            $this->WriteAttributeString('ManualUpdateInterval', '');
        } else {
            $this->WriteAttributeString('ManualUpdateInterval', $min);
        }
        $this->SetUpdateTimer($min);
    }

    private function UpdateStatus()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent/gateway', 0);
            $log_no_parent = $this->ReadPropertyBoolean('log_no_parent');
            if ($log_no_parent) {
                $this->LogMessage($this->Translate('Instance has no active gateway'), KL_WARNING);
            }
            return false;
        }

        $id = $this->ReadPropertyString('id');
        if ($id == '') {
            return false;
        }
        $sdata = [
            'DataID'   => '{4C746488-C0FD-A850-3532-8DEBC042C970}', // an AutomowerConnectIO
            'CallerID' => $this->InstanceID,
            'Function' => 'MowerStatus',
            'id'       => $id,
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $cdata = $this->SendDataToParent(json_encode($sdata));
        if ($cdata == '') {
            $this->SendDebug(__FUNCTION__, 'got no data', 0);
            $this->SetValue('Connected', false);
            return false;
        }

        $mower = json_decode($cdata, true);
        if ($mower == []) {
            $this->SendDebug(__FUNCTION__, 'got no data', 0);
            return false;
        }
        $this->SendDebug(__FUNCTION__, 'mower=' . print_r($mower, true), 0);

        $attributes = $this->GetArrayElem($mower, 'data.attributes', '');
        $this->SendDebug(__FUNCTION__, 'type=query, attributes=' . print_r($attributes, true), 0);
        $this->DecodeAttributes($attributes);

        $this->SetUpdateTimer();

        return true;
    }

    private function DecodeAttributes($attributes)
    {
        $fnd = false;
        $connected = (bool) $this->GetArrayElem($attributes, 'metadata.connected', false, $fnd);
        if ($fnd) {
            $this->SetValue('Connected', $connected);
        }

        $batteryPercent = $this->GetArrayElem($attributes, 'battery.batteryPercent', 0, $fnd);
        if ($fnd) {
            $this->SetValue('Battery', $batteryPercent);
        }

        if (isset($attributes['mower'])) {
            $operatingMode = '';
            $mower_mode = $this->GetArrayElem($attributes, 'mower.mode', '', $fnd);
            if ($fnd) {
                $operatingMode = $this->decode_operatingMode($mower_mode);
                $this->SendDebug(__FUNCTION__, 'mower_mode="' . $mower_mode . '" => OperationMode=' . $operatingMode, 0);
                $this->SetValue('OperationMode', $operatingMode);
            }

            $mowerState = self::$STATE_UNKNOWN;
            $mower_state = $this->GetArrayElem($attributes, 'mower.state', '', $fnd);
            if ($fnd) {
                $mowerState = $this->decode_mowerState($mower_state);
                $s = $this->CheckVarProfile4Value('Automower.State', $mowerState);
                $this->SendDebug(__FUNCTION__, 'mower_state="' . $mower_state . '" => ' . $mowerState . '(' . $s . ')', 0);
                $this->SetValue('MowerState', $mowerState);
            }

            $oldActivity = $this->GetValue('MowerActivity');

            $mowerActivity = self::$ACTIVITY_UNKNOWN;
            $mower_activity = $this->GetArrayElem($attributes, 'mower.activity', '', $fnd);
            if ($fnd) {
                $mowerActivity = $this->decode_mowerActivity($mower_activity);
                $s = $this->CheckVarProfile4Value('Automower.Activity', $mowerActivity);
                $this->SendDebug(__FUNCTION__, 'mower_activity="' . $mower_activity . '" => ' . $mowerActivity . '(' . $s . ')', 0);
                $this->SetValue('MowerActivity', $mowerActivity);
            }

            $enableStart = false;
            $enablePause = false;
            $enablePark = false;

            switch ($mowerActivity) {
                case self::$ACTIVITY_NOT_APPLICABLE:
                    if ($mowerState == self::$STATE_PAUSED) {
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
                    $enableStart = true;
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

            $enableStart = true;
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
                    $msg = __FUNCTION__ . ': unknown error-code=' . $lastErrorCode . ' @' . date('d.m.Y H:i:s', $lastErrorCodeTimestamp);
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
            switch ($oldActivity) {
                case self::$ACTIVITY_LEAVING:
                case self::$ACTIVITY_GOING_HOME:
                case self::$ACTIVITY_CUTTING:
                    $wasWorking = true;
                    break;
                default:
                    $wasWorking = false;
                    break;
            }
            $tstamp = $this->ReadAttributeInteger('WorkingStart');
            $this->SendDebug(__FUNCTION__, 'isWorking=' . $isWorking . ', wasWorking=' . $wasWorking . ', tstamp[GET]=' . $tstamp, 0);

            if ($tstamp > 0) {
                $daily_working = $this->ReadAttributeInteger('DailyWorking');
                $duration = $daily_working + ((time() - $tstamp) / 60);
                $this->SetValue('DailyWorking', $duration);
                $this->SendDebug(__FUNCTION__, 'daily_working[GET]=' . $daily_working . ', duration=' . $duration, 0);
                if (!$isWorking) {
                    $this->WriteAttributeInteger('WorkingStart', 0);
                    $this->WriteAttributeInteger('DailyWorking', 0);
                    $this->SendDebug(__FUNCTION__, 'tstamp[CLR], daily_working[CLR]', 0);
                }
            } else {
                if ($isWorking) {
                    $tstamp = time();
                    $this->WriteAttributeInteger('WorkingStart', $tstamp);
                    $daily_working = $this->GetValue('DailyWorking');
                    $this->WriteAttributeInteger('DailyWorking', $daily_working);
                    $this->SendDebug(__FUNCTION__, 'tstamp[SET]=' . $tstamp . ', daily_working[SET]=' . $daily_working, 0);
                }
            }
        } else {
            $mowerActivity = $this->GetValue('MowerActivity');
        }

        if (isset($attributes['planner'])) {
            $nextStartTimestamp = $this->calc_ts((string) $this->GetArrayElem($attributes, 'planner.nextStartTimestamp', '0'));
            $this->SendDebug(__FUNCTION__, 'nextStartTimestamp=' . ($nextStartTimestamp != 0 ? date('d.m.y H:i:s', $nextStartTimestamp) : ''), 0);
            $this->SetValue('NextStart', $nextStartTimestamp);

            // NOT_ACTIVE, FORCE_PARK, FORCE_MOW
            $planner_override = $this->GetArrayElem($attributes, 'planner.override.action', '');
            $this->SendDebug(__FUNCTION__, 'planner_override=' . $planner_override, 0);

            $restrictedReason = '';
            $restricted_reason = $this->GetArrayElem($attributes, 'planner.restrictedReason', '', $fnd);
            if ($fnd) {
                $mowerState = $this->GetValue('MowerState');
                if ($mowerState == self::$STATE_RESTRICTED) {
                    if ($restricted_reason == 'NOT_APPLICABLE' && $mower_activity == 'PARKED_IN_CS') {
                        $restricted_reason = 'UNTIL_FURTHER_NOTICE';
                    }
                    $restrictedReason = $this->decode_restrictedReason($restricted_reason);
                }
            }

            $this->SendDebug(__FUNCTION__, 'restricted_reason="' . $restricted_reason . '" => ' . $restrictedReason, 0);
            $this->SetValue('RestrictedReason', $restrictedReason);
        }

        $with_cuttingHeight = $this->ReadPropertyBoolean('with_cuttingHeight');
        if ($with_cuttingHeight) {
            $cuttingHeight = $this->GetArrayElem($attributes, 'settings.cuttingHeight', 0, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, 'cuttingHeight=' . $cuttingHeight, 0);
                $this->SetValue('CuttingHeight', $cuttingHeight);
            }
        }

        $with_headlightMode = $this->ReadPropertyBoolean('with_headlightMode');
        if ($with_headlightMode) {
            $headlight_mode = $this->GetArrayElem($attributes, 'settings.headlight.mode', 0, $fnd);
            if ($fnd) {
                $headlightMode = $this->decode_headlightMode($headlight_mode);
                $s = $this->CheckVarProfile4Value('Automower.HeadlightMode', $headlightMode);
                $this->SendDebug(__FUNCTION__, 'headlight_mode="' . $headlight_mode . '" => ' . $headlightMode . '(' . $s . ')', 0);
                $this->SetValue('HeadlightMode', $headlightMode);
            }
        }

        $with_statistics = $this->ReadPropertyBoolean('with_statistics');
        if ($with_statistics) {
            $totalChargingTime = $this->GetArrayElem($attributes, 'statistics.totalChargingTime', 0, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, 'totalChargingTime=' . $totalChargingTime, 0);
                $this->SetValue('TotalChargingTime', $totalChargingTime);
            }
            $totalCuttingTime = $this->GetArrayElem($attributes, 'statistics.totalCuttingTime', 0, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, 'totalCuttingTime=' . $totalCuttingTime, 0);
                $this->SetValue('TotalCuttingTime', $totalCuttingTime);
            }
            $totalRunningTime = $this->GetArrayElem($attributes, 'statistics.totalRunningTime', 0, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, 'totalRunningTime=' . $totalRunningTime, 0);
                $this->SetValue('TotalRunningTime', $totalRunningTime);
            }
            $totalSearchingTime = $this->GetArrayElem($attributes, 'statistics.totalSearchingTime', 0, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, 'totalSearchingTime=' . $totalSearchingTime, 0);
                $this->SetValue('TotalSearchingTime', $totalSearchingTime);
            }
            $numberOfChargingCycles = $this->GetArrayElem($attributes, 'statistics.numberOfChargingCycles', 0, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, 'numberOfChargingCycles=' . $numberOfChargingCycles, 0);
                $this->SetValue('NumberOfChargingCycles', $numberOfChargingCycles);
            }
            $numberOfCollisions = $this->GetArrayElem($attributes, 'statistics.numberOfCollisions', 0, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, 'numberOfCollisions=' . $numberOfCollisions, 0);
                $this->SetValue('NumberOfCollisions', $numberOfCollisions);
            }
            $cuttingBladeUsageTime = $this->GetArrayElem($attributes, 'statistics.cuttingBladeUsageTime', 0, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, 'cuttingBladeUsageTime=' . $cuttingBladeUsageTime, 0);
                $this->SetValue('CuttingBladeUsageTime', $cuttingBladeUsageTime);
            }
        }

        $with_gps = $this->ReadPropertyBoolean('with_gps');
        $save_position = $this->ReadPropertyBoolean('save_position');
        if ($with_gps && isset($attributes['positions'])) {
            $positions = (array) $this->GetArrayElem($attributes, 'positions', []);
            $this->SetBuffer('LastLocations', json_encode($positions));

            if (count($positions)) {
                $lat = $this->GetValue('LastLatitude');
                $lon = $this->GetValue('LastLongitude');
                if ($save_position) {
                    $pos = $this->GetValue('Position');
                    $this->SendDebug(__FUNCTION__, 'last latitude=' . $lat . ', longitude=' . $lon . ', pos=' . $pos, 0);
                } else {
                    $this->SendDebug(__FUNCTION__, 'last latitude=' . $lat . ', longitude=' . $lon, 0);
                }

                for ($i = 0; $i < count($positions); $i++) {
                    $latitude = $positions[$i]['latitude'];
                    $longitude = $positions[$i]['longitude'];
                    if ($latitude == $lat && $longitude == $lon) {
                        break;
                    }
                }
                $this->SendDebug(__FUNCTION__, 'changed positions=' . $i . ' (total=' . count($positions) . ')', 0);
                for ($i--; $i >= 0; $i--) {
                    $latitude = $positions[$i]['latitude'];
                    $longitude = $positions[$i]['longitude'];

                    $this->SendDebug(__FUNCTION__, 'set #' . $i . ' latitude=' . $latitude . ', longitude=' . $longitude, 0);

                    $this->SetValue('LastLatitude', $latitude);
                    $this->SetValue('LastLongitude', $longitude);

                    if ($save_position) {
                        $pos = [
                            'latitude'  => $latitude,
                            'longitude' => $longitude,
                            'activity'  => $mowerActivity,
                        ];
                        $this->SetValue('Position', json_encode($pos));
                    }
                }
            }
        }

        $this->SetValue('LastStatus', time());
    }

    public function ReceiveData($data)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);
        $jbuffer = json_decode($jdata['Buffer'], true);

        $connectionId = $this->GetArrayElem($jbuffer, 'connectionId', '');
        if ($connectionId != '') {
            $ready = (bool) $this->GetArrayElem($jbuffer, 'ready', false);
            $this->SendDebug(__FUNCTION__, 'connectionId=' . $connectionId . ', ready=' . $this->bool2str($ready), 0);
            return;
        }

        $type = $this->GetArrayElem($jbuffer, 'type', '');
        if ($type != '') {
            $id = $this->GetArrayElem($jbuffer, 'id', '');
            if ($id == '' || $id != $this->ReadPropertyString('id')) {
                $this->SendDebug(__FUNCTION__, 'id mismatch, buffer=' . print_r($jbuffer, true), 0);
                return;
            }

            $attributes = $this->GetArrayElem($jbuffer, 'attributes', '');
            $_attributes = false;
            switch ($type) {
                case 'settings-event':
                    $_attributes = [
                        'settings' => $attributes,
                    ];
                    break;
                case 'battery-event-v2':
                    /*
                        "attributes": {
                            "battery": {
                                "batteryPercent": 77
                            }
                        }
                     */
                    $_attributes = $attributes;
                    break;
                case 'calendar-event-v2':
                    /*
                        "attributes": {
                            "calendar": {
                                "tasks": [
                                    {
                                        "start": 420,
                                        "duration": 780,
                                        "workAreaId": 78543,
                                        "monday": true,
                                        "tuesday": true,
                                        "wednesday": true,
                                        "thursday": true,
                                        "friday": true,
                                        "saturday": false,
                                        "sunday": false
                                    }
                                ]
                            }
                        }
                     */
                    break;
                case 'cuttingHeight-event-v2':
                    /*
                        "attributes": {
                            "cuttingHeight": {
                                "height": 5
                            }
                        }
                     */
                    $_attributes = [
                        'settings' => [
                            'cuttingHeight' => $attributes['cuttingHeight']['height'],
                        ],
                    ];
                    break;
                case 'headLights-event-v2':
                    /*
                        "attributes": {
                            "headLight": {
                                "mode": "ALWAYS_ON"
                            }
                        }
                     */
                    $_attributes = [
                        'settings' => $attributes,
                    ];
                    break;
                case 'messages-event-v2':
                    /*
                        "attributes": {
                            "message": {
                                "time": 1728034996,
                                "code": 3,
                                "severity": "WARNING",
                                "latitude": 57.7086409,
                                "longitude": 14.1678988
                            }
                        }
                     */
                    break;
                case 'mower-event-v2':
                    /*
                        "attributes": {
                            "mower": {
                                "mode": "MAIN_AREA",
                                "activity": "MOWING",
                                "inactiveReason": "NONE",
                                "state": "IN_OPERATION",
                                "errorCode": 0,
                                "isErrorConfirmable": false,
                                "workAreaId": "78555",
                                "errorCodeTimestamp": 0 // In local time for the mower
                            }
                        }
                     */
                    $_attributes = $attributes;
                    break;
                case 'planner-event-v2':
                    /*
                        "attributes": {
                            "planner": {
                                "nextStartTimestamp": 0, // In local time for the mower
                                "override": {
                                    "action": "FORCE_MOW"
                                },
                                "restrictedReason": "PARK_OVERRIDE",
                                "externalReason": 7
                            }
                        }
                     */
                    $_attributes = $attributes;
                    break;
                case 'position-event-v2':
                    /*
                        "attributes": {
                            "position": {
                                "latitude": 57.70074,
                                "longitude": 14.4787133
                            }
                        }
                     */
                    $_attributes = [
                        'positions' => [
                            $attributes['position'],
                        ],
                    ];
                    break;
                default:
                    break;
            }

            if ($_attributes == false) {
                $this->SendDebug(__FUNCTION__, 'unknown/unsupported event type=' . $type . ', attributes=' . print_r($attributes, true), 0);
                return;
            }

            $this->SendDebug(__FUNCTION__, 'type=' . $type . ', attributes=' . print_r($_attributes, true), 0);
            $this->DecodeAttributes($_attributes);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'unsupported message ' . print_r($jbuffer, true), 0);
        return;
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'UpdateStatus':
                $this->UpdateStatus();
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

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
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
        $val2txt = [
            'MAIN_AREA'          => 'main area',
            'SECONDARY_AREA'     => 'secondary area',
            'HOME'               => 'remain in base',
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

    private function decode_mowerState($val)
    {
        $val2code = [
            'UNKNOWN'           => self::$STATE_UNKNOWN,
            'NOT_APPLICABLE'    => self::$STATE_NOT_APPLICABLE,
            'PAUSED'            => self::$STATE_PAUSED,
            'IN_OPERATION'      => self::$STATE_IN_OPERATION,
            'WAIT_UPDATING'     => self::$STATE_WAIT_UPDATING,
            'WAIT_POWER_UP'     => self::$STATE_WAIT_POWER_UP,
            'RESTRICTED'        => self::$STATE_RESTRICTED,
            'OFF'               => self::$STATE_OFF,
            'STOPPED'           => self::$STATE_STOPPED,
            'ERROR'             => self::$STATE_ERROR,
            'FATAL_ERROR'       => self::$STATE_FATAL_ERROR,
            'ERROR_AT_POWER_UP' => self::$STATE_ERROR_AT_POWER_UP,
        ];

        if (isset($val2code[$val])) {
            $code = $val2code[$val];
        } else {
            $msg = 'unknown value "' . $val . '"';
            $this->LogMessage(__FUNCTION__ . ': ' . $msg, KL_WARNING);
            $this->SendDebug(__FUNCTION__, $msg, 0);
            $code = self::$STATE_UNKNOWN;
        }
        return $code;
    }

    private function decode_restrictedReason($val)
    {
        $val2txt = [
            'NONE'                     => 'none',
            'UNKNOWN'                  => 'unknown',
            'NOT_APPLICABLE'           => 'not applicable',
            'WEEK_SCHEDULE'            => 'week schedule',
            'PARK_OVERRIDE'            => 'park overwrite',
            'SENSOR'                   => 'sensor',
            'DAILY_LIMIT'              => 'daily limit',
            'UNTIL_FURTHER_NOTICE'     => 'until further notice',
            'FROST'                    => 'frost',
            'FOTA'                     => 'firmware update',
            'EXTERNAL'                 => 'external control',
            'ALL_WORK_AREAS_COMPLETED' => 'all areas completed',
            'SEARCHING_FOR_SATELLITES' => 'searching for satellites',
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
            $code = self::$ACTIVITY_UNKNOWN;
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
            $this->SendDebug(__FUNCTION__, 'has no active parent/gateway', 0);
            $log_no_parent = $this->ReadPropertyBoolean('log_no_parent');
            if ($log_no_parent) {
                $this->LogMessage($this->Translate('Instance has no active gateway'), KL_WARNING);
            }
            return false;
        }

        $id = $this->ReadPropertyString('id');
        if ($id == '') {
            return false;
        }
        $sdata = [
            'DataID'    => '{4C746488-C0FD-A850-3532-8DEBC042C970}', // an AutomowerConnectIO
            'CallerID'  => $this->InstanceID,
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
        $this->MaintainTimer('UpdateStatus', 15 * 1000);
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
