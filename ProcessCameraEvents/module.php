<?php
// Version 1.1
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
        $this->RegisterPropertyBoolean('debug', false);
        $this->RegisterPropertyString('EggTimerModuleId', '{17843F0A-BFC8-A4BA-E219-A2D10FC8E5BE}');
        
        // Ensure the webhook is registered
        $this->RegisterHook($this->ReadPropertyString('WebhookName'));
    }

    public function ApplyChanges() {
        parent::ApplyChanges();
        // Ensure the webhook is registered
        $this->RegisterHook($this->ReadPropertyString('WebhookName'));
    }

    private function RegisterHook($WebHook)
    {
        $debug = $this->ReadPropertyBoolean('debug');
        if($debug) IPS_LogMessage("HIKMOD","Register Hook Called");
        $ids = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');
        $find_Hook = '/hook/'.$WebHook;
        if (count($ids) > 0) {
            if($debug) IPS_LogMessage("HIKMOD","Webhooks vorhanden");
            $hooks = json_decode(IPS_GetProperty($ids[0], 'Hooks'), true);
            $hook_connected_to_script = false;
            $correct_hook_installed = false;
            $correct_hook_with_wrong_name_installed = false;
            foreach ($hooks as $index => $hook) {
                if ($hook['TargetID'] == $this->InstanceID) {
                    if($debug) IPS_LogMessage("HIKMOD","Webhook bereits mit Instanz verbunden");
                    $hook_connected_to_script = true;
                    if  ($hook['Hook'] == $find_Hook) {
                        $correct_hook_installed = true;
                        $hooks[$index]['TargetID'] = $this->InstanceID;
                        if($debug) IPS_LogMessage("HIKMOD","Webhook bereits mit der Instanz verbunden und hat den korrekten Namen");
                        break;
                    }
                    else{
                        $correct_hook_with_wrong_name_installed = true; 
                        $hooks[$index]['TargetID'] = $this->InstanceID;
                        if($debug) IPS_LogMessage("HIKMOD","Webhook bereits mit Instanz verbunden aber der  neue Name muss eingetragen werden");
                        break;                 
                    }
                }
            }
            if ($correct_hook_with_wrong_name_installed) {
                    if($debug) IPS_LogMessage("HIKMOD","Webhook  Name wird jetzt korrigiert");
                    $hooks[$index] = ['Hook' => $WebHook, 'TargetID' => $this->InstanceID];
                    IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
                    IPS_ApplyChanges($ids[0]);
            }  
            if(!$hook_connected_to_script ){
                if($debug) IPS_LogMessage("HIKMOD","Neuer Webhook wird jetzt für die Instanz installiert und verbunden");
                $hooks[] = ['Hook' => $WebHook, 'TargetID' => $this->InstanceID];
                IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
                IPS_ApplyChanges($ids[0]);
            }
        }
        else{
            if($debug) IPS_LogMessage("HIKMOD","Keine Webhooks vorhanden");
        }
    }

    public function  ProcessHookData() {
        
            $debug = $this->ReadPropertyBoolean('debug');
            if($debug) IPS_LogMessage("HIKMOD","=======================Start of Script Webhook Processing============================"); 
            /*       
            $eggTimerModuleId = $this->ReadPropertyString('EggTimerModuleId');
            if (!IPS_GetModule($eggTimerModuleId)) {
                if($debug) IPS_LogMessage("HIKMOD","Bitte erst das Egg Timer Modul aus dem Modul Store installieren");
                return;
            }
            */
            $webhookData = file_get_contents("php://input", true);
            if ($webhookData !== "") {
                if($debug) IPS_LogMessage("HIKMOD","Webhook has delivered File Data");
                $motionData = $this->parseEventNotificationAlert($webhookData);
                if (is_array($motionData)) {
                    if($debug) IPS_LogMessage("HIKMOD"."File Data","XML Parser hat ein Array zurückgegeben. Weitere Verarbeitung möglich");
                    if($debug) IPS_LogMessage("HIKMOD"."File Data","Hier ist das Array ".implode(" ",$motionData));
                    $this->handleMotionData($motionData,"File Data");
                }
                else{
                    if($debug) IPS_LogMessage("HIKMOD"."File Data","XML Parser hat kein Array zurückgeliefert, daher keine weitere Verarbeitung möglich ".implode(" ",$motionData));
                }
            } elseif (is_array($_POST)) {
                if($debug) IPS_LogMessage("HIKMOD"."Post Data","Webhook has delivered Post Data");
                if($debug) IPS_LogMessage("HIKMOD"."Post Data","Array ".implode(" ",$_POST));
                
                foreach ($_POST as $value => $content) {
                        if($debug) IPS_LogMessage("HIKMOD"."Post Data","Value : ".$value);
                        if($debug) IPS_LogMessage("HIKMOD"."Post Data","Content : ".$content);
                        $motionData = $this->parseEventNotificationAlert($content);
                        $this->handleMotionData($motionData, "Post Data");
                        
                        if(array_key_exists('channelName',$motionData)){ 
                            if($motionData['channelName'] != "")
                            { 
                                $this->handleMotionData($motionData, "Post Data");
                            }
                            else{
                                if($debug) IPS_LogMessage("HIKMOD"."Post Data","Array Key Channel Name is empty");
                            }
                        }
                        else{
                            if($debug) IPS_LogMessage("HIKMOD"."Post Data","No Array Key Channel Name");
                        }
                        
                    }
                    
            }
            else{
                if($debug) IPS_LogMessage("HIKMOD","Error Not expected Webhook Data");
            }
            if($debug) IPS_LogMessage("HIKMOD","=======================END of Script Webhook Processing============================");         
    }

    private function handleMotionData($motionData,$source) {
        $debug = $this->ReadPropertyBoolean('debug');
        if($debug) IPS_LogMessage("HIKMOD".$source,$source."--------------------------------Start of Script Motion Data -------------------");
        $notSetYet = 'NotSet';
        $parent = $this->InstanceID;
        $channelId = $this->ReadPropertyString('ChannelId');
        $savePath = $this->ReadPropertyString('SavePath');
        $username = $this->ReadPropertyString('UserName');
        $password= $this->ReadPropertyString('Password');
        $kamera_name = $motionData['channelName'];


        if (IPS_SemaphoreEnter($kamera_name."process",5000)) 
        {
            if($debug) IPS_LogMessage("HIKMOD".$source,"Semaphore process wurde betreten  ".$kamera_name);

            $kameraId = $this->manageVariable($parent, $kamera_name , 0, 'Motion', true, 0, ""); 
            $event_descriptionvar_id = $this->manageVariable($kameraId, $motionData['eventDescription'], 3, '~TextBox', true, 0, "");
 
            $username = GetValueString($this->manageVariable($kameraId, "User Name", 3, '~TextBox', true, 0, $username));
            $password = GetValueString($this->manageVariable($kameraId, "Password", 3, '~TextBox', true, 0, $password ));
 
            if ($username != $notSetYet && $password != $notSetYet) {
                $savePath .= $motionData['eventDescription'].$motionData['ipAddress'] . ".jpg";
                $this->downloadHikvisionSnapshot($motionData['ipAddress'], $channelId, $username, $password, $savePath);
                $this->manageMedia($event_descriptionvar_id, $motionData['eventDescription']."Last_Picture", $savePath);
            } else {
                if($debug) IPS_LogMessage("HIKMOD".$source, "Please set UserName and Password in Variable");
            }

            $dateTime_id = $this->manageVariable($event_descriptionvar_id, "Date and Time", 3, '~TextBox', true, 0, "");
            SetValueString($dateTime_id, $motionData['dateTime']);
            SetValueBoolean($kameraId, true);
            $kamera_IP_var_id = $this->manageVariable($kameraId, $motionData['ipAddress'], 3, '~TextBox', true, 0, "");      
            SetValueString($kamera_IP_var_id,$motionData['ipAddress']);

            $this->handle_egg_timer($source,$kamera_name,$kameraId);

            if($debug) IPS_LogMessage("HIKMOD".$source,"Leave process Semaphore  ".$kamera_name );
            IPS_SemaphoreLeave($kamera_name."process");
        }
        else
        {
            if($debug) IPS_LogMessage("HIKMOD".$source," Semaphore Active. No execution for this Data ".$kamera_name );
        }  
        if($debug) IPS_LogMessage("HIKMOD".$source,$source."--------------------------------End of Script Motion Data -------------------".$kamera_name );
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

    private function handle_egg_timer_alt($source,$kamera_name,$kameraId){ 
        $motion_active = $this->ReadPropertyInteger('MotionActive');
        $debug = $this->ReadPropertyBoolean('debug');
        if (IPS_SemaphoreEnter($kamera_name,1000)) 
        {
            if($debug) IPS_LogMessage("HIKMOD".$source,"Semaphore gesetzt um zu verhindern das mehrere Egg Timer installiert werden   ".$kamera_name );
            $eggTimerId = @IPS_GetObjectIDByName("Egg Timer", $kameraId);
            if ($eggTimerId) {
                if($debug) IPS_LogMessage("HIKMOD".$source,"Check 1 : Der Egg Timer existiert bereits und wird auf Aktiv gesetzt  ".$kameraId);
                $activ_id = @IPS_GetObjectIDByName("Aktiv",  $eggTimerId );
                if($debug) IPS_LogMessage("HIKMOD".$source,"Check 2 : Egg Timer existiert und wird auf Aktiv gesetzt   ".$kameraId);
                SetValueInteger(IPS_GetObjectIDByName("Zeit in Sekunden", $eggTimerId), $motion_active);
                RequestAction(IPS_GetObjectIDByName("Aktiv", $eggTimerId), true);
            } else {
                if($debug) IPS_LogMessage("HIKMOD".$source,"Egg Timer existiert NICHT und wird installiert  ".$kameraId);
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
                if($debug) IPS_LogMessage("HIKMOD".$source,"Event wurde installiert Event ID ".$eid." Egg Timer ID ".$insId);
            }
            IPS_SemaphoreLeave($kamera_name );
        }
        else
        {
            if($debug) IPS_LogMessage("HIKMOD".$source,"Es wird bereits ein Egg Timer installiert Semaphore war gesetzt ".$kamera_name );
        }  
    }


    private function handle_egg_timer($source,$kamera_name,$kameraId){ 
        $motion_active = $this->ReadPropertyInteger('MotionActive');
        $debug = $this->ReadPropertyBoolean('debug');
        if (IPS_SemaphoreEnter($kamera_name,1000)) 
        {
            if($debug) IPS_LogMessage("HIKMOD".$source,"Habe Semaphore gesetzt um zu verhindern das mehrere Egg Timer installiert werden   ".$kamera_name );
            $eggTimerId = @IPS_GetObjectIDByName("Egg Timer", $kameraId);
            if ($eggTimerId) {
                if($debug) IPS_LogMessage("HIKMOD".$source,"Check 1 : Der Egg Timer existiert bereits und wird auf Aktiv gesetzt  ".$kameraId);
                $activ_id = @IPS_GetObjectIDByName(Translate('Active'),  $eggTimerId );
                if($debug) IPS_LogMessage("HIKMOD".$source,"Check 2 : Egg Timer existiert und wird auf Aktiv gesetzt   ".$kameraId);
                SetValueInteger(IPS_GetObjectIDByName(Translate('Time in Seconds'), $eggTimerId), $motion_active);
                RequestAction(IPS_GetObjectIDByName(Translate('Active'), $eggTimerId), true);
            } else {
                if($debug) IPS_LogMessage("HIKMOD".$source,"Egg Timer existiert NICHT und wird installiert  ".$kameraId);
                $insId = IPS_CreateInstance($this->ReadPropertyString('EggTimerModuleId'));
                IPS_SetName($insId, "Egg Timer");
                IPS_SetParent($insId, $kameraId);
                IPS_ApplyChanges($insId);
                RequestAction(IPS_GetObjectIDByName(Translate('Active'), $insId), true);
                SetValueInteger(IPS_GetObjectIDByName(Translate('Time in Seconds'), $insId), $motion_active);
                $eid = IPS_CreateEvent(0);
                IPS_SetEventTrigger($eid, 4, IPS_GetObjectIDByName(Translate('Active'), $insId));
                IPS_SetParent($eid, $kameraId);
                IPS_SetEventAction($eid, "{75C67945-BE11-5965-C569-602D43F84269}", ["VALUE" => false]);
                IPS_SetEventActive($eid, true);
                IPS_SetEventTriggerValue($eid, false);
                if($debug) IPS_LogMessage("HIKMOD".$source,"Event wurde installiert Event ID ".$eid." Egg Timer ID ".$insId);
            }
            IPS_SemaphoreLeave($kamera_name );
        }
        else
        {
            if($debug) IPS_LogMessage("HIKMOD".$source,"Es wird bereits ein Egg Timer installiert Semaphore war gesetzt ".$kamera_name );
        }  
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
