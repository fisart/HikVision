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
        $this->RegisterAttributeInteger('counter', '0');
        $this->RegisterAttributeString('EggTimerModuleId', '{17843F0A-BFC8-A4BA-E219-A2D10FC8E5BE}');
        
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
        if($debug) $this->LogMessage("Register Hook Called", KL_DEBUG);
        $ids = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');
        $find_Hook = '/hook/'.$WebHook;
        if (count($ids) > 0) {
            if($debug) $this->LogMessage("Webhooks vorhanden", KL_DEBUG);
            $hooks = json_decode(IPS_GetProperty($ids[0], 'Hooks'), true);
            $hook_connected_to_script = false;
            $correct_hook_installed = false;
            $correct_hook_with_wrong_name_installed = false;
            foreach ($hooks as $index => $hook) {
                if ($hook['TargetID'] == $this->InstanceID) {
                    if($debug) $this->LogMessage("Webhook bereits mit Instanz verbunden", KL_DEBUG);
                    $hook_connected_to_script = true;
                    if  ($hook['Hook'] == $find_Hook) {
                        $correct_hook_installed = true;
                        $hooks[$index]['TargetID'] = $this->InstanceID;
                        if($debug) $this->LogMessage("Webhook bereits mit der Instanz verbunden und hat den korrekten Namen", KL_DEBUG);
                        break;
                    }
                    else{
                        $correct_hook_with_wrong_name_installed = true; 
                        $hooks[$index]['TargetID'] = $this->InstanceID;
                        if($debug) $this->LogMessage("Webhook bereits mit Instanz verbunden aber der neue Name muss eingetragen werden", KL_DEBUG);
                        break;                 
                    }
                }
            }
            if ($correct_hook_with_wrong_name_installed) {
                    if($debug) $this->LogMessage("Webhook Name wird jetzt korrigiert", KL_DEBUG);
                    $hooks[$index] = ['Hook' => $WebHook, 'TargetID' => $this->InstanceID];
                    IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
                    IPS_ApplyChanges($ids[0]);
            }  
            if(!$hook_connected_to_script ){
                if($debug) $this->LogMessage("Neuer Webhook wird jetzt für die Instanz installiert und verbunden", KL_DEBUG);
                $hooks[] = ['Hook' => $WebHook, 'TargetID' => $this->InstanceID];
                IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
                IPS_ApplyChanges($ids[0]);
            }
        }
        else{
            if($debug) $this->LogMessage("Keine Webhooks vorhanden", KL_DEBUG);
        }
    }

    public function ProcessHookData() {
        $counter = $this->ReadAttributeInteger('counter');
        $counter = $counter + 1;
        $this->WriteAttributeInteger('counter',$counter);
        $debug = $this->ReadPropertyBoolean('debug');
        if($debug) $this->LogMessage("=======================Start of Script Webhook Processing============================".$counter, KL_DEBUG); 
               
        $eggTimerModuleId = $this->ReadAttributeString('EggTimerModuleId');
        if (!IPS_GetModule($eggTimerModuleId)) {
            if($debug) $this->LogMessage("Bitte erst das Egg Timer Modul aus dem Modul Store installieren", KL_ERROR);
            return;
        }
        
        $webhookData = file_get_contents("php://input", true);
        if ($webhookData !== "") {
            if($debug) $this->LogMessage("Webhook has delivered File Data", KL_DEBUG);
            $motionData = $this->parseEventNotificationAlert($webhookData);
            if (is_array($motionData)) {
                if($debug) $this->LogMessage("File Data".$counter." XML Parser hat ein Array zurückgegeben. Weitere Verarbeitung möglich", KL_DEBUG);
                if($debug) $this->LogMessage("File Data".$counter." Hier ist das Array ".implode(" ",$motionData), KL_DEBUG);
                $this->handleMotionData($motionData,"File Data". $counter);
            }
            else{
                if($debug) $this->LogMessage("File Data".$counter." XML Parser hat kein Array zurückgeliefert, daher keine weitere Verarbeitung möglich ".implode(" ",$motionData), KL_DEBUG);
            }
        } elseif (is_array($_POST)) {
            if($debug) $this->LogMessage("Post Data".$counter." Webhook has delivered Post Data", KL_DEBUG);
            if($debug) $this->LogMessage("Post Data".$counter." Array ".implode(" ",$_POST), KL_DEBUG);
            if(implode(" ",$_POST) == "")
            {
                if($debug) $this->LogMessage("Post Data".$counter." Array Empty", KL_DEBUG);
            }
            else{
                foreach ($_POST as $value => $content) {
                        if($debug) $this->LogMessage("Post Data".$counter." Value : ".$value, KL_DEBUG);
                        if($debug) $this->LogMessage("Post Data".$counter." Content : ".$content, KL_DEBUG);
                        $motionData = $this->parseEventNotificationAlert($content);
                        $this->handleMotionData($motionData, "Post Data". $counter);
                        
                        if(array_key_exists('channelName',$motionData)){ 
                            if($motionData['channelName'] != "")
                            { 
                                $this->handleMotionData($motionData, "Post Data". $counter);
                            }
                            else{
                                if($debug) $this->LogMessage("Post Data".$counter." Array Key Channel Name is empty", KL_DEBUG);
                            }
                        }
                        else{
                            if($debug) $this->LogMessage("Post Data".$counter." No Array Key Channel Name", KL_DEBUG);
                        }
                        
                    }
            }
        }
        else{
            if($debug) $this->LogMessage("Error Not expected Webhook Data", KL_ERROR);
        }
        if($debug) $this->LogMessage("=======================END of Script Webhook Processing============================".$counter, KL_DEBUG);         
    }

    private function handleMotionData($motionData,$source) {
        $debug = $this->ReadPropertyBoolean('debug');
        if($debug) $this->LogMessage($source."--------------------------------Start of Script Motion Data -------------------".$motionData['channelName'], KL_DEBUG);
        $notSetYet = 'NotSet';
        $parent = $this->InstanceID;
        $channelId = $this->ReadPropertyString('ChannelId');
        $savePath = $this->ReadPropertyString('SavePath');
        $username = $this->ReadPropertyString('UserName');
        $password= $this->ReadPropertyString('Password');
        $kamera_name = $motionData['channelName'];
        $semaphore_process_name = $kamera_name."10";

        if (IPS_SemaphoreEnter($semaphore_process_name ,5000)) 
        {
            if($debug) $this->LogMessage("Semaphore process wurde betreten  ".$semaphore_process_name, KL_DEBUG);

            $kameraId = $this->manageVariable($parent, $kamera_name , 0, 'Motion', true, 0, ""); 
            $event_descriptionvar_id = $this->manageVariable($kameraId, $motionData['eventDescription'], 3, '~TextBox', true, 0, "");
 
            $username = GetValueString($this->manageVariable($kameraId, "User Name", 3, '~TextBox', true, 0, $username));
            $password = GetValueString($this->manageVariable($kameraId, "Password", 3, '~TextBox', true, 0, $password ));

            if ($username != $notSetYet && $password != $notSetYet) {
                $savePath .= $motionData['eventDescription'].$motionData['ipAddress'] . ".jpg";
                $this->downloadHikvisionSnapshot($motionData['ipAddress'], $channelId, $username, $password, $savePath);
                $this->manageMedia($event_descriptionvar_id, $motionData['eventDescription']."Last_Picture", $savePath);
            } else {
                if($debug) $this->LogMessage("Please set UserName and Password in Variable", KL_WARNING);
            }

            $dateTime_id = $this->manageVariable($event_descriptionvar_id, "Date and Time", 3, '~TextBox', true, 0, "");
            SetValueString($dateTime_id, $motionData['dateTime']);
            SetValueBoolean($kameraId, true);
            $kamera_IP_var_id = $this->manageVariable($kameraId, $motionData['ipAddress'], 3, '~TextBox', true, 0, "");      
            SetValueString($kamera_IP_var_id,$motionData['ipAddress']);

            $this->handle_egg_timer($source,$kamera_name,$kameraId);

            if($debug) $this->LogMessage("Leave process Semaphore  ".$semaphore_process_name, KL_DEBUG);
            IPS_SemaphoreLeave($semaphore_process_name);
        }
        else
        {
            if($debug) $this->LogMessage("Process Semaphore Active. No execution for this Data ".$semaphore_process_name, KL_DEBUG);
        }  
        if($debug) $this->LogMessage($source."--------------------------------End of Script Motion Data -------------------".$kamera_name, KL_DEBUG );
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

    private function handle_egg_timer($source,$kamera_name,$kameraId){ 
        $motion_active = $this->ReadPropertyInteger('MotionActive');
        $debug = $this->ReadPropertyBoolean('debug');
        $active = $this->Translate('Active');
        $time_in_seconds = $this->Translate('Time in Seconds');
        $semaphore_egg_timer_name = $kamera_name."EggTimer1";
        if($debug) $this->LogMessage("Lokalisierte Variablen Namen des Egg Timers. Status : ".$active ."  Zeitdauer : ".$time_in_seconds, KL_DEBUG);

        if (IPS_SemaphoreEnter($semaphore_egg_timer_name,1000)) 
        {
            if($debug) $this->LogMessage("Habe Semaphore gesetzt um zu verhindern das mehrere Egg Timer installiert werden   ".$semaphore_egg_timer_name, KL_DEBUG );
            $eggTimerId = @IPS_GetObjectIDByName("Egg Timer", $kameraId);
            if ($eggTimerId) {
                if($debug) $this->LogMessage("Der Egg Timer existiert bereits und wird aktiviert  ".$kameraId, KL_DEBUG);
                $activ_id = @IPS_GetObjectIDByName($active,  $eggTimerId );
                SetValueInteger(IPS_GetObjectIDByName($time_in_seconds, $eggTimerId), $motion_active);
                RequestAction(IPS_GetObjectIDByName($active, $eggTimerId), true);
            } else {
                if($debug) $this->LogMessage("Egg Timer existiert NICHT und wird installiert  ".$kameraId, KL_DEBUG);
                $insId = IPS_CreateInstance($this->ReadAttributeString('EggTimerModuleId'));
                IPS_SetName($insId, "Egg Timer");
                IPS_SetParent($insId, $kameraId);
                IPS_ApplyChanges($insId);
                RequestAction(IPS_GetObjectIDByName($active, $insId), true);
                SetValueInteger(IPS_GetObjectIDByName($time_in_seconds, $insId), $motion_active);
                $eid = IPS_CreateEvent(0);
                IPS_SetEventTrigger($eid, 4, IPS_GetObjectIDByName($active, $insId));
                IPS_SetParent($eid, $kameraId);
                IPS_SetEventAction($eid, "{75C67945-BE11-5965-C569-602D43F84269}", ["VALUE" => false]);
                IPS_SetEventActive($eid, true);
                IPS_SetEventTriggerValue($eid, false);
                if($debug) $this->LogMessage("Event wurde installiert Event ID ".$eid." Egg Timer ID ".$insId, KL_DEBUG);
            }
            IPS_SemaphoreLeave($semaphore_egg_timer_name );
        }
        else
        {
            if($debug) $this->LogMessage("Es wird bereits ein Egg Timer installiert Semaphore war gesetzt ".$semaphore_egg_timer_name, KL_DEBUG);
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

    public function Destroy() {
        parent::Destroy();
        // Add your custom code here

        if (!IPS_InstanceExists($this->InstanceID)) 
        { 
            $this->LogMessage("Destroy existing HIKVISION Webhook Called", KL_MESSAGE);
            $ids = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');
            if (count($ids) > 0) {
                $this->LogMessage("Webhooks vorhanden", KL_MESSAGE);
                $hooks = json_decode(IPS_GetProperty($ids[0], 'Hooks'), true);
                $correct_hook_found = false;
                foreach ($hooks as $index => $hook) {
                    if ($hook['TargetID'] == $this->InstanceID) { 
                        $this->LogMessage(implode(" ",$hook), KL_DEBUG);
                        $correct_hook_found = true;
                        break;
                    }                 
                }
                if ( $correct_hook_found  ) {
                    $this->LogMessage("Webhook wird jetzt gelöscht", KL_MESSAGE);
        
                    // Remove the specific webhook from the hooks array
                    unset($hooks[$index]);
                
                    // Re-index the array to prevent gaps in the keys
                    $hooks = array_values($hooks);
                
                    // Update the hooks property with the modified array
                    IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
                    IPS_ApplyChanges($ids[0]);
                }  
                else
                {
                    $this->LogMessage("Webhook not found", KL_WARNING);
                }
            }
            else{
                $this->LogMessage("Keine Webhooks vorhanden", KL_MESSAGE);
            }
            // Call the parent destroy to ensure the instance is properly destroyed
        }
        else{
            $this->LogMessage("Instanz wurde nicht gelöscht daher bleibt der Webhook bestehen", KL_MESSAGE);            
        }
    }
}
?>
