<?php

class ProcessCameraEvents extends IPSModule {
    
    public function Create() {
        parent::Create();
        
        // Register properties
        $this->RegisterPropertyString('WebhookName', 'HIKVISION_EVENTS');
        $this->RegisterPropertyString('ChannelId', '101');
        $this->RegisterPropertyString('SavePath', '/user/');
        $this->RegisterPropertyString('UserName', 'NotSet');
        $this->RegisterPropertyString('Password', 'NotSet');
        $this->RegisterPropertyInteger('MotionActive', '30');
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
        $find_Hook = '/hook/'.$WebHook;
        if (count($ids) > 0) {
            $hooks = json_decode(IPS_GetProperty($ids[0], 'Hooks'), true);
            $found = false;
            foreach ($hooks as $index => $hook) {
                if ($hook['Hook'] == $find_Hook) {
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

    public function  ProcessHookData() {
        
        if (IPS_SemaphoreEnter($kameraId."process",1000)) 
        {
            
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
                IPS_LogMessage("HIKMOD","No Data");
            }
            IPS_SemaphoreLeave($kameraId)."process";
        }
        else
        {

        }
    }

    private function handleMotionData($motionData) {

        $notSetYet = 'NotSet';
        $parent = $this->InstanceID;
        $channelId = $this->ReadPropertyString('ChannelId');
        $savePath = $this->ReadPropertyString('SavePath');
        $username = $this->ReadPropertyString('UserName');
        $password= $this->ReadPropertyString('Password');
        $motion_active = $this->ReadPropertyInteger('MotionActive');
        $kameraId = $this->manageVariable($parent, $motionData['channelName'], 0, 'Motion', true, 0, "");
        SetValueBoolean($kameraId, true);
        $delay_still_active  = false;
        $eggTimerId = @IPS_GetObjectIDByName("Egg Timer", $kameraId);
        if ($eggTimerId) {
            $activ_id = @IPS_GetObjectIDByName("Aktiv",  $eggTimerId );
            $delay_still_active = GetValueBoolean($activ_id );
        }
        if(!$delay_still_active)
        { 
            $kameraName_var_id = $this->manageVariable($kameraId, $motionData['ipAddress'], 3, '~TextBox', true, 0, "");

            SetValueString($kameraName_var_id, $motionData['eventDescription']);

            $username = GetValueString($this->manageVariable($kameraId, "User Name", 3, '~TextBox', true, 0, $username));
            $password = GetValueString($this->manageVariable($kameraId, "Password", 3, '~TextBox', true, 0, $password ));
            $dateTime = $this->manageVariable($kameraId, "Date and Time", 3, '~TextBox', true, 0, "");
            SetValueString($dateTime, $motionData['dateTime']);
            if ($username != $notSetYet && $password != $notSetYet) {
                $savePath .= $motionData['ipAddress'] . ".jpg";
                $this->downloadHikvisionSnapshot($motionData['ipAddress'], $channelId, $username, $password, $savePath);
                sleep(2);
                $this->manageMedia($kameraId, "Last_Picture", $savePath);
            } else {
                echo "Please set UserName and Password in Variable";
            }
            if (IPS_SemaphoreEnter($kameraId,1000)) 
            {
                $eggTimerId = @IPS_GetObjectIDByName("Egg Timer", $kameraId);
                if ($eggTimerId) {
                    SetValueInteger(IPS_GetObjectIDByName("Zeit in Sekunden", $eggTimerId), $motion_active);
                    RequestAction(IPS_GetObjectIDByName("Aktiv", $eggTimerId), true);
                } else {
                    $insId = IPS_CreateInstance($this->ReadPropertyString('EggTimerModuleId'));
                    IPS_SetName($insId, "Egg Timer");
                    IPS_SetParent($insId, $kameraId);
                    IPS_ApplyChanges($insId);
                    RequestAction(IPS_GetObjectIDByName("Aktiv", $insId), true);
                    SetValueInteger(IPS_GetObjectIDByName("Zeit in Sekunden", $insId), $motion_active);
                    $eid = IPS_CreateEvent(0);
                    IPS_SetEventTrigger($eid, 4, IPS_GetObjectIDByName("Aktiv", $insId));
                    IPS_SetParent($eid, $kameraId);
                    IPS_SetEventAction($eid, "{75C67945-BE11-5965-C569-602D43F84269}", ["VALUE" => false]);
                    IPS_SetEventActive($eid, true);
                    IPS_SetEventTriggerValue($eid, false);
                }
                IPS_SemaphoreLeave($kameraId);
            }
            else
            {
                return false;
            }
        }
        else
        {
            return false;
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

    private function manageVariable($parent, $name, $type, $profile, $logging, $aggregationType, $initialValue) {
        $archiveId = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
        $varId = @IPS_GetVariableIDByName($name, $parent);

        if ($varId === false) {
            $varId = IPS_CreateVariable($type);
            if ($profile != "") IPS_SetVariableCustomProfile($varId, $profile);
            IPS_SetName($varId, $name);
            IPS_SetParent($varId, $parent);
            
            AC_SetLoggingStatus($archiveId, $varId, $logging);
            if ($logging || $type != 3) {
                AC_SetAggregationType($archiveId, $varId, $aggregationType);
            }
            IPS_ApplyChanges($archiveId);
            if ($initialValue != "") {
                SetValueString($varId, $initialValue);
            }
        }

        return $varId;
    }
    
    
    // Example usage of the manageVariable function
   

    private function manageMedia($parent, $name, $imageFile) {
        $mediaId = @IPS_GetMediaIDByName($name, $parent);
        if ($mediaId === false) {
            $mediaId = IPS_CreateMedia(1);
            IPS_SetName($mediaId, $name);
            IPS_SetParent($mediaId, $parent);
        }
        IPS_SetMediaFile($mediaId, $imageFile, true);
   
        
        return $mediaId;
    }

    private function downloadHikvisionSnapshot($cameraIp, $channelId, $username, $password, $relativePath) {
        $snapshotUrl = "http://$cameraIp/ISAPI/Streaming/channels/$channelId/picture";
        $retryCount = 3;
        
        for ($i = 0; $i < $retryCount; $i++) {
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
            }
        }
    
        // If all retries fail, return false
        return false;
    }
    
}

?>
