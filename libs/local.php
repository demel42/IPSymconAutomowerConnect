<?php

declare(strict_types=1);

trait AutomowerConnectLocalLib
{
    public static $IS_UNAUTHORIZED = IS_EBASE + 10;
    public static $IS_SERVERERROR = IS_EBASE + 11;
    public static $IS_HTTPERROR = IS_EBASE + 12;
    public static $IS_INVALIDDATA = IS_EBASE + 13;
    public static $IS_NODATA = IS_EBASE + 14;
    public static $IS_NOLOGIN = IS_EBASE + 15;
    public static $IS_FORBIDDEN = IS_EBASE + 16;
    public static $IS_INVALIDACCOUNT = IS_EBASE + 17;
    public static $IS_DEVICE_MISSING = IS_EBASE + 18;

    private function GetFormStatus()
    {
        $formStatus = $this->GetCommonFormStatus();

        $formStatus[] = ['code' => self::$IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => self::$IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => self::$IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => self::$IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];
        $formStatus[] = ['code' => self::$IS_NODATA, 'icon' => 'error', 'caption' => 'Instance is inactive (no data)'];
        $formStatus[] = ['code' => self::$IS_NOLOGIN, 'icon' => 'error', 'caption' => 'Instance is inactive (not logged in)'];
        $formStatus[] = ['code' => self::$IS_FORBIDDEN, 'icon' => 'error', 'caption' => 'Instance is inactive (forbidden)'];
        $formStatus[] = ['code' => self::$IS_INVALIDACCOUNT, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid account)'];
        $formStatus[] = ['code' => self::$IS_DEVICE_MISSING, 'icon' => 'error', 'caption' => 'Instance is inactive (device missing)'];

        return $formStatus;
    }

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = self::$STATUS_VALID;
                break;
            case self::$IS_UNAUTHORIZED:
            case self::$IS_SERVERERROR:
            case self::$IS_HTTPERROR:
            case self::$IS_INVALIDDATA:
            case self::$IS_FORBIDDEN:
            case self::$IS_INVALIDACCOUNT:
                $class = self::$STATUS_RETRYABLE;
                break;
            case self::$IS_NOLOGIN:
                @$connection_type = $this->ReadPropertyInteger('connection_type');
                // bei Entwicklerschlüssel macht das Modul das Login selber
                $class = $connection_type == self::$CONNECTION_DEVELOPER ? self::$STATUS_RETRYABLE : self::$STATUS_INVALID;
                break;
            default:
                $class = self::$STATUS_INVALID;
                break;
        }

        return $class;
    }

    // MowerState
    public static $STATE_UNKNOWN = 0;
    public static $STATE_NOT_APPLICABLE = 1;
    public static $STATE_PAUSED = 2;
    public static $STATE_IN_OPERATION = 3;
    public static $STATE_WAIT_UPDATING = 4;
    public static $STATE_WAIT_POWER_UP = 5;
    public static $STATE_RESTRICTED = 6;
    public static $STATE_OFF = 7;
    public static $STATE_STOPPED = 8;
    public static $STATE_ERROR = 9;
    public static $STATE_FATAL_ERROR = 10;
    public static $STATE_ERROR_AT_POWER_UP = 11;

    // MowerActivity
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

    // HeadlightMode
    public static $HEADLIGHT_ALWAYS_ON = 0;
    public static $HEADLIGHT_ALWAYS_OFF = 1;
    public static $HEADLIGHT_EVENING_ONLY = 2;
    public static $HEADLIGHT_EVENING_AND_NIGHT = 3;

    // Authentifizierungs-Methode
    public static $CONNECTION_UNDEFINED = 0;
    public static $CONNECTION_OAUTH = 1;
    public static $CONNECTION_DEVELOPER = 2;

    private function InstallVarProfiles(bool $reInstall = false)
    {
        if ($reInstall) {
            $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);
        }

        $associations = [
            ['Wert' => self::$ACTION_RESUME_SCHEDULE, 'Name' => $this->Translate('next schedule'), 'Farbe' => -1],
            ['Wert' => 3, 'Name' => $this->Translate('3 hours'), 'Farbe' => -1],
            ['Wert' => 6, 'Name' => $this->Translate('6 hours'), 'Farbe' => -1],
            ['Wert' => 12, 'Name' => $this->Translate('12 hours'), 'Farbe' => -1],
            ['Wert' => 24, 'Name' => $this->Translate('1 day'), 'Farbe' => -1],
            ['Wert' => 48, 'Name' => $this->Translate('2 days'), 'Farbe' => -1],
            ['Wert' => 72, 'Name' => $this->Translate('3 days'), 'Farbe' => -1],
            ['Wert' => 96, 'Name' => $this->Translate('4 days'), 'Farbe' => -1],
            ['Wert' => 120, 'Name' => $this->Translate('5 days'), 'Farbe' => -1],
            ['Wert' => 144, 'Name' => $this->Translate('6 days'), 'Farbe' => -1],
            ['Wert' => 168, 'Name' => $this->Translate('7 days'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('Automower.ActionStart', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' =>  self::$ACTION_PARK_UNTIL_FURTHER_NOTICE, 'Name' => $this->Translate('until further notice'), 'Farbe' => -1],
            ['Wert' => self::$ACTION_PARK_UNTIL_NEXT_SCHEDULE, 'Name' => $this->Translate('until next schedule'), 'Farbe' => -1],
            ['Wert' => 3, 'Name' => $this->Translate('3 hours'), 'Farbe' => -1],
            ['Wert' => 6, 'Name' => $this->Translate('6 hours'), 'Farbe' => -1],
            ['Wert' => 12, 'Name' => $this->Translate('12 hours'), 'Farbe' => -1],
            ['Wert' => 24, 'Name' => $this->Translate('1 day'), 'Farbe' => -1],
            ['Wert' => 48, 'Name' => $this->Translate('2 days'), 'Farbe' => -1],
            ['Wert' => 72, 'Name' => $this->Translate('3 days'), 'Farbe' => -1],
            ['Wert' => 96, 'Name' => $this->Translate('4 days'), 'Farbe' => -1],
            ['Wert' => 120, 'Name' => $this->Translate('5 days'), 'Farbe' => -1],
            ['Wert' => 144, 'Name' => $this->Translate('6 days'), 'Farbe' => -1],
            ['Wert' => 168, 'Name' => $this->Translate('7 days'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('Automower.ActionPark', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$ACTION_PAUSE, 'Name' => $this->Translate('Pause'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('Automower.ActionPause', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$STATE_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => -1],
            ['Wert' => self::$STATE_NOT_APPLICABLE, 'Name' => $this->Translate('not applicable'), 'Farbe' => -1],
            ['Wert' => self::$STATE_PAUSED, 'Name' => $this->Translate('paused'), 'Farbe' => -1],
            ['Wert' => self::$STATE_IN_OPERATION, 'Name' => $this->Translate('in operation'), 'Farbe' => -1],
            ['Wert' => self::$STATE_WAIT_UPDATING, 'Name' => $this->Translate('wait updating'), 'Farbe' => -1],
            ['Wert' => self::$STATE_WAIT_POWER_UP, 'Name' => $this->Translate('wait power up'), 'Farbe' => -1],
            ['Wert' => self::$STATE_RESTRICTED, 'Name' => $this->Translate('restricted'), 'Farbe' => -1],
            ['Wert' => self::$STATE_OFF, 'Name' => $this->Translate('off'), 'Farbe' => -1],
            ['Wert' => self::$STATE_STOPPED, 'Name' => $this->Translate('stopped'), 'Farbe' => -1],
            ['Wert' => self::$STATE_ERROR, 'Name' => $this->Translate('error'), 'Farbe' => -1],
            ['Wert' => self::$STATE_FATAL_ERROR, 'Name' => $this->Translate('fatal error'), 'Farbe' => -1],
            ['Wert' => self::$STATE_ERROR_AT_POWER_UP, 'Name' => $this->Translate('error at power up'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('Automower.State', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$ACTIVITY_UNKNOWN, 'Name' => $this->Translate('unknown'), 'Farbe' => -1],
            ['Wert' => self::$ACTIVITY_NOT_APPLICABLE, 'Name' => $this->Translate('manual intervention required'), 'Farbe' => -1],
            ['Wert' => self::$ACTIVITY_ERROR, 'Name' => $this->Translate('error'), 'Farbe' => -1],
            ['Wert' => self::$ACTIVITY_DISABLED, 'Name' => $this->Translate('disabled'), 'Farbe' => -1],
            ['Wert' => self::$ACTIVITY_PARKED, 'Name' => $this->Translate('parked'), 'Farbe' => -1],
            ['Wert' => self::$ACTIVITY_CHARGING, 'Name' => $this->Translate('charging'), 'Farbe' => -1],
            ['Wert' => self::$ACTIVITY_PAUSED, 'Name' => $this->Translate('paused'), 'Farbe' => -1],
            ['Wert' => self::$ACTIVITY_LEAVING, 'Name' => $this->Translate('leaving base'), 'Farbe' => -1],
            ['Wert' => self::$ACTIVITY_GOING_HOME, 'Name' => $this->Translate('going home'), 'Farbe' => -1],
            ['Wert' => self::$ACTIVITY_CUTTING, 'Name' => $this->Translate('cutting'), 'Farbe' => -1],
            ['Wert' => self::$ACTIVITY_STOPPED, 'Name' => $this->Translate('stopped'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('Automower.Activity', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$HEADLIGHT_ALWAYS_ON, 'Name' => $this->Translate('Always on'), 'Farbe' => -1],
            ['Wert' => self::$HEADLIGHT_ALWAYS_OFF, 'Name' => $this->Translate('Always off'), 'Farbe' => -1],
            ['Wert' => self::$HEADLIGHT_EVENING_ONLY, 'Name' => $this->Translate('Evening only'), 'Farbe' => -1],
            ['Wert' => self::$HEADLIGHT_EVENING_AND_NIGHT, 'Name' => $this->Translate('Evening and night'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('Automower.HeadlightMode', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => 0, 'Name' => '0 (2,0 cm)', 'Farbe' => -1],
            ['Wert' => 1, 'Name' => '1 (2,4 cm)', 'Farbe' => -1],
            ['Wert' => 2, 'Name' => '2 (2,9 cm)', 'Farbe' => -1],
            ['Wert' => 3, 'Name' => '3 (3,3 cm)', 'Farbe' => -1],
            ['Wert' => 4, 'Name' => '4 (3,8 cm)', 'Farbe' => -1],
            ['Wert' => 5, 'Name' => '5 (4,2 cm)', 'Farbe' => -1],
            ['Wert' => 6, 'Name' => '6 (4,6 cm)', 'Farbe' => -1],
            ['Wert' => 7, 'Name' => '7 (5,1 cm)', 'Farbe' => -1],
            ['Wert' => 8, 'Name' => '8 (5,5 cm)', 'Farbe' => -1],
            ['Wert' => 9, 'Name' => '9 (6,0 cm)', 'Farbe' => -1],
        ];
        $this->CreateVarProfile('Automower.CuttingHeight', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $this->CreateVarProfile('Automower.Time', VARIABLETYPE_INTEGER, ' s', 0, 0, 0, 0, '', [], $reInstall);

        $associations = [
            ['Wert' =>  0, 'Name' => '-', 'Farbe' => -1],
            ['Wert' => 1, 'Name' => $this->Translate('Outside working area'), 'Farbe' => 0xFFA500],
            ['Wert' => 2, 'Name' => $this->Translate('No loop signal'), 'Farbe' => 0xFF0000],
            ['Wert' => 3, 'Name' => $this->Translate('Wrong loop signal'), 'Farbe' => 0xFF0000],
            ['Wert' => 4, 'Name' => $this->Translate('Loop sensor problem, front'), 'Farbe' => 0xFF0000],
            ['Wert' => 5, 'Name' => $this->Translate('Loop sensor problem, rear'), 'Farbe' => 0xFF0000],
            ['Wert' => 6, 'Name' => $this->Translate('Loop sensor problem, left'), 'Farbe' => 0xFF0000],
            ['Wert' => 7, 'Name' => $this->Translate('Loop sensor problem, right'), 'Farbe' => 0xFF0000],
            ['Wert' => 8, 'Name' => $this->Translate('Wrong PIN code'), 'Farbe' => 0x9932CC],
            ['Wert' => 9, 'Name' => $this->Translate('Trapped'), 'Farbe' => 0x1874CD],
            ['Wert' => 10, 'Name' => $this->Translate('Upside down'), 'Farbe' => 0x1874CD],
            ['Wert' => 11, 'Name' => $this->Translate('Low battery'), 'Farbe' => 0x1874CD],
            ['Wert' => 12, 'Name' => $this->Translate('Empty battery'), 'Farbe' => 0xFFA500],
            ['Wert' => 13, 'Name' => $this->Translate('No drive'), 'Farbe' => 0x1874CD],
            ['Wert' => 14, 'Name' => $this->Translate('Mower lifted'), 'Farbe' => 0x1874CD],
            ['Wert' => 15, 'Name' => $this->Translate('Lifted'), 'Farbe' => 0x1874CD],
            ['Wert' => 16, 'Name' => $this->Translate('Stuck in charging station'), 'Farbe' => 0xFFA500],
            ['Wert' => 17, 'Name' => $this->Translate('Charging station blocked'), 'Farbe' => 0xFFA500],
            ['Wert' => 18, 'Name' => $this->Translate('Collision sensor problem, rear'), 'Farbe' => 0xFF0000],
            ['Wert' => 19, 'Name' => $this->Translate('Collision sensor problem, front'), 'Farbe' => 0xFF0000],
            ['Wert' => 20, 'Name' => $this->Translate('Wheel motor blocked, right'), 'Farbe' => 0xFF0000],
            ['Wert' => 21, 'Name' => $this->Translate('Wheel motor blocked, left'), 'Farbe' => 0xFF0000],
            ['Wert' => 22, 'Name' => $this->Translate('Wheel drive problem, right'), 'Farbe' => 0xFF0000],
            ['Wert' => 23, 'Name' => $this->Translate('Wheel drive problem, left'), 'Farbe' => 0xFF0000],
            ['Wert' => 24, 'Name' => $this->Translate('Cutting system blocked'), 'Farbe' => 0xFF0000],
            ['Wert' => 25, 'Name' => $this->Translate('Cutting system blocked'), 'Farbe' => 0xFFA500],
            ['Wert' => 26, 'Name' => $this->Translate('Invalid sub-device combination'), 'Farbe' => 0xFF0000],
            ['Wert' => 27, 'Name' => $this->Translate('Settings restored'), 'Farbe' => -1],
            ['Wert' => 28, 'Name' => $this->Translate('Memory circuit problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 29, 'Name' => $this->Translate('Slope too steep'), 'Farbe' => 0xFF0000],
            ['Wert' => 30, 'Name' => $this->Translate('Charging system problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 31, 'Name' => $this->Translate('STOP button problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 32, 'Name' => $this->Translate('Tilt sensor problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 33, 'Name' => $this->Translate('Mower tilted'), 'Farbe' => 0x1874CD],
            ['Wert' => 34, 'Name' => $this->Translate('Cutting stopped - slope too steep'), 'Farbe' => 0x1874CD],
            ['Wert' => 35, 'Name' => $this->Translate('Wheel motor overloaded, right'), 'Farbe' => 0xFF0000],
            ['Wert' => 36, 'Name' => $this->Translate('Wheel motor overloaded, left'), 'Farbe' => 0xFF0000],
            ['Wert' => 37, 'Name' => $this->Translate('Charging current too high'), 'Farbe' => 0xFF0000],
            ['Wert' => 38, 'Name' => $this->Translate('Electronic problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 39, 'Name' => $this->Translate('Cutting motor problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 40, 'Name' => $this->Translate('Limited cutting height range'), 'Farbe' => 0xFF0000],
            ['Wert' => 41, 'Name' => $this->Translate('Unexpected cutting height adj'), 'Farbe' => 0xFF0000],
            ['Wert' => 42, 'Name' => $this->Translate('Limited cutting height range'), 'Farbe' => 0xFF0000],
            ['Wert' => 43, 'Name' => $this->Translate('Cutting height problem, drive'), 'Farbe' => 0xFF0000],
            ['Wert' => 44, 'Name' => $this->Translate('Cutting height problem, curr'), 'Farbe' => 0xFF0000],
            ['Wert' => 45, 'Name' => $this->Translate('Cutting height problem, dir'), 'Farbe' => 0xFF0000],
            ['Wert' => 46, 'Name' => $this->Translate('Cutting height blocked'), 'Farbe' => 0xFF0000],
            ['Wert' => 47, 'Name' => $this->Translate('Cutting height problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 48, 'Name' => $this->Translate('No response from charger'), 'Farbe' => 0xFF0000],
            ['Wert' => 49, 'Name' => $this->Translate('Ultrasonic problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 50, 'Name' => $this->Translate('Guide 1 not found'), 'Farbe' => 0xFF0000],
            ['Wert' => 51, 'Name' => $this->Translate('Guide 2 not found'), 'Farbe' => 0xFF0000],
            ['Wert' => 52, 'Name' => $this->Translate('Guide 3 not found'), 'Farbe' => 0xFF0000],
            ['Wert' => 53, 'Name' => $this->Translate('GPS navigation problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 54, 'Name' => $this->Translate('Weak GPS signal'), 'Farbe' => 0xFF0000],
            ['Wert' => 55, 'Name' => $this->Translate('Difficult finding home'), 'Farbe' => 0xFF0000],
            ['Wert' => 56, 'Name' => $this->Translate('Guide calibration accomplished'), 'Farbe' => 0xFF0000],
            ['Wert' => 57, 'Name' => $this->Translate('Guide calibration failed'), 'Farbe' => 0xFF0000],
            ['Wert' => 58, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 59, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 60, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 61, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 62, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 63, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 64, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 65, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 66, 'Name' => $this->Translate('Battery problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 67, 'Name' => $this->Translate('Battery problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 68, 'Name' => $this->Translate('Temporary battery problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 69, 'Name' => $this->Translate('Alarm! Mower switched off'), 'Farbe' => 0xFF0000],
            ['Wert' => 70, 'Name' => $this->Translate('Alarm! Mower stopped'), 'Farbe' => 0xFF0000],
            ['Wert' => 71, 'Name' => $this->Translate('Alarm! Mower lifted'), 'Farbe' => 0xFF0000],
            ['Wert' => 72, 'Name' => $this->Translate('Alarm! Mower tilted'), 'Farbe' => 0xFF0000],
            ['Wert' => 73, 'Name' => $this->Translate('Alarm! Mower in motion'), 'Farbe' => 0xFF0000],
            ['Wert' => 74, 'Name' => $this->Translate('Alarm! Outside geofence'), 'Farbe' => 0xFF0000],
            ['Wert' => 75, 'Name' => $this->Translate('Connection changed'), 'Farbe' => 0xFF0000],
            ['Wert' => 76, 'Name' => $this->Translate('Connection NOT changed'), 'Farbe' => 0xFF0000],
            ['Wert' => 77, 'Name' => $this->Translate('Com board not available'), 'Farbe' => 0xFF0000],
            ['Wert' => 78, 'Name' => $this->Translate('Mower has slipped'), 'Farbe' => 0xFF0000],
            ['Wert' => 79, 'Name' => $this->Translate('Invalid battery combination'), 'Farbe' => 0xFF0000],
            ['Wert' => 80, 'Name' => $this->Translate('Cutting system imbalance warning'), 'Farbe' => 0xFF0000],
            ['Wert' => 81, 'Name' => $this->Translate('Safety function faulty'), 'Farbe' => 0xFF0000],
            ['Wert' => 82, 'Name' => $this->Translate('Wheel motor blocked, rear right'), 'Farbe' => 0xFF0000],
            ['Wert' => 83, 'Name' => $this->Translate('Wheel motor blocked, rear left'), 'Farbe' => 0xFF0000],
            ['Wert' => 84, 'Name' => $this->Translate('Wheel drive problem, rear right'), 'Farbe' => 0xFF0000],
            ['Wert' => 85, 'Name' => $this->Translate('Wheel drive problem, rear left'), 'Farbe' => 0xFF0000],
            ['Wert' => 86, 'Name' => $this->Translate('Wheel motor overloaded, rear right'), 'Farbe' => 0xFF0000],
            ['Wert' => 87, 'Name' => $this->Translate('Wheel motor overloaded, rear left'), 'Farbe' => 0xFF0000],
            ['Wert' => 88, 'Name' => $this->Translate('Angular sensor problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 89, 'Name' => $this->Translate('Invalid system configuration'), 'Farbe' => 0xFF0000],
            ['Wert' => 90, 'Name' => $this->Translate('No power in charging station'), 'Farbe' => 0xFF0000],
            ['Wert' => 91, 'Name' => $this->Translate('Switch cord problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 92, 'Name' => $this->Translate('Work area not valid'), 'Farbe' => 0xFF0000],
            ['Wert' => 93, 'Name' => $this->Translate('No accurate position from satellites'), 'Farbe' => 0xFF0000],
            ['Wert' => 94, 'Name' => $this->Translate('Reference station communication problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 95, 'Name' => $this->Translate('Folding sensor activated'), 'Farbe' => 0xFF0000],
            ['Wert' => 96, 'Name' => $this->Translate('Right brush motor overloaded'), 'Farbe' => 0xFF0000],
            ['Wert' => 97, 'Name' => $this->Translate('Left brush motor overloaded'), 'Farbe' => 0xFF0000],
            ['Wert' => 98, 'Name' => $this->Translate('Ultrasonic Sensor 1 defect'), 'Farbe' => 0xFF0000],
            ['Wert' => 99, 'Name' => $this->Translate('Ultrasonic Sensor 2 defect'), 'Farbe' => 0xFF0000],
            ['Wert' => 100, 'Name' => $this->Translate('Ultrasonic Sensor 3 defect'), 'Farbe' => 0xFF0000],
            ['Wert' => 101, 'Name' => $this->Translate('Ultrasonic Sensor 4 defect'), 'Farbe' => 0xFF0000],
            ['Wert' => 102, 'Name' => $this->Translate('Cutting drive motor 1 defect'), 'Farbe' => 0xFF0000],
            ['Wert' => 103, 'Name' => $this->Translate('Cutting drive motor 2 defect'), 'Farbe' => 0xFF0000],
            ['Wert' => 104, 'Name' => $this->Translate('Cutting drive motor 3 defect'), 'Farbe' => 0xFF0000],
            ['Wert' => 105, 'Name' => $this->Translate('Lift Sensor defect'), 'Farbe' => 0xFF0000],
            ['Wert' => 106, 'Name' => $this->Translate('Collision sensor defect'), 'Farbe' => 0xFF0000],
            ['Wert' => 107, 'Name' => $this->Translate('Docking sensor defect'), 'Farbe' => 0xFF0000],
            ['Wert' => 108, 'Name' => $this->Translate('Folding cutting deck sensor defect'), 'Farbe' => 0xFF0000],
            ['Wert' => 109, 'Name' => $this->Translate('Loop sensor defect'), 'Farbe' => 0xFF0000],
            ['Wert' => 110, 'Name' => $this->Translate('Collision sensor error'), 'Farbe' => 0xFF0000],
            ['Wert' => 111, 'Name' => $this->Translate('No confirmed position'), 'Farbe' => 0xFF0000],
            ['Wert' => 112, 'Name' => $this->Translate('Cutting system major imbalance'), 'Farbe' => 0xFF0000],
            ['Wert' => 113, 'Name' => $this->Translate('Complex working area'), 'Farbe' => 0xFF0000],
            ['Wert' => 114, 'Name' => $this->Translate('Too high discharge current'), 'Farbe' => 0xFF0000],
            ['Wert' => 115, 'Name' => $this->Translate('Too high internal current'), 'Farbe' => 0xFF0000],
            ['Wert' => 116, 'Name' => $this->Translate('High charging power loss'), 'Farbe' => 0xFF0000],
            ['Wert' => 117, 'Name' => $this->Translate('High internal power loss'), 'Farbe' => 0xFF0000],
            ['Wert' => 118, 'Name' => $this->Translate('Charging system problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 119, 'Name' => $this->Translate('Zone generator problem'), 'Farbe' => 0xFF0000],
            ['Wert' => 120, 'Name' => $this->Translate('Internal voltage error'), 'Farbe' => 0xFF0000],
            ['Wert' => 121, 'Name' => $this->Translate('High internal temerature'), 'Farbe' => 0xFF0000],
            ['Wert' => 122, 'Name' => $this->Translate('CAN error'), 'Farbe' => 0xFF0000],
            ['Wert' => 123, 'Name' => $this->Translate('Destination not reachable'), 'Farbe' => 0xFF0000],
        ];
        $this->CreateVarProfile('Automower.Error', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => false, 'Name' => $this->Translate('Disconnected'), 'Farbe' => 0xEE0000],
            ['Wert' => true, 'Name' => $this->Translate('Connected'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('Automower.Connection', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 1, 'Alarm', $associations, $reInstall);

        $this->CreateVarProfile('Automower.Battery', VARIABLETYPE_INTEGER, ' %', 0, 0, 0, 0, 'Battery', [], $reInstall);
        $this->CreateVarProfile('Automower.Location', VARIABLETYPE_FLOAT, ' °', 0, 0, 0, 5, '', [], $reInstall);
        $this->CreateVarProfile('Automower.Duration', VARIABLETYPE_INTEGER, ' min', 0, 0, 0, 0, 'Hourglass', [], $reInstall);
    }
}
