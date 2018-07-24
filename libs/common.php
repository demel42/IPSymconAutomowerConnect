<?php

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

trait AutomowerCommon
{
    protected function SetValue($Ident, $Value)
    {
        @$varID = $this->GetIDForIdent($Ident);
        if ($varID == false) {
            $this->SendDebug(__FUNCTION__, 'missing variable ' . $Ident, 0);
            return;
        }

        if (IPS_GetKernelVersion() >= 5) {
            $ret = parent::SetValue($Ident, $Value);
        } else {
            $ret = SetValue($varID, $Value);
        }
        if ($ret == false) {
            $this->SendDebug(__FUNCTION__, 'mismatch of value "' . $Value . '" for variable ' . $Ident, 0);
        }
    }

    protected function GetValue($Ident)
    {
        @$varID = $this->GetIDForIdent($Ident);
        if ($varID == false) {
            $this->SendDebug(__FUNCTION__, 'missing variable ' . $Ident, 0);
            return false;
        }

        if (IPS_GetKernelVersion() >= 5) {
            $ret = parent::GetValue($Ident);
        } else {
            $ret = GetValue($varID);
        }

        return $ret;
    }

    private function CreateVarProfile($Name, $ProfileType, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Icon, $Asscociations = '')
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, $ProfileType);
            IPS_SetVariableProfileText($Name, '', $Suffix);
            IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
            IPS_SetVariableProfileDigits($Name, $Digits);
            IPS_SetVariableProfileIcon($Name, $Icon);
            if ($Asscociations != '') {
                foreach ($Asscociations as $a) {
                    $w = isset($a['Wert']) ? $a['Wert'] : '';
                    $n = isset($a['Name']) ? $a['Name'] : '';
                    $i = isset($a['Icon']) ? $a['Icon'] : '';
                    $f = isset($a['Farbe']) ? $a['Farbe'] : 0;
                    IPS_SetVariableProfileAssociation($Name, $w, $n, $i, $f);
                }
            }
        }
    }

    // Inspired from module SymconTest/HookServe
    private function RegisterHook($WebHook)
    {
        $ids = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');
        if (count($ids) > 0) {
            $hooks = json_decode(IPS_GetProperty($ids[0], 'Hooks'), true);
            $found = false;
            foreach ($hooks as $index => $hook) {
                if ($hook['Hook'] == $WebHook) {
                    if ($hook['TargetID'] == $this->InstanceID) {
                        return;
                    }
                    $hooks[$index]['TargetID'] = $this->InstanceID;
                    $found = true;
                }
            }
            if (!$found) {
                $hooks[] = ['Hook' => $WebHook, 'TargetID' => $this->InstanceID];
            }
            IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
            IPS_ApplyChanges($ids[0]);
        }
    }

    // Inspired from module SymconTest/HookServe
    private function GetMimeType($extension)
    {
        $lines = file(IPS_GetKernelDirEx() . 'mime.types');
        foreach ($lines as $line) {
            $type = explode("\t", $line, 2);
            if (count($type) == 2) {
                $types = explode(' ', trim($type[1]));
                foreach ($types as $ext) {
                    if ($ext == $extension) {
                        return $type[0];
                    }
                }
            }
        }
        return 'text/plain';
    }

    protected function LogMessage($Message, $Severity)
    {
        if (IPS_GetKernelVersion() >= 5) {
            switch ($Severity) {
                case KL_NOTIFY:
                case KL_WARNING:
                case KL_ERROR:
                case KL_DEBUG:
                    $this->LogMessage($Message, $Severity);
                    break;
                default:
                    echo __CLASS__ . '::' . __FUNCTION__ . ': unknown severity ' . $Severity;
                    break;
            }
        } else {
            switch ($Severity) {
                case KL_NOTIFY:
                    IPS_LogMessage(__CLASS__ . '::' . __FUNCTION__, 'INFO: ' . $Message);
                    break;
                case KL_WARNING:
                    IPS_LogMessage(__CLASS__ . '::' . __FUNCTION__, 'WARNUNG: ' . $Message);
                    break;
                case KL_ERROR:
                    echo $Message;
                    break;
                case KL_DEBUG:
                    break;
                default:
                    echo __CLASS__ . '::' . __FUNCTION__ . ': unknown severity ' . $Severity;
                    break;
            }
        }
    }

    private function GetArrayElem($data, $var, $dflt)
    {
        return isset($data[$var]) ? $data[$var] : $dflt;
    }

    private function format_float($number, $dec_points = -1)
    {
        if (is_numeric((float)$number)) {
            $nk = abs($number - floor($number));
            $n = strlen(floatval($nk));
            $d = ($n > 1) ? $n - 2 : 0;
            if ($dec_points == -1 || $dec_points > $d) {
                $dec_points = $d;
            }
            $result = number_format($number, $dec_points, '.', '');
        } else {
			$result = false;
		}
        return $result;
    }
}
