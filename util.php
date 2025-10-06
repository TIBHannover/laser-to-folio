<?php

// Load config
if(is_file("config.json")){
  $config = json_decode(file_get_contents("config.json"), true);
  $API_KEY = $config['API_KEY'];
  $API_PASS = $config['API_PASS'];
  $ORG_GUID = $config['ORG_GUID'];
  $SAVE_PATH = $config['API_KEY'];
  $FOLIO_USER = $config['FOLIO_USER'];
  $FOLIO_PASS = $config['FOLIO_PASS'];
  $FOLIO_TENANT = $config['FOLIO_TENANT'];
}
//LAS:eR API Base-URL
$API_URL="https://laser.hbz-nrw.de/api/v0/";

// Status mapping, can be changed to fit own needs
$status = array(
  "In Progress" => "not_yet_active",
  "Current" => "active",
  "Participation" => "active",
  "In negotiation" => "in_negotiation",
  "Under negotiation" => "in_negotiation",
  "Not yet active" => "not_yet_active",
  "Rejected" => "rejected",
  "Retired" => "expired",
  "Expired" => "closed"
);

// Missing values here are currently not in FOLIO
$resourceMap = array(
  "ejournalSingle" => "journals",
  "database" => "database",
  "other" => "",
  "ejournalPackage" => "journals",
  "mixed" => "",
  "data" => ""
);


// Function to get a current timestamp for logging
function getTimestamp(){
  $date = new DateTimeImmutable("now");
  return $date->format('Y-m-d H:i:s');
}


// Generates the HMAC Token for requests to LAS:eR
function generateHmac($method, $path, $query_params){
  global $API_KEY, $API_PASS;
  $time = getTimestamp();
  error_log("[INFO $time] Generating HMAC for $path" . http_build_query($query_params) ."\n", 3, "laser.log");
  $query = implode("&", $query_params);
  $message = $method . $path . $query;
  $hash = hash_hmac("sha256", $message, $API_PASS, false);
  return "hmac $API_KEY:::$hash,hmac-sha256";
}


// Sends a GET Request to given LAS:eR endpoint with params
function laserRequest($endpoint, $params){
  global $API_URL;

  $time = getTimestamp();
  error_log("[INFO $time] Calling LAS:eR API at \"$endpoint\" with params: " . http_build_query($params) . "\n", 3, "laser.log");
  $token = generateHmac("GET", "/api/v0/$endpoint", array("q=" . $params['q'], "v=" . $params['v']));
  $params = "?q=" . urlencode($params['q']) . "&v=" . urlencode($params['v']);
  $curlHandler = curl_init($API_URL . $endpoint . $params);
  // Returns response instead of printing it on exec
  curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true); 

  $headers = array();

  if($endpoint == "document"){
    $headers[] = 'accept: */*';
  }else{
    $headers[] = 'accept: application/json';
  }
  $headers[] = "x-authorization: $token";
  curl_setopt($curlHandler, CURLOPT_HTTPHEADER, $headers);
  
  $response = curl_exec($curlHandler);
  $status = curl_getinfo($curlHandler, CURLINFO_HTTP_CODE);
  if($status != 200){
    $time = getTimestamp();
    error_log("[ERROR $time] Response code $status on endpoint \"$endpoint\"\n\n", 3, "laser.log");
  }

  return $response;
}


// Call LAS:eR endpoint depending on the submitted laserID
function callAPI($uid){
  $split = explode(":", $uid);
  if(count($split) == 1){
    // uuid of a document
    return laserRequest("document", array('q' => 'uuid', 'v' => $uid));
  }else{
    if($split[0] == "org"){
      // org laserIDs are a special case, the endpoint is not called "org"
      return laserRequest("organisation", array('q' => 'laserID', 'v' => $uid));
    }else{
      return laserRequest($split[0], array('q' => 'laserID', 'v' => $uid));
    }
  }
}


// Download a file from LAS:eR if it does not yet exist
function downloadDocument($path, $filename, $uuid){
  // FILES WITH SAME FILENAME CURRENTLY GET SKIPPED
  $time = getTimestamp();
  if(is_file("$path/$filename")){
    error_log("[INFO $time] $filename already found locally, skipping download.\n", 3, "laser.log");
  }
  error_log("[INFO $time] Downloading $filename...\n", 3, "laser.log");
  
  // Make a request to download the file
  $fileData = callAPI($uuid);
  // Check if no file was found
  $decodedData = json_decode($fileData, true);
  if(isset($decodedData)){
    if(isset($decodedData['status']) && $decodedData['status'] == 404){
      $time = getTimestamp();
      error_log("[WARNING $time] File $filename was not found in LAS:eR (returned 404)\n", 3, "laser.log");
      return;
    }
  }
  
  // Write contents of file to disk
  $fh = fopen("$path/$filename", "w");
  fwrite($fh, $fileData);
  fclose($fh);
  $time = getTimestamp();
  error_log("[INFO $time] $filename saved on disk.\n", 3, "laser.log");
}


