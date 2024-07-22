<?php

class CameraWebhookModule extends IPSModule {

    public function Create() {
        // Never delete this line!
        parent::Create();
        
        // Register properties for camera credentials
        $this->RegisterPropertyString("CameraUsernames", json_encode([]));
        $this->RegisterPropertyString("CameraPasswords", json_encode([]));

        // Register a webhook
        $this->RegisterHook('/hook/camerawebhook');
    }

    public function ApplyChanges() {
        // Never delete this line!
        parent::ApplyChanges();

        // Ensure the webhook is registered
        $this->RegisterHook('/hook/camerawebhook');
        
        // Check if the Egg Timer module is installed
        if (!$this->IsEggTimerInstalled()) {
            $this->PromptUserToInstallEggTimer();
        }
    }

    private function RegisterHook($WebHook) {
        $ids = IPS_GetInstanceListByModuleID("{3565B1F2-8F7B-4311-A4B6-1BF1D868F39E}"); 
        if (count($ids) > 0) {
            $hooks = json_decode(IPS_GetProperty($ids[0], 'Hooks'), true);
            foreach ($hooks as $index => $hook) {
                if ($hook['Hook'] == $WebHook) {
                    return;
                }
            }
            $hooks[] = ['Hook' => $WebHook, 'TargetID' => $this->InstanceID];
            IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
            IPS_ApplyChanges($ids[0]);
        }
    }
    public function GetConfigurationForm() {
        $data = [
            "elements" => [
                [
                    "type" => "List",
                    "name" => "CameraCredentials",
                    "caption" => "Camera Credentials",
                    "add" => true,
                    "delete" => true,
                    "columns" => [
                        [
                            "caption" => "IP Address",
                            "name" => "IPAddress",
                            "width" => "200px",
                            "edit" => ["type" => "ValidationTextBox"]
                        ],
                        [
                            "caption" => "Username",
                            "name" => "Username",
                            "width" => "150px",
                            "edit" => ["type" => "ValidationTextBox"]
                        ],
                        [
                            "caption" => "Password",
                            "name" => "Password",
                            "width" => "150px",
                            "edit" => ["type" => "PasswordTextBox"]
                        ]
                    ],
                    "values" => $this->GetCredentials()
                ]
            ]
        ];

        return json_encode($data);
    }

    private function GetCredentials() {
        $usernames = json_decode($this->ReadPropertyString("CameraUsernames"), true);
        $passwords = json_decode($this->ReadPropertyString("CameraPasswords"), true);
        $credentials = [];
        foreach ($usernames as $ip => $username) {
            $credentials[] = [
                "IPAddress" => $ip,
                "Username" => $username,
                "Password" => $passwords[$ip] ?? ''
            ];
        }
        return $credentials;
    }
    public function WebHookHandler() {
        IPS_LogMessage("WebHook Kamera", "START");
        $data = file_get_contents("php://input", true);
        IPS_LogMessage("WebHook Kamera", "File: " . $data);

        if ($data !== "") {
            $motion_data = $this->parseEventNotificationAlert($data);
            if (is_array($motion_data)) {
                $this->ProcessMotionData($motion_data);
            } else {
                IPS_LogMessage("WebHook Kamera", "ERROR: Invalid motion data");
            }
        } elseif (is_array($_POST)) {
            foreach ($_POST as $value) {
                $motion_data = $this->parseEventNotificationAlert($value);
                if (is_array($motion_data)) {
                    $this->ProcessMotionData($motion_data);
                }
            }
        } else {
            IPS_LogMessage("WebHook Kamera", "ERROR: No valid input");
        }

        IPS_LogMessage("WebHook Kamera", "END");
        echo 'Webhook received!';
    }

