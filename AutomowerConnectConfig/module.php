<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class AutomowerConnectConfig extends IPSModule
{
    use AutomowerConnect\StubsCommonLib;
    use AutomowerConnectLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('ImportCategoryID', 0);

        $this->RegisterAttributeString('UpdateInfo', '');

        $this->ConnectParent('{AEEFAA3E-8802-086D-6620-E971C03CBEFC}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $propertyNames = ['ImportCategoryID'];
        $this->MaintainReferences($propertyNames);

        if ($this->CheckPrerequisites() != false) {
            $this->SetStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->SetStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
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
        $entries = [];

        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return $entries;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            return $entries;
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

        $guid = '{B64D5F1C-6F12-474B-8DBC-3B263E67954E}';
        $instIDs = IPS_GetInstanceListByModuleID($guid);

        if (is_array($mowers)) {
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

                $entry = [
                    'instanceID'    => $instanceID,
                    'name'          => $name,
                    'model'         => $model,
                    'serial'        => $serial,
                    'id'            => $id,
                    'create'        => [
                        'moduleID'      => $guid,
                        'location'      => $this->SetLocation(),
                        'info'          => 'Automower  ' . $model,
                        'configuration' => [
                            'model'       => $model,
                            'serial'      => (string) $serial,
                            'id'          => (string) $id,
                        ],
                    ],
                ];

                $entries[] = $entry;
                $this->SendDebug(__FUNCTION__, 'entry=' . print_r($entry, true), 0);
            }
        }
        foreach ($instIDs as $instID) {
            $fnd = false;
            foreach ($entries as $entry) {
                if ($entry['instanceID'] == $instID) {
                    $fnd = true;
                    break;
                }
            }
            if ($fnd) {
                continue;
            }

            $name = IPS_GetName($instID);
            $model = IPS_GetProperty($instID, 'model');
            $serial = IPS_GetProperty($instID, 'serial');
            $id = IPS_GetProperty($instID, 'id');

            $entry = [
                'instanceID' => $instID,
                'name'       => $name,
                'model'      => $model,
                'serial'     => $serial,
                'id'         => $id,
            ];

            $entries[] = $entry;
            $this->SendDebug(__FUNCTION__, 'missing entry=' . print_r($entry, true), 0);
        }

        return $entries;
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Husqvarna AutomowerConnect Configurator');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'SelectCategory',
            'name'    => 'ImportCategoryID',
            'caption' => 'category for mower to be created'
        ];

        $entries = $this->getConfiguratorValues();
        $formElements[] = [
            'type'     => 'Configurator',
            'name'     => 'Mower',
            'caption'  => 'Mower',
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

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }
        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }
}
