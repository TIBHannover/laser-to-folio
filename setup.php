<?php


require('navbar.php');

print "<link rel=\"stylesheet\" href=\"style.css\">";

$db = new SQLite3('laserfolio.sqlite', SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);

$db->enableExceptions(true);

$db->query("CREATE TABLE IF NOT EXISTS 'imported' ( 'globalUID' VARCHAR PRIMARY KEY NOT NULL, 'folioID' VARCHAR NOT NULL)");

$db->close();

if(isset($_POST['save'])){
  // Save configuration to file
  $config = $_POST;
  unset($config['save']);
  if(isset($config['OKAPI_URL'])){
    if(str_ends_with($config['OKAPI_URL'], "/")){
      $config['OKAPI_URL'] = substr($config['OKAPI_URL'], 0, -1);
    }
  }
  file_put_contents("config.json", json_encode($config));
  
  #$mysqli = new mysqli("localhost", $_POST['DB_USER'], $_POST['DB_PASS'], $_POST['DB_NAME']);
  #$mysqli->execute_query("CREATE TABLE IF NOT EXISTS `imported`( `globalUID` varchar(225) PRIMARY KEY NOT NULL, `folioID` varchar(255) NOT NULL )");
  if(!is_dir($_POST['SAVE_PATH'])){
    mkdir($_POST['SAVE_PATH'], 0777, true);
  }
  print '<strong>Konfiguration gespeichert!</strong>';
}

$configExists = is_file("config.json");

$API_KEY = "";
$API_PASS = "";
$API_ISIL = "";
$ORG_GUID = "";
$SAVE_PATH = "";
$FOLIO_USER = "";
$FOLIO_PASS = "";
$FOLIO_TENANT = "";
$OKAPI_URL = "";

if($configExists){
  $config = json_decode(file_get_contents("config.json"), true);
  $API_KEY = $config['API_KEY'] ?? "";
  $API_PASS = $config['API_PASS'] ?? "";
  $API_ISIL = $config['API_ISIL'] ?? "";
  $ORG_GUID = $config['ORG_GUID'] ?? "";
  $SAVE_PATH = $config['SAVE_PATH'] ?? "";
  $FOLIO_USER = $config['FOLIO_USER'] ?? "";
  $FOLIO_PASS = $config['FOLIO_PASS'] ?? "";
  $FOLIO_TENANT = $config['FOLIO_TENANT'] ?? "";
  $OKAPI_URL = $config['OKAPI_URL'] ?? "";

  // Check if any entry is empty
  if(empty($API_KEY) || empty($API_PASS) || empty($API_ISIL) || empty($ORG_GUID) || empty($SAVE_PATH) || empty($FOLIO_USER) || empty($FOLIO_PASS) || empty($FOLIO_TENANT) || empty($OKAPI_URL)) {
    print '<strong style="color: red">Konfiguration unvollständig!</strong>';
  }
}else{
  print '<strong style="color: red">Konfiguration fehlt!</strong>';
}

?>


<form method="POST">
  <h1>LAS:eR 2 FOLIO - Konfigurationsseite</h1>
  <div class="row">
    <div class="col-6">
      <h2>LAS:eR Konfiguration</h2>
      <table>
        <tr>
          <td class="col-6">
            <label for="API_KEY">LAS:eR API Schlüssel</label>
          </td>
          <td class="col-6">
            <input class="col-12 config-text" type="text" name="API_KEY" id="API_KEY" value="<?php echo htmlentities($API_KEY); ?>" size=25>
          </td>
        </tr>
        <tr>
          <td class="col-6">
            <label for="API_PASS">LAS:eR API Passwort</label>
          </td>
          <td class="col-6">
            <input class="col-12 config-text" type="password" name="API_PASS" id="API_PASS" value="<?php echo htmlentities($API_PASS); ?>" size=25>
          </td>
        </tr>
        <tr>
          <td class="col-6">
            <label for="API_ISIL">ISIL der Einrichtung</label>
          </td>
          <td class="col-6">
            <input class="col-12 config-text" type="text" name="API_ISIL" id="API_ISIL" value="<?php echo htmlentities($API_ISIL); ?>" size=8>
          </td>
        </tr>
        <tr>
          <td class="col-6">
            <label for="ORG_GUID">laserID der Einrichtung</label>
          </td>
          <td class="col-6">
            <input class="col-12 config-text" placeholder="org:XXXXX" type="text" name="ORG_GUID" id="ORG_GUID" value="<?php echo htmlentities($ORG_GUID); ?>" size=36>
          </td>
        </tr>
      </table>
    </div>
    <div class="col-6">
      <h2>Speicherpfad für den Export</h2>
      <input class="col-10 config-text" type="text" name="SAVE_PATH" value="<?php echo htmlentities($SAVE_PATH); ?>" size=64>
    </div>
  </div>
  <div class="row">
    <div class="col-6">
      <h2>FOLIO Konfiguration</h2>
      <table>
        <tr>
          <td class="col-6">
            <label for="FOLIO_USER">FOLIO User</label>
          </td>
          <td class="col-6">
            <input class="col-12 config-text" type="text" id="FOLIO_USER" name="FOLIO_USER" value="<?php echo htmlentities($FOLIO_USER); ?>" size=20>
          </td>
        </tr>
        <tr>
          <td class="col-6">
            <label for="FOLIO_PASS">FOLIO Passwort</label>
          </td>
          <td class="col-6">
            <input class="col-12 config-text" type="password" id="FOLIO_PASS" name="FOLIO_PASS" value="<?php echo htmlentities($FOLIO_PASS); ?>" size=20>
          </td>
        </tr>
        <tr>
          <td class="col-6">
            <label for="FOLIO_TENANT">FOLIO Tenant</label>
          </td>
          <td class="col-6">
            <input class="col-12 config-text" type="text" id="FOLIO_TENANT" name="FOLIO_TENANT" value="<?php echo htmlentities($FOLIO_TENANT); ?>" size=20>
          </td>
        </tr>
        <tr>
          <td class="col-6">
            <label for="OKAPI_URL">Okapi URL</label>
          </td>
          <td class="col-6">
            <input class="col-12 config-text" type="text" id="OKAPI_URL" name="OKAPI_URL" value="<?php echo htmlentities($OKAPI_URL); ?>" size=20>
          </td>
        </tr>
      </table>
    </div>
  </div>
  <div class="row">
    <input type="submit" name="save" value="Speichern">
  </div>
</form>