    private function ProcessMotionData($motion_data) {
        IPS_LogMessage("WebHook Kamera", $motion_data['eventDescription'] . "  " . $motion_data['ipAddress'] . "  " . $motion_data['dateTime'] . "  " . $motion_data['channelName']);

        // Manage variables for motion data
        $name = $motion_data['channelName'];
        $type = 0;
        $parent = $this->InstanceID;
        $profil = "Motion";
        $logging = true;
        $aggregation_type = 0;
        $kamera_id = $this->manage_variable($parent, $name, $type, $profil, $logging, $aggregation_type);
        SetValueBoolean($kamera_id, true);

        $name = $motion_data['ipAddress'];
        $type = 3;
        $profil = "~TextBox";
        $kamera_name = $this->manage_variable($kamera_id, $name, $type, $profil, $logging, $aggregation_type);
        SetValueString($kamera_name, $motion_data['eventDescription']);

        $name = "Date and Time";
        $type = 3;
        $kamera_name = $this->manage_variable($kamera_id, $name, $type, $profil, $logging, $aggregation_type);
        SetValueString($kamera_name, $motion_data['dateTime']);

        // Handle egg timer logic
        $this->handleEggTimer($kamera_id);

        // Manage media for snapshot
        $savePath = 'webfront/user/' . $motion_data['ipAddress'] . ".jpg";
        $this->downloadHikvisionSnapshot($motion_data['ipAddress'], '101', $this->GetCameraUsername($motion_data['ipAddress']), $this->GetCameraPassword($motion_data['ipAddress']), $savePath);
        $name = "Last_Picture";
        $this->manage_media($kamera_id, $name, $savePath);
    }
    private function handleEggTimer($parent) {
        $egg_timer_id = @IPS_GetObjectIDByName("Egg Timer", $parent);
        if ($egg_timer_id) {
            RequestAction(IPS_GetObjectIDByName("Aktiv", $egg_timer_id), true);
        } else {
            if ($this->IsEggTimerInstalled()) {
                $InsID = IPS_CreateInstance("{17843F0A-BFC8-A4BA-E219-A2D10FC8E5BE}");
                IPS_SetName($InsID, "Egg Timer");
                IPS_SetParent($InsID, $parent);
                IPS_ApplyChanges($InsID);
                RequestAction(IPS_GetObjectIDByName("Aktiv", $InsID), true);
                SetValueInteger(IPS_GetObjectIDByName("Zeit in Sekunden", $InsID), 300);

                $eid = IPS_CreateEvent(0);
                IPS_SetEventTrigger($eid, 4, IPS_GetObjectIDByName("Aktiv", $InsID));
                IPS_SetParent($eid, $parent);
                IPS_SetEventAction($eid, "{75C67945-BE11-5965-C569-602D43F84269}", ["VALUE" => false]);
                IPS_SetEventActive($eid, true);
                IPS_SetEventTriggerValue($eid, false);
            } else {
                IPS_LogMessage("WebHook Kamera", "ERROR: Egg Timer module is not installed.");
                echo 'Please install the Egg Timer module from the Module Store.';
            }
        }
    }

    private function IsEggTimerInstalled() {
        $moduleList = IPS_GetModuleList();
        return in_array("{17843F0A-BFC8-A4BA-E219-A2D10FC8E5BE}", $moduleList);
    }

    private function PromptUserToInstallEggTimer() {
        echo 'The Egg Timer module is not installed. Please install it from the Module Store: https://www.symcon.de/de/service/dokumentation/modulreferenz/eieruhr/';
    }

    private function GetCameraUsername($ipAddress) {
        $usernames = json_decode($this->ReadPropertyString("CameraUsernames"), true);
        return $usernames[$ipAddress] ?? 'admin';
    }

    private function GetCameraPassword($ipAddress) {
        $passwords = json_decode($this->ReadPropertyString("CameraPasswords"), true);
        return $passwords[$ipAddress] ?? '';
    }

    private function parseEventNotificationAlert($xmlString) {
        $xml = @simplexml_load_string($xmlString, "SimpleXMLElement", LIBXML_NOCDATA);
        if ($xml === false) {
            return false;
        }
        $json = json_encode($xml);
        return json_decode($json, true);
    }
    private function manage_variable($parent, $name, $type, $profil, $logging, $aggregation_type) {
        $archive_id = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
        $var_id = @IPS_GetVariableIDByName($name, $parent);
        if ($var_id === false) {
            $var_id = IPS_CreateVariable($type);
            if ($profil != "") IPS_SetVariableCustomProfile($var_id, $profil);
            IPS_SetName($var_id, $name);
            IPS_SetParent($var_id, $parent);
            AC_SetLoggingStatus($archive_id, $var_id, $logging);
            if ($logging || $type != 3) {
                AC_SetAggregationType($archive_id, $var_id, $aggregation_type);
            }
            IPS_ApplyChanges($archive_id);
        }
        return $var_id;
    }

    private function manage_media($parent, $name, $ImageFile) {
        $MediaID = @IPS_GetMediaIDByName($name, $parent);
        if ($MediaID === false) {
            $MediaID = IPS_CreateMedia(1);
            IPS_SetMediaFile($MediaID, $ImageFile, true);
            IPS_SetName($MediaID, $name);
            IPS_SetParent($MediaID, $parent);
        }
        return $MediaID;
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
            $rootDir = IPS_GetKernelDir();
            $savePath = $rootDir . DIRECTORY_SEPARATOR . $relativePath;
            $fileHandle = fopen($savePath, 'w');
            if ($fileHandle !== false) {
                fwrite($fileHandle, $imageData);
                fclose($fileHandle);
                return true;
            }
        }
        return false;
    }
}

?>
