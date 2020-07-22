<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/library.php';  // modul-bezogene Funktionen

class AutomowerConnectConfig extends IPSModule
{
    use AutomowerConnectCommon;
    use AutomowerConnectLibrary;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('ImportCategoryID', 0);

        $this->ConnectParent('{AEEFAA3E-8802-086D-6620-E971C03CBEFC}');
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

    private function getConfiguratorValues()
    {
        $config_list = [];

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return $config_list;
        }

        // an AutomowerConnectIO
        $sdata = [
            'DataID'   => '{4C746488-C0FD-A850-3532-8DEBC042C970}',
            'Function' => 'MowerList'
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $data = $this->SendDataToParent(json_encode($sdata));
        $mowers = $data != '' ? json_decode($data, true) : '';
        $this->SendDebug(__FUNCTION__, 'mowers=' . print_r($mowers, true), 0);

        if ($mowers != '') {
            $guid = '{B64D5F1C-6F12-474B-8DBC-3B263E67954E}';
            $instIDs = IPS_GetInstanceListByModuleID($guid);
            foreach ($mowers['data'] as $mower) {
                $this->SendDebug(__FUNCTION__, 'mower=' . print_r($mower, true), 0);
                $name = $this->GetArrayElem($mower, 'attributes.system.name', '');
                $model = $this->GetArrayElem($mower, 'attributes.system.model', '');
                $serial = $this->GetArrayElem($mower, 'attributes.system.serialNumber', '');

                $instanceID = 0;
                foreach ($instIDs as $instID) {
                    if (IPS_GetProperty($instID, 'serial') == $serial) {
                        $this->SendDebug(__FUNCTION__, 'device found: ' . utf8_decode(IPS_GetName($instID)) . ' (' . $instID . ')', 0);
                        $instanceID = $instID;
                        break;
                    }
                }

                $create = [
                    'moduleID'      => $guid,
                    'location'      => $this->SetLocation(),
                    'configuration' => [
                        'model'       => $model,
                        'serial'      => (string) $serial
                    ]
                ];
                $create['info'] = 'Automower  ' . $model;

                $entry = [
                    'instanceID'    => $instanceID,
                    'name'          => $name,
                    'model'         => $model,
                    'serial'        => $serial,
                    'create'        => $create
                ];

                $config_list[] = $entry;
                $this->SendDebug(__FUNCTION__, 'entry=' . print_r($entry, true), 0);
            }
        }

        return $config_list;
    }

    public function GetFormElements()
    {
        $formElements = [];

        if ($this->HasActiveParent() == false) {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => 'Instance has no active parent instance',
            ];
        }

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Husqvarna Automower Configurator'
        ];

        $formElements[] = [
            'type'    => 'SelectCategory',
            'name'    => 'ImportCategoryID',
            'caption' => 'category'
        ];

        $entries = $this->getConfiguratorValues();
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
                    'caption' => 'Serial',
                    'name'    => 'serial',
                    'width'   => '200px'
                ],
            ],
            'values' => $entries
        ];
        $formElements[] = $configurator;

        return $formElements;
    }

    protected function GetFormActions()
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
