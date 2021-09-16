<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';	// globale Funktionen
require_once __DIR__ . '/../libs/local.php';	// lokale Funktionen

class AutomowerConfig extends IPSModule
{
    use AutomowerCommonLib;
    use AutomowerLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');

        $this->RegisterPropertyInteger('ImportCategoryID', 0);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        $propertyNames = ['ImportCategoryID'];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid > 0) {
                $this->RegisterReference($oid);
            }
        }

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        if ($user == '' || $password == '') {
            $this->SetStatus(self::$IS_UNAUTHORIZED);
            return;
        }

        $this->SetStatus(IS_ACTIVE);
    }

    private function SetLocation()
    {
        $category = $this->ReadPropertyInteger('ImportCategoryID');
        $tree_position = [];
        if ($category > 0 && IPS_ObjectExists($category)) {
            $tree_position[] = IPS_GetName($category);
            $parent = IPS_GetObject($category)['ParentID'];
            while ($parent > 0) {
                if ($parent > 0) {
                    $tree_position[] = IPS_GetName($parent);
                }
                $parent = IPS_GetObject($parent)['ParentID'];
            }
            $tree_position = array_reverse($tree_position);
        }
        return $tree_position;
    }

    private function GetConfiguratorValues()
    {
        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');

        $config_list = [];

        $mowers = $this->GetMowerList();
        if ($mowers != '') {
            $guid = '{B64D5F1C-6F12-474B-8DBC-3B263E67954E}';
            $instIDs = IPS_GetInstanceListByModuleID($guid);
            foreach ($mowers as $mower) {
                $this->SendDebug(__FUNCTION__, 'mower=' . print_r($mower, true), 0);
                $device_id = $mower['id'];
                $name = $mower['name'];
                $model = isset($mower['model']) ? $mower['model'] : '';
                /*
                if ($model == '' || is_null($model)) {
                    $model = $this->Translate('unknown');
                }
                switch ($model) {
                    case 'G':
                    case 'H':
                        $with_gps = true;
                        break;
                    default:
                        $with_gps = false;
                        break;
                }
                 */

                $instanceID = 0;
                foreach ($instIDs as $instID) {
                    if (IPS_GetProperty($instID, 'device_id') == $device_id) {
                        $this->SendDebug(__FUNCTION__, 'controller found: ' . utf8_decode(IPS_GetName($instID)) . ' (' . $instID . ')', 0);
                        $instanceID = $instID;
                        break;
                    }
                }

                $create = [
                    'moduleID'      => $guid,
                    'location'      => $this->SetLocation(),
                    'configuration' => [
                        'user'        => $user,
                        'password'    => $password,
                        'device_id'   => "$device_id",
                        /*
                        'model'       => $model,
                        'with_gps'    => $with_gps
                         */
                    ]
                ];
                $create['info'] = 'Automower  ' . $model;

                $entry = [
                    'instanceID'    => $instanceID,
                    'name'          => $name,
                    'model'         => $model,
                    'id'            => $device_id,
                    'create'        => $create
                ];

                $config_list[] = $entry;
                $this->SendDebug(__FUNCTION__, 'entry=' . print_r($entry, true), 0);
            }
        }

        return $config_list;
    }

    private function GetFormElements()
    {
        $formElements = [];

        $formElements[] = ['type' => 'Label', 'caption' => 'Husqvarna Automower Configurator'];

        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'user', 'caption' => 'User'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'password', 'caption' => 'Password'];

        $formElements[] = ['type' => 'Label', 'caption' => ''];

        $formElements[] = ['name' => 'ImportCategoryID', 'type' => 'SelectCategory', 'caption' => 'category'];

        $entries = $this->GetConfiguratorValues();
        $configurator = [
            'type'    => 'Configurator',
            'name'    => 'Mower',
            'caption' => 'Mower',

            'rowCount' => count($entries),

            'add'     => false,
            'delete'  => false,
            'columns' => [
                [
                    'caption' => 'Name',
                    'name'    => 'name',
                    'width'   => 'auto'
                ],
                [
                    'caption' => 'Model',
                    'name'    => 'model',
                    'width'   => '200px'
                ],
                [
                    'caption' => 'ID',
                    'name'    => 'id',
                    'width'   => '400px'
                ]
            ],
            'values' => $entries
        ];
        $formElements[] = $configurator;

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

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
}
