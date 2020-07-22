<?php

declare(strict_types=1);

if (!defined('STATUS_INVALID')) {
    define('STATUS_INVALID', 0);
    define('STATUS_VALID', 1);
    define('STATUS_RETRYABLE', 2);
}

trait AutomowerConnectLocal
{
    public static $IS_UNAUTHORIZED = IS_EBASE + 1;
    public static $IS_SERVERERROR = IS_EBASE + 2;
    public static $IS_HTTPERROR = IS_EBASE + 3;
    public static $IS_INVALIDDATA = IS_EBASE + 4;
    public static $IS_NOSYMCONCONNECT = IS_EBASE + 5;
    public static $IS_NOLOGIN = IS_EBASE + 6;
    public static $IS_FORBIDDEN = IS_EBASE + 7;
    public static $IS_INVALIDACCOUNT = IS_EBASE + 8;
    public static $IS_DEVICE_MISSING = IS_EBASE + 9;

    private function GetFormStatus()
    {
        $formStatus = [];
        $formStatus[] = ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => IS_DELETING, 'icon' => 'inactive', 'caption' => 'Instance is deleted'];
        $formStatus[] = ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => IS_NOTCREATED, 'icon' => 'inactive', 'caption' => 'Instance is not created'];

        $formStatus[] = ['code' => self::$IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => self::$IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => self::$IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => self::$IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];
        $formStatus[] = ['code' => self::$IS_NOSYMCONCONNECT, 'icon' => 'error', 'caption' => 'Instance is inactive (no Symcon-Connect)'];
        $formStatus[] = ['code' => self::$IS_NOLOGIN, 'icon' => 'error', 'caption' => 'Instance is inactive (not logged in)'];
        $formStatus[] = ['code' => self::$IS_FORBIDDEN, 'icon' => 'error', 'caption' => 'Instance is inactive (forbidden)'];
        $formStatus[] = ['code' => self::$IS_INVALIDACCOUNT, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid account)'];
        $formStatus[] = ['code' => self::$IS_DEVICE_MISSING, 'icon' => 'error', 'caption' => 'Instance is inactive (device missing)'];

        return $formStatus;
    }

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = STATUS_VALID;
                break;
            case self::$IS_NODATA:
            case self::$IS_UNAUTHORIZED:
            case self::$IS_FORBIDDEN:
            case self::$IS_SERVERERROR:
            case self::$IS_HTTPERROR:
            case self::$IS_INVALIDDATA:
            case self::$IS_INVALIDACCOUNT:
                $class = STATUS_RETRYABLE;
                break;
            default:
                $class = STATUS_INVALID;
                break;
        }

        return $class;
    }

    private function GetStatusText()
    {
        $txt = false;
        $status = $this->GetStatus();
        $formStatus = $this->GetFormStatus();
        foreach ($formStatus as $item) {
            if ($item['code'] == $status) {
                $txt = $item['caption'];
                break;
            }
        }

        return $txt;
    }
}
