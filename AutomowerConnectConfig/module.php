<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/CommonStubs/common.php'; // globale Funktionen
require_once __DIR__ . '/../libs/local.php';   // lokale Funktionen

class AutomowerConnectConfig extends IPSModule
{
    use StubsCommonLib;
    use AutomowerConnectLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('ImportCategoryID', 0);

        $this->ConnectParent('{AEEFAA3E-8802-086D-6620-E971C03CBEFC}');
    }

    private function CheckConfiguration()
    {
        $s = '';
        $r = [];

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

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        $propertyNames = ['ImportCategoryID'];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid >= 10000) {
                $this->RegisterReference($oid);
            }
        }

        if ($this->CheckConfiguration() != false) {
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $this->SetStatus(IS_ACTIVE);
    }

    private function SetLocation()
    {
        $catID = $this->ReadPropertyInteger('ImportCategoryID');
        $tree_position = [];
        if ($catID >= 10000 && IPS_ObjectExists($catID)) {
            $tree_position[] = IPS_GetName($catID);
            $parID = IPS_GetObject($catID)['ParentID'];
            while ($parID > 0) {
                if ($parID > 0) {
                    $tree_position[] = IPS_GetName($parID);
                }
                $parID = IPS_GetObject($parID)['ParentID'];
            }
            $tree_position = array_reverse($tree_position);
        }
        $this->SendDebug(__FUNCTION__, 'tree_position=' . print_r($tree_position, true), 0);
        return $tree_position;
    }

    private function getConfiguratorValues()
    {
        $config_list = [];

        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return $config_list;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
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
                $id = $this->GetArrayElem($mower, 'id', '');
                $name = $this->GetArrayElem($mower, 'attributes.system.name', '');
                $model = $this->GetArrayElem($mower, 'attributes.system.model', '');
                if (preg_match('/^HUSQVARNA AUTOMOWER® (.*)$/', $model, $r)) {
                    $model = $r[1];
                }
                $serial = $this->GetArrayElem($mower, 'attributes.system.serialNumber', '');

                $instanceID = 0;
                foreach ($instIDs as $instID) {
                    if (IPS_GetProperty($instID, 'serial') == $serial) {
                        $this->SendDebug(__FUNCTION__, 'device found: ' . utf8_decode(IPS_GetName($instID)) . ' (' . $instID . ')', 0);
                        $instanceID = $instID;
                        break;
                    }
                }
                // Kompatibilität mit alter API
                if ($instanceID == 0) {
                    foreach ($instIDs as $instID) {
                        $device_id = IPS_GetProperty($instID, 'device_id');
                        if (preg_match('/^([^-]*)-.*$/', $device_id, $r)) {
                            $device_id = $r[1];
                        }
                        if ($device_id == $serial) {
                            $this->SendDebug(__FUNCTION__, 'device found: ' . utf8_decode(IPS_GetName($instID)) . ' (' . $instID . ')', 0);
                            $instanceID = $instID;
                            break;
                        }
                    }
                }

                $create = [
                    'moduleID'      => $guid,
                    'location'      => $this->SetLocation(),
                    'configuration' => [
                        'model'       => $model,
                        'serial'      => (string) $serial,
                        'id'          => (string) $id
                    ]
                ];
                $create['info'] = 'Automower  ' . $model;

                $entry = [
                    'instanceID'    => $instanceID,
                    'name'          => $name,
                    'model'         => $model,
                    'serial'        => $serial,
                    'id'            => $id,
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
        $formElements[] = [
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
                [
                    'caption' => 'Device-ID',
                    'name'    => 'id',
                    'width'   => '350px'
                ],
            ],
            'values' => $entries
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        $formActions[] = $this->GetInformationForm();
        $formActions[] = $this->GetReferencesForm();

        return $formActions;
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->CommonRequestAction($Ident, $Value)) {
            return;
        }
        switch ($Ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $Ident, 0);
                break;
        }
    }
}
