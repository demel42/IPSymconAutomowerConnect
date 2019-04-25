<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/library.php';  // modul-bezogene Funktionen

class AutomowerConfig extends IPSModule
{
    use AutomowerCommon;
    use AutomowerLibrary;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');

        $ok = true;
        if ($user == '' || $password == '') {
            $ok = false;
        }
        $this->SetStatus($ok ? IS_ACTIVE : IS_UNAUTHORIZED);
    }

    public function GetConfigurationForm()
    {
        $formElements = [];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'user', 'caption' => 'User'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'password', 'caption' => 'Password'];

        $options = [];

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');

        if ($user != '' || $password != '') {
            $mowers = $this->GetMowerList();
            if ($mowers != '') {
                foreach ($mowers as $mower) {
                    $name = $mower['name'];
                    $options[] = ['label' => $name, 'value' => $name];
                }
            }
        }

        $formActions = [];
        $formActions[] = ['type' => 'Select', 'name' => 'mower_name', 'caption' => 'Mower-Name', 'options' => $options];
        $formActions[] = [
                            'type'    => 'Button',
                            'caption' => 'Import of mower',
                            'confirm' => 'Triggering the function creates the instances for the selected Automower-device. Are you sure?',
                            'onClick' => 'AutomowerConfig_Doit($id, $mower_name);'
                        ];
        $formActions[] = ['type' => 'Label', 'label' => '____________________________________________________________________________________________________'];
        $formActions[] = [
                            'type'    => 'Button',
                            'caption' => 'Module description',
                            'onClick' => 'echo "https://github.com/demel42/IPSymconAutomowerConnect/blob/master/README.md";'
                        ];

        $formStatus = [];
        $formStatus[] = ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => IS_DELETING, 'icon' => 'inactive', 'caption' => 'Instance is deleted'];
        $formStatus[] = ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => IS_NOTCREATED, 'icon' => 'inactive', 'caption' => 'Instance is not created'];

        $formStatus[] = ['code' => IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];
        $formStatus[] = ['code' => IS_DEVICE_MISSING, 'icon' => 'error', 'caption' => 'Instance is inactive (device missing)'];

        return json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
    }

    private function FindOrCreateInstance($device_id, $name, $info, $properties, $pos)
    {
        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');

        $instID = '';

        $instIDs = IPS_GetInstanceListByModuleID('{B64D5F1C-6F12-474B-8DBC-3B263E67954E}');
        foreach ($instIDs as $id) {
            $cfg = IPS_GetConfiguration($id);
            $jcfg = json_decode($cfg, true);
            if (!isset($jcfg['device_id'])) {
                continue;
            }
            if ($jcfg['device_id'] == $device_id) {
                $instID = $id;
                break;
            }
        }

        if ($instID == '') {
            $instID = IPS_CreateInstance('{B64D5F1C-6F12-474B-8DBC-3B263E67954E}');
            if ($instID == '') {
                echo 'unable to create instance "' . $name . '"';
                return $instID;
            }
            IPS_SetProperty($instID, 'user', $user);
            IPS_SetProperty($instID, 'password', $password);
            IPS_SetProperty($instID, 'device_id', $device_id);
            foreach ($properties as $key => $property) {
                IPS_SetProperty($instID, $key, $property);
            }
            IPS_SetName($instID, $name);
            IPS_SetInfo($instID, $info);
            IPS_SetPosition($instID, $pos);
        }

        IPS_ApplyChanges($instID);

        return $instID;
    }

    public function Doit(string $mower_name = null)
    {
        $err = '';
        $statuscode = 0;
        $do_abort = false;

        $mowers = $this->GetMowerList();
        if ($mower_name != null && $mowers != '') {
            $mower_found = false;
            foreach ($mowers as $mower) {
                if ($mower_name == $mower['name']) {
                    $mower_found = true;
                    break;
                }
            }
            if (!$mower_found) {
                $err = "mower \"$mower_name\" don't exists";
                $statuscode = 202;
                $do_abort = true;
            }
        } else {
            $err = 'no data';
            $statuscode = 204;
            $do_abort = true;
        }

        if ($do_abort) {
            echo "statuscode=$statuscode, err=$err";
            $this->SendDebug(__FUNCTION__, $err, 0);
            $this->SetStatus($statuscode);
            return -1;
        }

        $this->SetStatus(102);

        $device_id = $mower['id'];
        $name = $mower['name'];
        $model = $mower['model'];
        switch ($model) {
            case 'G':
            case 'H':
                $with_gps = true;
                break;
            default:
                $with_gps = false;
                break;
        }

        $info = 'Automower  ' . $model;
        $properties = [
                'model'       => $model,
                'with_gps'    => $with_gps
            ];
        $pos = 1000;
        $instID = $this->FindOrCreateInstance($device_id, $name, $info, $properties, $pos++);
    }
}