// Returns the data of object with laserID as an associative array
function getJsonData($path, $laserID){
  $time = getTimestamp();
  if(is_file($path)){
    error_log("[INFO $time] JSON data found locally for $laserID\n", 3, "laser.log");
    return json_decode(file_get_contents($path), true);
  }else{
    error_log("[INFO $time] No local data found for $laserID, calling API.\n", 3, "laser.log");
    $data = callAPI($laserID);
    $fh = fopen($path, "w");
    fwrite($fh, $data);
    fclose($fh);
    return json_decode($data, true);
  }
}


// Log into okAPI to recieve Auth token
function okapiLogin(){
  global $FOLIO_PASS, $FOLIO_TENANT, $FOLIO_USER;
  $time = getTimestamp();
  error_log("[INFO $time] Logging into okapi..\n", 3, "import.log");
  $curlHandler = curl_init("https://okapi.gbv.de/authn/login");
  curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true);
  $header = array("x-okapi-tenant: $FOLIO_TENANT", "Content-type: application/json");
  curl_setopt($curlHandler, CURLOPT_HTTPHEADER, $header);
  curl_setopt($curlHandler, CURLOPT_POST, 1);
  $data = array("username" => $FOLIO_USER, "password" => $FOLIO_PASS);
  curl_setopt($curlHandler, CURLOPT_POSTFIELDS, json_encode($data));

  $response = json_decode(curl_exec($curlHandler), true);
  $token = $response['okapiToken'] ?? null;
  if($token == null){
    $time = getTimestamp();
    error_log("[FATAL $time] Did not recieve okapi Token after login. Dumping response and aborting.\n\n", 3, "import.log");
    print "Failed at okapiLogin<br>";
    exit;
  }
  return $response['okapiToken'];
}

// Call FOLIO API to check if $name is already used as agreement name
function checkAgreementName($name, $okapiToken){
  $time = getTimestamp();
  error_log("[INFO $time] Checking if '$name' is already in use.\n", 3, "import.log");
  $curlHandler = curl_init("https://okapi.gbv.de/erm/validate/subscriptionAgreement/name");
  curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true);
  $header = array("x-okapi-token: $okapiToken", "Content-type: application/json");
  curl_setopt($curlHandler, CURLOPT_HTTPHEADER, $header);
  curl_setopt($curlHandler, CURLOPT_POST, 1);
  curl_setopt($curlHandler, CURLOPT_POSTFIELDS, json_encode(array("name" => $name)));
  $response = curl_exec($curlHandler);
  $return_code = curl_getinfo($curlHandler, CURLINFO_RESPONSE_CODE);
  $time = getTimestamp();
  if($return_code == 204){
    error_log("[INFO $time] '$name' is not in use.\n", 3, "import.log");
    return true;
  }else{
    error_log("[ERROR $time] '$name' already in use, appending 'DUPLICATE' suffix.\n", 3, "import.log");
    return false;
  }
}


// Sends a POST request to okAPI to create a new resource
function uploadResource($resource, $type, $okapiToken){
  $time = getTimestamp();
  error_log("[INFO $time] Uploading $type\n", 3, "import.log");
  switch ($type) {
    case 'license':
      $curlHandler = curl_init("https://okapi.gbv.de/licenses/licenses");
      break;
    case 'subscription':
      $curlHandler = curl_init("https://okapi.gbv.de/erm/sas");
      break;
    default:
      $time = getTimestamp();
      error_log("[FATAL $time] Unknown type $type.\n", 3, "import.log");
      exit;
  }

  curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true);
  $header = array("x-okapi-token: $okapiToken", "Content-type: application/json");
  curl_setopt($curlHandler, CURLOPT_HTTPHEADER, $header);
  curl_setopt($curlHandler, CURLOPT_POST, 1);
  curl_setopt($curlHandler, CURLOPT_POSTFIELDS, json_encode($resource));

  $response = json_decode(curl_exec($curlHandler), true);
  if(!isset($response['id'])){
    $time = getTimestamp();
    error_log("[FATAL $time] Could not recieve folioID for resource\n", 3, "import.log");
    print "Failed at uploadResource<br>";
    print(json_encode($resource));
    print "<br>";
    print(json_encode($response));
    exit;
  }

    return $response;
}


