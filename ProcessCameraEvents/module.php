<?php

class ProcessCameraEvents extends IPSModule {
    
    public function Create() {
        parent::Create();
        
        // Register properties
        $this->RegisterPropertyString('WebhookName', 'HIKVISION_EVENTS');
        $this->RegisterPropertyString('ChannelId', '101');
        $this->RegisterPropertyString('SavePath', 'webfront/user/');
        $this->RegisterPropertyString('EggTimerModuleId', '{17843F0A-BFC8-A4BA-E219-A2D10FC8E5BE}');
        
        // Ensure the webhook is registered
        $this->RegisterHook($this->ReadPropertyString('WebhookName'));
    }

    public function ApplyChanges() {
        parent::ApplyChanges();
        
        // Ensure the webhook is registered
        //$this->RegisterHook($this->ReadPropertyString('WebhookName'));
    }

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

    public function ProcessEvent() {
        $eggTimerModuleId = $this->ReadPropertyString('EggTimerModuleId');
        if (!IPS_GetModule($eggTimerModuleId)) {
            echo "Bitte erst das Egg Timer Modul aus dem Modul Store installieren";
            return;
        }

        $webhookData = file_get_contents("php://input", true);
        if ($webhookData !== "") {
            $motionData = $this->parseEventNotificationAlert($webhookData);
            if (is_array($motionData)) {
                $this->handleMotionData($motionData);
            }
        } elseif (is_array($_POST)) {
            foreach ($_POST as $value) {
                $motionData = $this->parseEventNotificationAlert($value);
                $this->handleMotionData($motionData);
            }
        } else {
            IPS_LogMessage("HIK","No Data");
        }
        
    }

    private function handleMotionData($motionData) {
        $parent = $this->InstanceID;
        $notSetYet = "NotSet";
        $channelId = $this->ReadPropertyString('ChannelId');
        $savePath = $this->ReadPropertyString('SavePath');

        $kameraId = $this->manageVariable($parent, $motionData['channelName'], 0, 'Motion', true, 0, "");
        SetValueBoolean($kameraId, true);

        $kameraName = $this->manageVariable($kameraId, $motionData['ipAddress'], 3, '~TextBox', true, 0, "");
        SetValueString($kameraName, $motionData['eventDescription']);

        $username = GetValueString($this->manageVariable($kameraId, "User Name", 3, '~TextBox', true, 0, $notSetYet));
        $password = GetValueString($this->manageVariable($kameraId, "Password", 3, '~TextBox', true, 0, $notSetYet));
        $dateTime = $this->manageVariable($kameraId, "Date and Time", 3, '~TextBox', true, 0, "");
        SetValueString($dateTime, $motionData['dateTime']);

        $eggTimerId = @IPS_GetObjectIDByName("Egg Timer", $parent);
        if ($eggTimerId) {
            RequestAction(IPS_GetObjectIDByName("Aktiv", $eggTimerId), true);
        } else {
            $insId = IPS_CreateInstance($this->ReadPropertyString('EggTimerModuleId'));
            IPS_SetName($insId, "Egg Timer");
            IPS_SetParent($insId, $kameraId);
            IPS_ApplyChanges($insId);
            RequestAction(IPS_GetObjectIDByName("Aktiv", $insId), true);
            SetValueInteger(IPS_GetObjectIDByName("Zeit in Sekunden", $insId), 300);

            $eid = IPS_CreateEvent(0);
            IPS_SetEventTrigger($eid, 4, IPS_GetObjectIDByName("Aktiv", $insId));
            IPS_SetParent($eid, $kameraId);
            IPS_SetEventAction($eid, "{75C67945-BE11-5965-C569-602D43F84269}", ["VALUE" => false]);
            IPS_SetEventActive($eid, true);
            IPS_SetEventTriggerValue($eid, false);
        }

        if ($username != $notSetYet && $password != $notSetYet) {
            $savePath .= $motionData['ipAddress'] . ".jpg";
            $this->downloadHikvisionSnapshot($motionData['ipAddress'], $channelId, $username, $password, $savePath);
            $this->manageMedia($kameraId, "Last_Picture", $savePath);
        } else {
            echo "Please set UserName and Password in Variable";
        }
    }

    private function parseEventNotificationAlert($xmlString) {
        $xml = @simplexml_load_string($xmlString, "SimpleXMLElement", LIBXML_NOCDATA);
        if ($xml === false) {
            return false;
        }

        $json = json_encode($xml);
        $array = json_decode($json, true);
        return $array;
    }

    private function manageVariable($name, $type, $profile, $position, $initialValue, $archive = true, $aggregationType = 0) {
        // Check if variable already exists
        $varId = @$this->GetIDForIdent($name);
    
        if ($varId === false) {
            // Register the variable if it does not exist
            $this->RegisterVariable($name, $name, $type, $profile, $position);
            
            // Set initial value
            switch ($type) {
                case VARIABLETYPE_BOOLEAN:
                    SetValueBoolean($varId, (bool)$initialValue);
                    break;
                case VARIABLETYPE_INTEGER:
                    SetValueInteger($varId, (int)$initialValue);
                    break;
                case VARIABLETYPE_FLOAT:
                    SetValueFloat($varId, (float)$initialValue);
                    break;
                case VARIABLETYPE_STRING:
                    SetValueString($varId, (string)$initialValue);
                    break;
            }
    
            // Set logging if required
            if ($archive && IPS_ModuleExists("{43192F0B-135B-4CE7-A0A7-1475603F3060}")) {
                $archiveId = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
                AC_SetLoggingStatus($archiveId, $varId, true);
                AC_SetAggregationType($archiveId, $varId, $aggregationType);
                IPS_ApplyChanges($archiveId);
            }
        }
    
        return $varId;
    }
    
    private function RegisterVariable($ident, $name, $type, $profile, $position) {
        // MaintainVariable helps to register or update a variable
        $this->MaintainVariable($ident, $name, $type, $profile, $position, true);
    }
    
    // Example usage of the manageVariable function
    public function Create() {
        parent::Create();
    
        // Register variables with initial values and logging settings
        $this->manageVariable('Motion', VARIABLETYPE_BOOLEAN, '~Switch', 0, false);
        $this->manageVariable('CameraName', VARIABLETYPE_STRING, '', 1, '');
        $this->manageVariable('DateTime', VARIABLETYPE_STRING, '', 2, '');
    }

    private function manageMedia($parent, $name, $imageFile) {
        $mediaId = @IPS_GetMediaIDByName($name, $parent);
        if ($mediaId === false) {
            $mediaId = IPS_CreateMedia(1);
            IPS_SetMediaFile($mediaId, $imageFile, true);
            IPS_SetName($mediaId, $name);
            IPS_SetParent($mediaId, $parent);
        }
        return $mediaId;
    }

    private function downloadHikvisionSnapshot($cameraIp, $channelId, $username, $password, $relativePath) {
        $snapshotUrl = "http://$cameraIp/ISAPI/Streaming/channels/$channelId/picture";
        $ch = curl_init($snapshotUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200 && $imageData !== false) {
            $savePath = IPS_GetKernelDir() . DIRECTORY_SEPARATOR . $relativePath;
            $fileHandle = fopen($savePath, 'w');
            if ($fileHandle !== false) {
                fwrite($fileHandle, $imageData);
                fclose($fileHandle);
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}

?>
