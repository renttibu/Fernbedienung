<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Fernbedienung/tree/main/Fernbedienung
 */

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class Fernbedienung extends IPSModule
{
    // Helper
    use FB_alarmProtocol;
    use FB_backupRestore;
    use FB_triggerVariable;
    // Constants
    private const MODULE_NAME = 'Fernbedienung';
    private const MODULE_PREFIX = 'UBFB';
    private const ALARMPROTOCOL_MODULE_GUID = '{33EF9DF1-C8D7-01E7-F168-0A1927F1C61F}';

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Properties

        // Functions
        $this->RegisterPropertyBoolean('MaintenanceMode', false);

        // Trigger variables
        $this->RegisterPropertyString('TriggerVariables', '[]');

        // Alarm protocol
        $this->RegisterPropertyInteger('AlarmProtocol', 0);
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();

        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Delete all references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        // Delete all registrations
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }

        if (!$this->CheckMaintenanceMode()) {
            $this->SendDebug(__FUNCTION__, 'Referenzen und Nachrichten werden registriert.', 0);
            // Register references and update messages
            foreach (json_decode($this->ReadPropertyString('TriggerVariables')) as $variable) {
                if ($variable->Use) {
                    $id = $variable->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $this->RegisterReference($id);
                        $this->RegisterMessage($id, VM_UPDATE);
                    }
                }
            }
        }
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:

                //$Data[0] = actual value
                //$Data[1] = value changed
                //$Data[2] = last value
                //$Data[3] = timestamp actual value
                //$Data[4] = timestamp value changed
                //$Data[5] = timestamp last value

                if ($this->CheckMaintenanceMode()) {
                    return;
                }

                // Check trigger variable
                $valueChanged = 'false';
                if ($Data[1]) {
                    $valueChanged = 'true';
                }
                $scriptText = self::MODULE_PREFIX . '_CheckTriggerVariable(' . $this->InstanceID . ', ' . $SenderID . ', ' . $valueChanged . ');';
                IPS_RunScriptText($scriptText);
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $form = [];

        #################### Elements

        ########## Functions

        ##### Functions panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Wartungsmodus',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'MaintenanceMode',
                    'caption' => 'Wartungsmodus'
                ]
            ]
        ];

        ########## Trigger variables

        $triggerVariables = [];
        foreach (json_decode($this->ReadPropertyString('TriggerVariables')) as $variable) {
            $rowColor = '#FFC0C0'; # red
            $id = $variable->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#DFDFDF'; # grey
                if ($variable->Use) {
                    $rowColor = '#C0FFC0'; # light green
                    if ($variable->TriggerAction != 3) {
                        $id = $variable->TargetVariableID;
                    } else {
                        $id = $variable->TargetScriptID;
                    }
                    if ($id == 0 || @!IPS_ObjectExists($id)) {
                        $rowColor = '#FFC0C0'; # red
                    }
                }
            }
            $triggerVariables[] = ['rowColor' => $rowColor];
        }

        ##### Trigger variables panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Fernbedienungen',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'TriggerVariables',
                    'caption'  => '',
                    'rowCount' => 15,
                    'add'      => true,
                    'delete'   => true,
                    'sort'     => [
                        'column'    => 'ID',
                        'direction' => 'ascending'
                    ],
                    'columns' => [
                        [
                            'name'    => 'Use',
                            'caption' => 'Aktiviert',
                            'width'   => '100px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'UseAlarmProtocol',
                            'caption' => 'Alarmprotokoll',
                            'width'   => '150px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Auslösende Variable',
                            'width'   => '350px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $TriggerVariables["ID"], "TriggerVariableConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'name'    => 'Info',
                            'caption' => 'Info',
                            'width'   => '160px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type'    => 'Button',
                                'onClick' => self::MODULE_PREFIX . '_ShowVariableDetails($id, $ID);'
                            ]
                        ],
                        [
                            'name'    => 'Name',
                            'caption' => 'Bezeichnung',
                            'width'   => '350px',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'name'    => 'TriggerType',
                            'caption' => 'Auslöseart',
                            'width'   => '280px',
                            'add'     => 7,
                            'edit'    => [
                                'type'    => 'Select',
                                'options' => [
                                    [
                                        'caption' => 'Bei Änderung',
                                        'value'   => 0
                                    ],
                                    [
                                        'caption' => 'Bei Aktualisierung',
                                        'value'   => 1
                                    ],
                                    [
                                        'caption' => 'Bei Grenzunterschreitung (einmalig)',
                                        'value'   => 2
                                    ],
                                    [
                                        'caption' => 'Bei Grenzunterschreitung (mehrmalig)',
                                        'value'   => 3
                                    ],
                                    [
                                        'caption' => 'Bei Grenzüberschreitung (einmalig)',
                                        'value'   => 4
                                    ],
                                    [
                                        'caption' => 'Bei Grenzüberschreitung (mehrmalig)',
                                        'value'   => 5
                                    ],
                                    [
                                        'caption' => 'Bei bestimmtem Wert (einmalig)',
                                        'value'   => 6
                                    ],
                                    [
                                        'caption' => 'Bei bestimmtem Wert (mehrmalig)',
                                        'value'   => 7
                                    ]
                                ]
                            ]
                        ],
                        [
                            'name'    => 'TriggerValue',
                            'caption' => 'Auslösewert',
                            'width'   => '160px',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'name'    => 'TriggerAction',
                            'caption' => 'Auslöseaktion',
                            'width'   => '200px',
                            'add'     => 0,
                            'edit'    => [
                                'type'    => 'Select',
                                'options' => [
                                    [
                                        'caption' => 'Variable ausschalten',
                                        'value'   => 0
                                    ],
                                    [
                                        'caption' => 'Variable einschalten',
                                        'value'   => 1
                                    ],
                                    [
                                        'caption' => 'Variable umschalten',
                                        'value'   => 2
                                    ],
                                    [
                                        'caption' => 'Skript ausführen',
                                        'value'   => 3
                                    ]
                                ]
                            ]
                        ],
                        [
                            'name'    => 'TargetVariableID',
                            'caption' => 'Variable',
                            'width'   => '350px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $TriggerVariables["TargetVariableID"], "TriggerVariableConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'name'    => 'TargetScriptID',
                            'caption' => 'Skript',
                            'width'   => '350px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $TriggerVariables["TargetScriptID"], "TriggerVariableConfigurationButton", 0);',

                            'edit'    => [
                                'type' => 'SelectScript'
                            ]
                        ]
                    ],
                    'values' => $triggerVariables,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'name'     => 'TriggerVariableConfigurationButton',
                    'caption'  => 'Bearbeiten',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## Alarm protocol

        $id = $this->ReadPropertyInteger('AlarmProtocol');
        $enabled = false;
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $enabled = true;
        }

        ##### Alarm protocol panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Alarmprotokoll',
            'items'   => [
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'     => 'SelectModule',
                            'name'     => 'AlarmProtocol',
                            'caption'  => 'Alarmprotokoll',
                            'moduleID' => self::ALARMPROTOCOL_MODULE_GUID,
                            'width'    => '600px',
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' ',
                            'visible' => $enabled
                        ],
                        [
                            'type'     => 'OpenObjectButton',
                            'caption'  => 'ID ' . $id . ' konfigurieren',
                            'visible'  => $enabled,
                            'objectID' => $id
                        ]
                    ]
                ]
            ]
        ];

        #################### Actions

        ##### Configuration panel

        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Konfiguration',
            'items'   => [
                [
                    'type'    => 'Button',
                    'caption' => 'Neu einlesen',
                    'onClick' => self::MODULE_PREFIX . '_ReloadConfiguration($id);'
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'SelectCategory',
                            'name'    => 'BackupCategory',
                            'caption' => 'Kategorie',
                            'width'   => '600px'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' '
                        ],
                        [
                            'type'    => 'Button',
                            'caption' => 'Sichern',
                            'onClick' => self::MODULE_PREFIX . '_CreateBackup($id, $BackupCategory);'
                        ]
                    ]
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'SelectScript',
                            'name'    => 'ConfigurationScript',
                            'caption' => 'Konfiguration',
                            'width'   => '600px'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' '
                        ],
                        [
                            'type'    => 'PopupButton',
                            'caption' => 'Wiederherstellen',
                            'popup'   => [
                                'caption' => 'Konfiguration wirklich wiederherstellen?',
                                'items'   => [
                                    [
                                        'type'    => 'Button',
                                        'caption' => 'Wiederherstellen',
                                        'onClick' => self::MODULE_PREFIX . '_RestoreConfiguration($id, $ConfigurationScript);'
                                    ]
                                ]
                            ]
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' '
                        ],
                        [
                            'type'    => 'PopupButton',
                            'caption' => 'Importieren',
                            'popup'   => [
                                'caption' => 'Konfiguration wirklich importieren?',
                                'items'   => [
                                    [
                                        'type'    => 'Button',
                                        'caption' => 'Importieren',
                                        'onClick' => self::MODULE_PREFIX . '_ImportConfiguration($id, $ConfigurationScript);'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        #################### Status

        $form['status'] = [
            [
                'code'    => 101,
                'icon'    => 'active',
                'caption' => self::MODULE_NAME . ' wird erstellt',
            ],
            [
                'code'    => 102,
                'icon'    => 'active',
                'caption' => self::MODULE_NAME . ' ist aktiv (ID ' . $this->InstanceID . ')',
            ],
            [
                'code'    => 103,
                'icon'    => 'active',
                'caption' => self::MODULE_NAME . ' wird gelöscht (ID ' . $this->InstanceID . ')',
            ],
            [
                'code'    => 104,
                'icon'    => 'inactive',
                'caption' => self::MODULE_NAME . ' ist inaktiv (ID ' . $this->InstanceID . ')',
            ],
            [
                'code'    => 200,
                'icon'    => 'inactive',
                'caption' => self::MODULE_NAME . ', es ist Fehler aufgetreten, weitere Informationen unter Meldungen, im Log oder Debug! (ID ' . $this->InstanceID . ')',
            ]
        ];
        return json_encode($form);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    public function ShowVariableDetails(int $VariableID): void
    {
        if ($VariableID == 0 || !@IPS_ObjectExists($VariableID)) {
            return;
        }
        if ($VariableID != 0) {
            // Variable
            echo 'ID: ' . $VariableID . "\n";
            echo 'Name: ' . IPS_GetName($VariableID) . "\n";
            $variable = IPS_GetVariable($VariableID);
            if (!empty($variable)) {
                $variableType = $variable['VariableType'];
                switch ($variableType) {
                    case 0:
                        $variableTypeName = 'Boolean';
                        break;

                    case 1:
                        $variableTypeName = 'Integer';
                        break;

                    case 2:
                        $variableTypeName = 'Float';
                        break;

                    case 3:
                        $variableTypeName = 'String';
                        break;

                    default:
                        $variableTypeName = 'Unbekannt';
                }
                echo 'Variablentyp: ' . $variableTypeName . "\n";
            }
            // Profile
            $profile = @IPS_GetVariableProfile($variable['VariableProfile']);
            if (empty($profile)) {
                $profile = @IPS_GetVariableProfile($variable['VariableCustomProfile']);
            }
            if (!empty($profile)) {
                $profileType = $variable['VariableType'];
                switch ($profileType) {
                    case 0:
                        $profileTypeName = 'Boolean';
                        break;

                    case 1:
                        $profileTypeName = 'Integer';
                        break;

                    case 2:
                        $profileTypeName = 'Float';
                        break;

                    case 3:
                        $profileTypeName = 'String';
                        break;

                    default:
                        $profileTypeName = 'Unbekannt';
                }
                echo 'Profilname: ' . $profile['ProfileName'] . "\n";
                echo 'Profiltyp: ' . $profileTypeName . "\n\n";
            }
            if (!empty($variable)) {
                echo "\nVariable:\n";
                print_r($variable);
            }
            if (!empty($profile)) {
                echo "\nVariablenprofil:\n";
                print_r($profile);
            }
        }
    }

    public function EnableConfigurationButton(int $ObjectID, string $ButtonName, int $Type): void
    {
        // Variable
        $description = 'ID ' . $ObjectID . ' bearbeiten';
        // Instance
        if ($Type == 1) {
            $description = 'ID ' . $ObjectID . ' konfigurieren';
        }
        $this->UpdateFormField($ButtonName, 'caption', $description);
        $this->UpdateFormField($ButtonName, 'visible', true);
        $this->UpdateFormField($ButtonName, 'enabled', true);
        $this->UpdateFormField($ButtonName, 'objectID', $ObjectID);
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = $this->ReadPropertyBoolean('MaintenanceMode');
        if ($result) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        return $result;
    }
}