// Maps a property according to given mapping
function mapProperty($prop, $map){
  $propName = $prop['token'];
  $result = array();
  // Authorized Users hat meist leeren Wert -> steht für standard
  if($propName == "Authorized Users" and !isset($prop['value'])){
    $result['value'] = "standart";
  }else{
    $mapping = $map[$propName];
    if($mapping['type'] == "choice"){
      if(!isset($prop['value'])) return array();;
      $result['value'] = $mapping[$prop['value']];
    }elseif($mapping['type'] == "text"){
      $result['value'] = $prop['value'] ?? "KEIN INHALT";
    }elseif($mapping['type'] == "special"){
      switch ($propName) {
        case 'Langzeitarchivierung':
          $result['value'] = array(array("value" => $mapping[$prop['note']]));
          break;
        default:
          break;
      }
    }
  }
  // Concatinate the Note
  $note = $prop['note'] ?? "";
  $paragraph = $prop['paragraph'] ?? "";
  $result['note'] = "$note::$paragraph";


  return $result;
}


// Generates a random string of numbers - used for document upload
function generateRandomString($length){
  $characters = "0123456789";
  $result = "";
  for($i = 0; $i < $length; $i++){
    $result .= $characters[random_int(0,strlen($characters)-1)];
  }
  return $result;
}


// Uploads a document to FOLIO and returns the folioID
function uploadDocument($path, $filename, $type, $okapiToken){
  //$okapiToken = okapiLogin();
  $documentContentType = array(
    "PDF" => "application/pdf",
    "pdf" => "application/pdf",
    "msg" => "application/octet-stream",
    "xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    "doc" => "application/msword",
    "txt" => "text/plain"
  );

  switch ($type) {
    case 'license':
      $curlHandler = curl_init("https://okapi.gbv.de/licenses/files");
      break;
    case 'subscription':
      $curlHandler = curl_init("https://okapi.gbv.de/erm/files");
      break;
    default:
      $time = getTimestamp();
      error_log("[FATAL $time] Unknown document endpoint for \"$type\"\n", 3, "import.log");
      exit;
  }
  $splitFilename = explode(".", $filename);
  $fileType = end($splitFilename);
  $boundary = "---------------------------" . generateRandomString(30);
  curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curlHandler, CURLOPT_POST, 1);
  
  $rawData = "--$boundary\r\n";
  $rawData .= 'Content-Disposition: form-data; name="upload"; filename="' . $filename . '"' . "\r\n";
  $rawData .= "Content-Type: " . $documentContentType[$fileType] . "\r\n\r\n";
  $rawData .= file_get_contents($path);
  $rawData .= "\r\n";
  $rawData .= "--$boundary--";

  $header = array("x-okapi-token: $okapiToken", "Content-type: multipart/form-data; boundary=$boundary");
  curl_setopt($curlHandler, CURLOPT_HTTPHEADER, $header);

  curl_setopt($curlHandler, CURLOPT_POSTFIELDS, $rawData);
  $response = curl_exec($curlHandler);
  $responseJSON = json_decode($response, true);
  if(!isset($responseJSON['id'])){
    $time = getTimestamp();
    error_log("[FATAL $time] Could not recieve folioID for document.\n", 3, "import.log");
    print "Failed at uploadDocument<br>";
    var_dump($response);
    exit;
  }
  
  return $responseJSON;
}


// Checks if key was already imported into FOLIO
// Returns folioID if found, else returns false
function checkDatabase($db, $key){
  #$stmt = $mysqli->prepare("SELECT folioID FROM imported WHERE laserID = ?");
  #$stmt->bind_param("s", $key);
  #$stmt->execute();
  #$result = $stmt->get_result();
  $stmt = $db->prepare("SELECT folioID FROM imported WHERE globalUID = ?");
  $stmt->bindValue(1, $key);
  $result = $stmt->execute();
  $resArray = $result->fetchArray(SQLITE3_ASSOC);
  if($resArray){
    #return $result->fetch_assoc()['folioID'];
    $id = $resArray['folioID'];
    $result->finalize();
    return $id;
  }else{
    $result->finalize();
    return false;
  }
}


