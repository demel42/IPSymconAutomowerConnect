<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/library.php';  // modul-bezogene Funktionen

class AutomowerConnectDevice extends IPSModule
{
    use AutomowerConnectCommon;
    use AutomowerConnectLibrary;

    // MowerStatus
    public static $ACTIVITY_UNKNOWN = 0;
    public static $ACTIVITY_NOT_APPLICABLE = 1;
    public static $ACTIVITY_ERROR = 2;
    public static $ACTIVITY_DISABLED = 3;
    public static $ACTIVITY_PARKED = 4;
    public static $ACTIVITY_CHARGING = 5;
    public static $ACTIVITY_PAUSED = 6;
    public static $ACTIVITY_LEAVING = 7;
    public static $ACTIVITY_GOING_HOME = 8;
    public static $ACTIVITY_CUTTING = 9;
    public static $ACTIVITY_STOPPED = 10;

    // ActionStart
    public static $ACTION_RESUME_SCHEDULE = 0;

    // ActionPark
    public static $ACTION_PARK_UNTIL_FURTHER_NOTICE = -1;
    public static $ACTION_PARK_UNTIL_NEXT_SCHEDULE = 0;

    // ActionPause
    public static $ACTION_PAUSE = 0;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('device_id', ''); // alt, nur noch temporär für DetermineIDs()

        $this->RegisterPropertyString('model', '');
        $this->RegisterPropertyString('serial', '');

        $this->RegisterAttributeString('app_id', '');
        $this->RegisterAttributeString('api_id', '');

        $this->RegisterPropertyBoolean('with_gps', true);
        $this->RegisterPropertyBoolean('save_position', false);

        $this->RegisterPropertyInteger('update_interval', '5');

        $this->RegisterTimer('UpdateStatus', 0, 'AutomowerConnect_UpdateStatus(' . $this->InstanceID . ');');
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        $this->ConnectParent('{AEEFAA3E-8802-086D-6620-E971C03CBEFC}');

