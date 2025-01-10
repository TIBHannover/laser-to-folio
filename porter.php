<?php 

require_once("util.php");
require("navbar.php");

print "<link rel=\"stylesheet\" href=\"style.css\">";

if(!is_file("config.json")){
  header("Location: setup.php");
}else{
  $config = json_decode(file_get_contents("config.json"), true);
}

?>

<form method="POST">
  <div class="row">
    <div class="col-4">
      <h2>LAS:eR Export (lokal Speichern)</h2>
      <input type="submit" name="exportLicense" value="Verträge exportieren">
      <input type="submit" name="exportSub" value="Lizenzen exportieren">
      <input type="submit" name="exportAll" value="Komplettexport">
    </div>
    <div class="col-4">
      <h2>FOLIO Import</h2>
      <input type="submit" name="importAll" value="Import">
    </div>
  </div>
</form>


<?php

if(isset($_POST['exportSub'])){
  // Fetch list of local subscription
  $SAVE_PATH = $config['SAVE_PATH'];
  retrieveList($SAVE_PATH, "subscriptionList");
  print "<hr><div class=\"flex-container\">Lizenzen exportiert.</div>";
}

if(isset($_POST['exportLicense'])){
  // Fetch list of local subscriptions
  $SAVE_PATH = $config['SAVE_PATH'];
  retrieveList($SAVE_PATH, "licenseList");
  print "<hr><div class=\"flex-container\">Verträge exportiert.</div>";
}

if(isset($_POST['exportAll'])){
  $SAVE_PATH = $config['SAVE_PATH'];
  retrieveList($SAVE_PATH, "licenseList");
  retrieveList($SAVE_PATH, "subscriptionList");
  print "<hr><div class=\"flex-container\">Verträge und Lizenzen exportiert.</div>";
}

if(isset($_POST['importSub'])){
  $SAVE_PATH = $config['SAVE_PATH'];
  $subscriptions = "$SAVE_PATH/subscriptionList";

  foreach(scandir($subscriptions) as $subscriptionDir){
    if(!is_dir("$subscriptions/$subscriptionDir")) continue;
    if(in_array($subscriptionDir, array(".", ".."))) continue;
    importResource("subscription", "$subscriptions/$subscriptionDir");
  }

  print "<hr><div class=\"flex-container\">Daten importiert.</div>";
}

if(isset($_POST['importLicense'])){
  $SAVE_PATH = $config['SAVE_PATH'];
  $licenses = "$SAVE_PATH/licenseList";

  foreach(scandir($licenses) as $licenseDir){
    if(!is_dir("$licenses/$licenseDir")) continue;
    if(in_array($licenseDir, array(".", ".."))) continue;
    importResource("license", "$licenses/$licenseDir");
  }

  print "<hr><div class=\"flex-container\">Daten importiert.</div>";

}

if(isset($_POST['importAll'])){
  $SAVE_PATH = $config['SAVE_PATH'];
  $licenses = "$SAVE_PATH/licenseList";
  $subscriptions = "$SAVE_PATH/subscriptionList";

  foreach(scandir($licenses) as $licenseDir){
    if(!is_dir("$licenses/$licenseDir")) continue;
    if(in_array($licenseDir, array(".", ".."))) continue;
    importResource("license", "$licenses/$licenseDir");
  }

  foreach(scandir($subscriptions) as $subscriptionDir){
    if(!is_dir("$subscriptions/$subscriptionDir")) continue;
    if(in_array($subscriptionDir, array(".", ".."))) continue;
    importResource("subscription", "$subscriptions/$subscriptionDir");
  }

  print "<hr><div class=\"flex-container\">Daten importiert.</div>";
}

?>