// Retrieves either the subscriptionList or licenseList of given org and saves result at "$path/<subscriptionList|licenseList>"
function retrieveList($path, $list){
  global $ORG_GUID;
  if(!is_dir("$path/$list")) mkdir("$path/$list");

  // To prevent cancellation of script
  set_time_limit(0);
  // If local file with lists exists load it, else create and fill it
  if(is_file("$path/$list/localList.json")){
    $metaList = json_decode(file_get_contents("$path/$list/localList.json"), true);
  }else{
    $resp = laserRequest($list, array('q' => 'laserID', 'v' => $ORG_GUID));
    $resp = json_decode($resp, true);

    // Filter out any non-local resource
    $metaList = array();
    foreach($resp as $license){
      if($license['calculatedType'] == "Local"){
        $metaList[] = $license;
      }
    }
    // Save list as file
    $fh = fopen("$path/$list/localList.json", "w");
    fwrite($fh, json_encode($metaList));
    fclose($fh);
  }

  // Create directories for each local resource and save data inside
  foreach($metaList as $entry){
    // Extract part of laserID after ":" to use as directory name
    $entryID = $entry['globalUID'] ?? $entry['laserID'];
    $uid = explode(":", $entryID)[1];
    $entryDir = "$path/$list/$uid";
    if(!is_dir($entryDir)) mkdir($entryDir);

    $entryJSON = getJsonData("$entryDir/daten.json", $entryID);

    // Check for documents
    if(isset($entryJSON['documents'])){
      foreach($entryJSON['documents'] as $document){
        if(isset($document['type']) and $document['type'] == "Note") continue;
        downloadDocument("$entryDir/", $document['filename'], $document['uuid']);
      }
    }
  }
}


// Uploads a note and connects it to resource per folioID
function uploadNote($title, $content, $type, $folioID, $okapiToken){
  $time = getTimestamp();
  error_log("[INFO $time] Uploading note \"$title\" to $folioID\n", 3, "import.log");
  if($type == "subscription") $type = "agreement";
  $curlHandler = curl_init("https://okapi.gbv.de/notes");
  curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true);
  $header = array("x-okapi-token: $okapiToken", "Content-type: application/json");
  curl_setopt($curlHandler, CURLOPT_HTTPHEADER, $header);
  curl_setopt($curlHandler, CURLOPT_POST, 1);
  $note = array();
  $note['domain'] = $type . "s";
  $note['content'] = "<p>$content</p>";
  $note['title'] = $title;
  $note['links'] = array(array("type" => $type, "id" => $folioID));

  //get ID for first note type found
  $curlHandlerNoteType = curl_init("https://okapi.gbv.de/note-types");
  curl_setopt($curlHandlerNoteType, CURLOPT_RETURNTRANSFER, true);
  $header = array("x-okapi-token: $okapiToken");
  curl_setopt($curlHandlerNoteType, CURLOPT_HTTPHEADER, $header);
  $response = json_decode(curl_exec($curlHandlerNoteType), true);
  if(!isset($response['totalRecords']) || $response['totalRecords'] == 0){
    return;
  }else{
    $note['typeId'] = $response['noteTypes'][0]['id'];
  }

  curl_setopt($curlHandler, CURLOPT_POSTFIELDS, json_encode($note));

  $response = curl_exec($curlHandler);
}

