<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Fernbedienung/tree/main/Fernbedienung
 */

/** @noinspection PhpUnused */

declare(strict_types=1);

trait FB_backupRestore
{
    public function CreateBackup(int $BackupCategory): void
    {
        if (IPS_GetInstance($this->InstanceID)['InstanceStatus'] == 102) {
            $name = 'Konfiguration (' . IPS_GetName($this->InstanceID) . ' #' . $this->InstanceID . ') ' . date('d.m.Y H:i:s');
            //$config = json_decode(IPS_GetConfiguration($this->InstanceID), true);
            $config = [];
            $config['MaintenanceMode'] = $this->ReadPropertyBoolean('MaintenanceMode');
            $config['TriggerVariables'] = json_decode($this->ReadPropertyString('TriggerVariables'));
            $config['AlarmProtocol'] = $this->ReadPropertyInteger('AlarmProtocol');
            $json_string = json_encode($config, JSON_HEX_APOS | JSON_PRETTY_PRINT);
            $content = "<?php\n// Backup " . date('d.m.Y, H:i:s') . "\n// ID " . $this->InstanceID . "\n$" . "config = '" . $json_string . "';";
            $backupScript = IPS_CreateScript(0);
            IPS_SetParent($backupScript, $BackupCategory);
            IPS_SetName($backupScript, $name);
            IPS_SetHidden($backupScript, true);
            IPS_SetScriptContent($backupScript, $content);
            echo 'Die Konfiguration wurde erfolgreich gesichert!';
        }
    }

    public function RestoreConfiguration(int $ConfigurationScript): void
    {
        if ($ConfigurationScript != 0 && IPS_ObjectExists($ConfigurationScript)) {
            $object = IPS_GetObject($ConfigurationScript);
            if ($object['ObjectType'] == 3) {
                $content = IPS_GetScriptContent($ConfigurationScript);
                preg_match_all('/\'([^;]+)\'/', $content, $matches);
                $config = json_decode($matches[1][0], true);
                IPS_SetProperty($this->InstanceID, 'MaintenanceMode', $config['MaintenanceMode']);
                IPS_SetProperty($this->InstanceID, 'TriggerVariables', json_encode($config['TriggerVariables']));
                IPS_SetProperty($this->InstanceID, 'AlarmProtocol', $config['AlarmProtocol']);
                if (IPS_HasChanges($this->InstanceID)) {
                    IPS_ApplyChanges($this->InstanceID);
                }
            }
            echo 'Die Konfiguration wurde erfolgreich wiederhergestellt!';
        }
    }

    public function ImportConfiguration(int $ConfigurationScript): void
    {
        if ($ConfigurationScript != 0 && IPS_ObjectExists($ConfigurationScript)) {
            $object = IPS_GetObject($ConfigurationScript);
            if ($object['ObjectType'] == 3) {
                $content = IPS_GetScriptContent($ConfigurationScript);
                preg_match_all('/\'([^;]+)\'/', $content, $matches);
                $config = json_decode($matches[1][0], true);
                $listedTriggerVariables = json_decode($this->ReadPropertyString('TriggerVariables'), true);
                if (array_key_exists('RemoteControls', $config)) {
                    $remoteControls = json_decode($config['RemoteControls'], true);
                    foreach ($remoteControls as $remoteControl) {
                        $use = true;
                        if (array_key_exists('Use', $remoteControl)) {
                            $use = $remoteControl['Use'];
                        }
                        $id = 0;
                        if (array_key_exists('ID', $remoteControl)) {
                            $id = $remoteControl['ID'];
                        }
                        $name = 'Unbekannt';
                        if (array_key_exists('Name', $remoteControl)) {
                            $name = $remoteControl['Name'];
                        }
                        $triggerType = 0;
                        if (array_key_exists('Trigger', $remoteControl)) {
                            $triggerType = $remoteControl['Trigger'];
                        }
                        $triggerValue = 'true';
                        if (array_key_exists('Value', $remoteControl)) {
                            $triggerValue = $remoteControl['Value'];
                        }
                        if ($id != 0 && @IPS_ObjectExists($id)) {
                            array_push($listedTriggerVariables, [
                                'Use'              => $use,
                                'ID'               => $id,
                                'Name'             => $name,
                                'TriggerType'      => $triggerType,
                                'TriggerValue'     => $triggerValue,
                                'TriggerAction'    => 0,
                                'TargetVariableID' => 0,
                                'TargetScriptID'   => 0]);
                        }
                    }
                    $value = json_encode($listedTriggerVariables);
                    @IPS_SetProperty($this->InstanceID, 'TriggerVariables', $value);
                    if (@IPS_HasChanges($this->InstanceID)) {
                        @IPS_ApplyChanges($this->InstanceID);
                    }
                    echo 'Die Konfiguration wurde erfolgreich importiert!';
                }
            }
        }
    }
}