        $associations = [];
        $associations[] = ['Wert' => self::$ACTION_RESUME_SCHEDULE, 'Name' => $this->Translate('next schedule'), 'Farbe' => -1];
        $associations[] = ['Wert' =>   3, 'Name' => $this->Translate('3 hours'), 'Farbe' => -1];
        $associations[] = ['Wert' =>   6, 'Name' => $this->Translate('6 hours'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  12, 'Name' => $this->Translate('12 hours'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  24, 'Name' => $this->Translate('1 day'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  48, 'Name' => $this->Translate('2 days'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  72, 'Name' => $this->Translate('3 days'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  96, 'Name' => $this->Translate('4 days'), 'Farbe' => -1];
        $associations[] = ['Wert' => 120, 'Name' => $this->Translate('5 days'), 'Farbe' => -1];
        $associations[] = ['Wert' => 144, 'Name' => $this->Translate('6 days'), 'Farbe' => -1];
        $associations[] = ['Wert' => 168, 'Name' => $this->Translate('7 days'), 'Farbe' => -1];
        $this->CreateVarProfile('Automower.ActionStart', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [];
        $associations[] = ['Wert' =>  self::$ACTION_PARK_UNTIL_FURTHER_NOTICE, 'Name' => $this->Translate('until further notice'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  self::$ACTION_PARK_UNTIL_NEXT_SCHEDULE, 'Name' => $this->Translate('until next schedule'), 'Farbe' => -1];
        $associations[] = ['Wert' =>   3, 'Name' => $this->Translate('3 hours'), 'Farbe' => -1];
        $associations[] = ['Wert' =>   6, 'Name' => $this->Translate('6 hours'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  12, 'Name' => $this->Translate('12 hours'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  24, 'Name' => $this->Translate('1 day'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  48, 'Name' => $this->Translate('2 days'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  72, 'Name' => $this->Translate('3 days'), 'Farbe' => -1];
        $associations[] = ['Wert' =>  96, 'Name' => $this->Translate('4 days'), 'Farbe' => -1];
        $associations[] = ['Wert' => 120, 'Name' => $this->Translate('5 days'), 'Farbe' => -1];
        $associations[] = ['Wert' => 144, 'Name' => $this->Translate('6 days'), 'Farbe' => -1];
        $associations[] = ['Wert' => 168, 'Name' => $this->Translate('7 days'), 'Farbe' => -1];
        $this->CreateVarProfile('Automower.ActionPark', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [];
        $associations[] = ['Wert' => self::$ACTION_PAUSE, 'Name' => $this->Translate('Pause'), 'Farbe' => -1];
        $this->CreateVarProfile('Automower.ActionPause', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [];
        $associations[] = ['Wert' => self::$ACTIVITY_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$ACTIVITY_NOT_APPLICABLE, 'Name' => $this->Translate('manual intervention required'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$ACTIVITY_ERROR, 'Name' => $this->Translate('error'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$ACTIVITY_DISABLED, 'Name' => $this->Translate('disabled'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$ACTIVITY_PARKED, 'Name' => $this->Translate('parked'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$ACTIVITY_CHARGING, 'Name' => $this->Translate('charging'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$ACTIVITY_PAUSED, 'Name' => $this->Translate('paused'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$ACTIVITY_LEAVING, 'Name' => $this->Translate('leaving base'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$ACTIVITY_GOING_HOME, 'Name' => $this->Translate('going home'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$ACTIVITY_CUTTING, 'Name' => $this->Translate('cutting'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$ACTIVITY_STOPPED, 'Name' => $this->Translate('stopped'), 'Farbe' => -1];
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

        $model = $this->ReadPropertyString('model');
        $serial = $this->ReadPropertyString('serial');
        $with_gps = $this->ReadPropertyBoolean('with_gps');
        $save_position = $this->ReadPropertyBoolean('save_position');

        $vpos = 0;
        $this->MaintainVariable('Connected', $this->Translate('Connected'), VARIABLETYPE_BOOLEAN, 'Automower.Connection', $vpos++, true);
        $this->MaintainVariable('Battery', $this->Translate('Battery capacity'), VARIABLETYPE_INTEGER, 'Automower.Battery', $vpos++, true);
        $this->MaintainVariable('OperationMode', $this->Translate('Operation mode'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('MowerStatus', $this->Translate('Mower status'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('MowerActivity', $this->Translate('Mower activity'), VARIABLETYPE_INTEGER, 'Automower.Activity', $vpos++, true);
        $this->MaintainVariable('RestrictedReason', $this->Translate('Restricted reason'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('NextStart', $this->Translate('Next start'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('MowerActionStart', $this->Translate('Start action'), VARIABLETYPE_INTEGER, 'Automower.ActionStart', $vpos++, true);
        $this->MaintainVariable('MowerActionPause', $this->Translate('Pause action'), VARIABLETYPE_INTEGER, 'Automower.ActionPause', $vpos++, true);
        $this->MaintainVariable('MowerActionPark', $this->Translate('Park action'), VARIABLETYPE_INTEGER, 'Automower.ActionPark', $vpos++, true);

        $this->MaintainVariable('DailyReference', $this->Translate('Day of cumulation'), VARIABLETYPE_INTEGER, '~UnixTimestampDate', $vpos++, true);
        $this->MaintainVariable('DailyWorking', $this->Translate('Working time (day)'), VARIABLETYPE_INTEGER, 'Automower.Duration', $vpos++, true);

        $this->MaintainVariable('LastErrorCode', $this->Translate('Last error'), VARIABLETYPE_INTEGER, 'Automower.Error', $vpos++, true);
        $this->MaintainVariable('LastErrorTimestamp', $this->Translate('Timestamp of last error'), VARIABLETYPE_INTEGER, '~UnixTimestampDate', $vpos++, true);
        $this->MaintainVariable('LastLongitude', $this->Translate('Last position (longitude)'), VARIABLETYPE_FLOAT, 'Automower.Location', $vpos++, $with_gps);
        $this->MaintainVariable('LastLatitude', $this->Translate('Last position (latitude)'), VARIABLETYPE_FLOAT, 'Automower.Location', $vpos++, $with_gps);
        $this->MaintainVariable('LastStatus', $this->Translate('Last status'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('Position', $this->Translate('Position'), VARIABLETYPE_STRING, '', $vpos++, $save_position);

        $this->MaintainAction('MowerActionStart', true);
        $this->MaintainAction('MowerActionPause', true);
        $this->MaintainAction('MowerActionPark', true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetTimerInterval('UpdateStatus', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        $this->SetUpdateInterval();
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->UpdateStatus();
        }
        $this->SetStatus(IS_ACTIVE);

        $this->SetSummary($model . '(#' . $serial . ')');
    }

    protected function GetFormElements()
    {
        $api_id = $this->ReadAttributeString('api_id');
        $app_id = $this->ReadAttributeString('app_id');

        $formElements = [];
        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Instance is disabled'
        ];

        #$items = [];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'serial',
            'caption' => 'Serial',
            'enabled' => false
        ];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'model',
            'caption' => 'Model',
            'enabled' => false
        ];
        $items[] = [
            'type'    => 'Label',
            'caption' => $this->Translate('API-ID') . ': ' . $api_id,
        ];
        $items[] = [
            'type'    => 'Label',
            'caption' => $this->Translate('APP-ID') . ': ' . $app_id,
        ];
        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => $items,
            'caption' => 'Basic configuration (don\'t change)'
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
            'type'    => 'Label',
            'caption' => ''
        ];
        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Update status every X minutes'
        ];
        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'update_interval',
            'caption' => 'Minutes'
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
                    'type'   => 'Label',
                    'caption'=> 'manual setting of APPI-ID is only required if not automatic determined'
                ],
                [
                    'type'   => 'ValidationTextBox',
                    'name'   => 'app_id',
                    'caption'=> 'APP-ID'
                ],
                [
                    'type'    => 'Button',
                    'caption' => 'Set APP-ID',
                    'onClick' => 'AutomowerConnect_SetAppID($id, $app_id);'
                ],
            ]
        ];

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

    protected function SetUpdateInterval()
    {
        $min = $this->ReadPropertyInteger('update_interval');
        $msec = $min > 0 ? $min * 1000 * 60 : 0;
        $this->SetTimerInterval('UpdateStatus', $msec);
    }

    private function DetermineIDs()
    {
        $serial = $this->ReadPropertyString('serial');

        $api_id = $this->ReadAttributeString('api_id');
        if ($api_id != '') {
            return;
        }

        // an AutomowerConnectIO
        $sdata = [
            'DataID'    => '{4C746488-C0FD-A850-3532-8DEBC042C970}',
            'Function'  => 'MowerList',
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $cdata = $this->SendDataToParent(json_encode($sdata));
        if ($cdata == '') {
            return;
        }
        $mowers = json_decode($cdata, true);
        foreach ($mowers['data'] as $mower) {
            $api_id = $this->GetArrayElem($mower, 'id', '');
            $sn = $this->GetArrayElem($mower, 'attributes.system.serialNumber', '');
            if ($sn == $serial) {
                $this->SendDebug(__FUNCTION__, 'set api_id=' . $api_id, 0);
                $this->WriteAttributeString('api_id', $api_id);
                break;
            }
        }
        if ($api_id == '') {
            $this->SetStatus(self::$IS_DEVICE_MISSING);
            return;
        }

        $device_id = $this->ReadPropertyString('device_id');
        $this->SendDebug(__FUNCTION__, 'device_id=' . $device_id, 0);
        if ($device_id != '') {
            $app_id = $device_id;
        } else {
            // an AutomowerConnectIO
            $sdata = [
                'DataID'    => '{4C746488-C0FD-A850-3532-8DEBC042C970}',
                'Function'  => 'MowerList4App',
            ];
            $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
            $cdata = $this->SendDataToParent(json_encode($sdata));
            $app_mowers = $cdata != '' ? json_decode($cdata, true) : '';

            $app_id = '';
            if ($app_mowers != '') {
                foreach ($app_mowers as $app_mower) {
                    $id = $app_mower['id'];
                    $this->SendDebug(__FUNCTION__, 'serial=' . $serial . ', id=' . $id, 0);
                    $ids = explode('-', $id);
                    if ($serial == $ids[0]) {
                        $app_id = $id;
                        break;
                    }
                }
            }
            if ($app_id == '') {
                if (count($mowers['data']) == 1 && count($app_mowers) == 1) {
                    $app_id = $app_mowers[0]['id'];
                }
            }
        }
        if ($app_id != '') {
            $this->SendDebug(__FUNCTION__, 'set app_id=' . $app_id, 0);
            $this->WriteAttributeString('app_id', $app_id);
        }
    }

    public function SetAppID(string $app_id)
    {
        $this->SendDebug(__FUNCTION__, 'set app_id=' . $app_id, 0);
        $this->WriteAttributeString('app_id', $app_id);
    }

    public function UpdateStatus()
    {
        $this->DetermineIDs();

        $api_id = $this->ReadAttributeString('api_id');
        if ($api_id == '') {
            return;
        }

        // an AutomowerConnectIO
        $sdata = [
            'DataID'    => '{4C746488-C0FD-A850-3532-8DEBC042C970}',
            'Function'  => 'MowerStatus',
            'api_id'    => $api_id
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

        $this->MaintainAction('MowerActionStart', $enableStart);
        $this->MaintainAction('MowerActionPause', $enablePause);
        $this->MaintainAction('MowerActionPark', $enablePark);

        $this->SendDebug(__FUNCTION__, 'enable ActionStart=' . $this->bool2str($enableStart) . ',  ActionPause=' . $this->bool2str($enablePause) . ', ActionPark=' . $this->bool2str($enablePark), 0);

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

        $with_gps = $this->ReadPropertyBoolean('with_gps');
        $save_position = $this->ReadPropertyBoolean('save_position');
        $app_id = $this->ReadAttributeString('app_id');

        if ($with_gps && $app_id != '') {

            // an AutomowerConnectIO
            $sdata = [
                'DataID'    => '{4C746488-C0FD-A850-3532-8DEBC042C970}',
                'Function'  => 'MowerStatus4App',
                'app_id'    => $app_id
            ];
            $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
            $cdata = $this->SendDataToParent(json_encode($sdata));
            if ($cdata == '') {
                return;
            }
            $status = json_decode($cdata, true);
            $this->SendDebug(__FUNCTION__, 'status=' . print_r($status, true), 0);

            if (isset($status['lastLocations'][0]['longitude']) && isset($status['lastLocations'][0]['latitude'])) {
                $lon = $status['lastLocations'][0]['longitude'];
                $lat = $status['lastLocations'][0]['latitude'];
                $this->SendDebug(__FUNCTION__, 'longitude=' . $lon . ', latitude=' . $lat, 0);
                $this->SetValue('LastLongitude', $lon);
                $this->SetValue('LastLatitude', $lat);
            }

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

        $this->SetValue('LastStatus', time());

        $this->SetUpdateInterval();
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $r = false;
        switch ($Ident) {
            case 'MowerActionStart':
                $r = $this->StartMower((int) $Value);
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            case 'MowerActionPause':
                $r = $this->PauseMower();
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            case 'MowerActionPark':
                $r = $this->ParkMower((int) $Value);
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            default:
                $this->SendDebug(__FUNCTION__, "invalid ident $Ident", 0);
                break;
        }
        if ($r) {
            $this->SetValue($Ident, $Value);
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
        return $this->MowerCmd($data);
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
        return $this->MowerCmd($data);
    }

    private function PauseMower()
    {
        $data = [
            'type' => 'Pause',
        ];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        return $this->MowerCmd($data);
    }

    private function MowerCmd($data)
    {
        $api_id = $this->ReadAttributeString('api_id');
        if ($api_id == '') {
            return false;
        }

        // an AutomowerConnectIO
        $sdata = [
            'DataID'    => '{4C746488-C0FD-A850-3532-8DEBC042C970}',
            'Function'  => 'MowerCmd',
            'api_id'    => $api_id,
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