// Uploads a resource to FOLIO
function importResource($type, $path){
  global $SAVE_PATH, $status, $resourceMap;
  $time = getTimestamp();
  error_log("[INFO $time] Importing $type at $path\n", 3, "import.log");
  // Login and load resource
  $okapiToken = okapiLogin();

  # $mysqli = @new mysqli("localhost", $DB_USER, $DB_PASS, $DB_NAME);
  $db = new SQLite3('laserfolio.sqlite', SQLITE3_OPEN_READWRITE);

  $resource = json_decode(file_get_contents("$path/daten.json"), true);

  // Resource is already in FOLIO, skip
  $resourceID = $resource['globalUID'] ?? $resource['laserID'];
  $folioID = checkDatabase($db, $resourceID);
  if($folioID){
    $time = getTimestamp();
    error_log("[INFO $time] $type at $path is already imported. Skipping.\n", 3, "import.log");
    return $folioID;
  }

  $laserID = $resource['globalUID'] ?? $resource['laserID'];
  $additionalNotes = array();
  // Set data
  $data = array();
  $data['type'] = "local";
  $data['description'] = "SKRIPTIMPORT\nLAS:eR ID: $laserID";
  $startDate = $resource['startDate'] ?? "";
  $date = new DateTimeImmutable($startDate);
  $startYear = $date->format('Y');
  $endDate = $resource['endDate'] ?? "";
  $data['name'] = $type == "license" ? $resource['reference'] . " $startYear" : $resource['name'] . " $startYear";
  $data['startDate'] = $startDate;
  $data['endDate'] = $endDate;

  if($type == "subscription"){
    // Special handling for subscriptions
    if(isset($resource['resource'])){
      $data['agreementContentTypes'] = array(array("contentType" => array("value" => $resourceMap[$resource['resource']])));
    }
    $data['agreementStatus'] = $status[$resource['status']]; 

    // Set time period
    $data['periods'] = array(array("startDate" => $startDate, "endDate" => $endDate));

    // Link linceses
    if(isset($resource['licenses'])){
      // Only the first licence gets linked as current license, remainder will get notes
      $connectedCurrent = false;
      foreach($resource['licenses'] as $linkedLicense){
        $linkedLicenseID = $linkedLicense['globalUID'] ?? $linkedLicense['laserID'];
        $licenseFolioId = checkDatabase($db, $linkedLicenseID);

        if(!$licenseFolioId) continue;

        if($linkedLicense['status'] == "Current"){
          if(!$connectedCurrent){
            $data['linkedLicenses'][] = array("remoteId" => $licenseFolioId, "status" => "controlling");
            $connectedCurrent = true;
          }else{
            $data['linkedLicenses'][] = array("remoteId" => $licenseFolioId, "status" => "historical");
            $licenseDate = new DateTimeImmutable($linkedLicense['startDate']);
            $additionalNotes[] = array("title" => "Weiterer wirkender Lizenzvertrag", "content" => $linkedLicense['reference'] . " " . $licenseDate->format('Y'));
          }
        }elseif($linkedLicense['status'] == "Retired"){
          $data['linkedLicenses'][] = array("remoteId" => $licenseFolioId, "status" => "historical");
        }else{
          print($linkedLicense['status']);
          exit;
        }
      }
    }

    // Link subscriptions
    $data['inwardRelationships'] = array();
    $data['outwardRelationships'] = array();
    $time = getTimestamp();
    error_log("[INFO $time] Checking for predecessors\n", 3, "import.log");
    if(isset($resource['predecessors'])){
      $time = getTimestamp();
      error_log("[INFO $time] List is set\n", 3, "import.log");

      foreach($resource['predecessors'] as $pred){
        if(!isset($pred['calculatedType']) || $pred['calculatedType'] != "Local") continue;
        $predID = $pred['globalUID'] ?? $pred['laserID'];
        $predFolioId = checkDatabase($db, $predID);
        if(!$predFolioId) continue;
        $data['inwardRelationships'][] = array("outward" => $predFolioId, "type" => "supersedes");
      }
    }

    $time = getTimestamp();
    error_log("[INFO $time] Checking for successors\n", 3, "import.log");
    if(isset($resource['successors'])){
      $time = getTimestamp();
      error_log("[INFO $time] List is set\n", 3, "import.log");

      foreach($resource['successors'] as $succ){
        if(!isset($succ['calculatedType']) || $succ['calculatedType'] != "Local") continue;
        $succID = $succ['globalUID'] ?? $succ['laserID'];
        $succFolioId = checkDatabase($db, $succID);
        if(!$succFolioId) continue;
        $data['outwardRelationships'][] = array("inward" => $succFolioId, "type" => "supersedes");
      }
    }

    $time = getTimestamp();
    error_log("[INFO $time] Checking for linked subscriptions\n", 3, "import.log");
    if(isset($resource['linkedSubscriptions'])){
      foreach($resource['linkedSubscriptions'] as $linkedSub){
        if(!isset($linkedSub['subscription']['calculatedType']) || $linkedSub['subscription']['calculatedType'] != 'Local') continue;
        $linkedSubID = $linkedSub['subscription']['globalUID'] ?? $linkedSub['subscription']['laserID'];
        $linkedSubFolioId = checkDatabase($db, $linkedSubID);
        if(!$linkedSubFolioId) continue;
        $data['inwardRelationships'][] = array("outward" => $linkedSub, "type" => "related_to");
      }
    }
  }else{
    // License handling
    $data['status'] = $status[$resource['status']];
    // Add predecessors as Note
    if(isset($resource['predecessors'])){
      foreach($resource['predecessors'] as $pre){
        $preStartDate = new DateTimeImmutable($pre['startDate']);
        $additionalNotes[] = array("title" => "Vorgänger", "content" => $pre['reference'] . " " . $preStartDate->format('Y'));
      }
    }
    // Add successors as Note
    if(isset($resource['successors'])){
      foreach($resource['successors'] as $su){
        $suStartDate = new DateTimeImmutable($su['startDate']);
        $additionalNotes[] = array("title" => "Nachfolger", "content" => $su['reference'] . " " . $suStartDate->format('Y'));
      }
    }

    // Add linked licences as Note
    if(isset($resource['linkedLicenses'])){
      foreach($resource['linkedLicenses'] as $linked){
        $linkedStartDate = new DateTimeImmutable($linked['license']['startDate']);
        $additionalNotes[] = array("title" => "Verknüpft mit", "content" => $linked['license']['reference'] . " " . $linkedStartDate->format('Y'));
      }
    }
  }

  // Map custom Properties
  $data['customProperties'] = array();
  if(isset($resource['properties'])){
    // If no mappings have been set, redirect to mappings page
    if(!is_file("mapping.json")){
      header("Location: mapper.php");
    }else{
      // Load mapping for resource type
      $typemapping = json_decode(file_get_contents("mapping.json"), true)[$type];
    }
    foreach($resource['properties'] as $property){
      $result = array();
      if(isset($typemapping[$property['token']])){
        $map = $typemapping[$property['token']];
        switch ($map['type']) {
          case 'ignore':
            // Skip to next property
            continue 2;
          case 'text':
            $result['value'] = $property['value'] ?? "KEIN INHALT";
            break;
          case 'choice':
            $result['value'] = $map[$property['value']];
            break;
          default:
            continue 2;
        }
        // Concatenate note and paragraph to one text, FOLIO limitation
        $note = $property['note'] ?? "";
        $paragraph = $property['paragraph'] ?? "";
        $result['note'] = "$note::$paragraph";
        $data['customProperties'][$map['folioName']] = $result;
      }
    }
  }

  if(empty($data['customProperties'])){
    unset($data['customProperties']);
  }


  //Upload documents
  $dirContent = scandir("$path");
  $documentList = array();

  foreach($dirContent as $file){
    if(in_array($file, array("daten.json", ".", ".."))) continue;

    $response = uploadDocument("$path/$file", $file, $type, $okapiToken);
    $fileFolioId = $response['id'];

    $documentList[] = array("name" => $file, "fileUpload" => array("id" => $fileFolioId));
  }

  if($type == "license"){
    $data['docs'] = $documentList;
  }else{
    $data['supplementaryDocs'] = $documentList;
  }

  while($type == "subscription" && !checkAgreementName($data['name'], $okapiToken)){
    $data['name'] .= " DUPLICATE";    //Easy to find but might look ridiculous if it happens more than once
  }

  $folioResource = uploadResource($data, $type, $okapiToken);
  if(!isset($folioResource['id'])){
    $time = getTimestamp();
    error_log("[ERROR $time] Did not recieve folioID for $type at $path\n", 3, "import.log");
    print "Failed at importResource<br>";
    var_dump($folioResource);
    var_dump($data);
    exit;
  }

  // Add Notes to resource
  if(isset($resource['documents'])){
    foreach($resource['documents'] as $document){
      if(isset($document['type']) and $document['type'] == "Note"){
        $content = $document['content'] ?? "";
        uploadNote($document['title'], $content, $type, $folioResource['id'], $okapiToken);
      }
    }
  }

  // Add Note for each additional current license
  if($additionalNotes){
    foreach($additionalNotes as $note){
      uploadNote($note['title'], $note['content'], $type, $folioResource['id'], $okapiToken);
    }
  }


  // Store folioID in Database
  #$stmt = $mysqli->prepare("INSERT INTO imported(globalUID, folioID) VALUES (?, ?)");
  #$stmt->bind_param("ss", $resource['globalUID'], $folioResource['id']);
  #$stmt->execute();
  #$mysqli->close();
  
  $stmt = $db->prepare("INSERT INTO 'imported' ('globalUID', 'folioID') VALUES (:uid, :fid)");
  $stmt->bindValue(":uid", $resource['globalUID'] ?? $resource['laserID']);
  $stmt->bindValue(":fid", $folioResource['id']);
  $stmt->execute();
  $db->close();
  
  return $folioResource['id'];
}
?>