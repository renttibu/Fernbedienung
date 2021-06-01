<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Fernbedienung/tree/main/Fernbedienung
 */

/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection PhpUndefinedFunctionInspection */

declare(strict_types=1);

trait FB_alarmProtocol
{
    private function UpdateAlarmProtocol(string $Message): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $id = $this->ReadPropertyInteger('AlarmProtocol');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $timestamp = date('d.m.Y, H:i:s');
            $logText = $timestamp . ', ' . $Message;
            $this->SendDebug(__FUNCTION__, $logText, 0);
            self::ALARMPROTOCOL_PREFIX . _UpdateMessages($id, $logText, 0);
            $this->SendDebug(__FUNCTION__, 'Das Alarmprotokoll wurde aktualisiert.', 0);
        }
    }
}