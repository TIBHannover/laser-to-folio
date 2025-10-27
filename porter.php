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
      <h2>LAS:eR Export</h2>
      <input type="submit" name="exportAll" value="Komplettexport">
      <br><br>
      <?php
        if(isset($_POST['scanForTypes'])){
          $types = array();
          $subTypes = array_column(json_decode(laserRequest("subscriptionList", array('q' => 'laserID', 'v' => $config['ORG_GUID'])), true), 'calculatedType');

          $licenseTypes = array_column(json_decode(laserRequest("licenseList", array('q' => 'laserID', 'v' => $config['ORG_GUID'])), true), 'calculatedType');

          $allTypes = array_unique(array_merge($subTypes, $licenseTypes));
          $allTypes = array_values($allTypes);

          foreach($allTypes as $type){
            echo "<input type='checkbox' id='$type' name='checkedExport[]' value='$type'>";
            echo "<label for='$type'>$type</label><br>";
          }
          echo '<input type="submit" name="exportChecked" value="Export">';
        }else{
          echo '<input type="submit" name="scanForTypes" value="Scan">';
        }
      ?>
    </div>
    <div class="col-4">
      <h2>FOLIO Import</h2>
      <?php
        $licenseTypes = array();
        $subscriptionTypes = array();
        if(is_dir($config['SAVE_PATH'] . "/licenseList")){
          $licenseTypes = scandir($config['SAVE_PATH'] . "/licenseList");
        }
        if(is_dir($config['SAVE_PATH'] . "/licenseList")){
          $subscriptionTypes = scandir($config['SAVE_PATH'] . "/subscriptionList");
        }
        $mergedtypes = array_merge($licenseTypes, $subscriptionTypes);
        $availabletypes = array();
        foreach(array_unique($mergedtypes) as $dir){
          if($dir != "." && $dir != ".."){
            $availabletypes[] = $dir;
          }
        }
        foreach($availabletypes as $type){
          echo "<input type='checkbox' id='$type' name='checkedImport[]' value='$type'>";
          echo "<label for='$type'>$type</label><br>";
        }
      ?>
      <input type="submit" name="importChecked" value="Import">
    </div>
  </div>
</form>


<?php

if(isset($_POST['exportChecked'])){
  $checked = $_POST['checkedExport'] ?? array();
  retrieveList($config['SAVE_PATH'], "subscriptionList", $_POST['checkedExport']);
  retrieveList($config['SAVE_PATH'], "licenseList", $_POST['checkedExport']);
  print "<hr><div class=\"flex-container\">Datensätze exportiert.</div>";
}

if(isset($_POST['exportAll'])){
  $types = array();
  $subTypes = array_column(json_decode(laserRequest("subscriptionList", array('q' => 'laserID', 'v' => $config['ORG_GUID'])), true), 'calculatedType');
  $licenseTypes = array_column(json_decode(laserRequest("licenseList", array('q' => 'laserID', 'v' => $config['ORG_GUID'])), true), 'calculatedType');

  $allTypes = array_unique(array_merge($subTypes, $licenseTypes));
  $allTypes = array_values($allTypes);

  retrieveList($config['SAVE_PATH'], "licenseList", $allTypes);
  retrieveList($config['SAVE_PATH'], "subscriptionList", $allTypes);
  print "<hr><div class=\"flex-container\">Verträge und Lizenzen exportiert.</div>";
}


if(isset($_POST['importChecked'])){
  $SAVE_PATH = $config['SAVE_PATH'];
  $checked = $_POST['checkedImport'] ?? array();
  $licenses = "$SAVE_PATH/licenseList/";
  $subscriptions = "$SAVE_PATH/subscriptionList/";

  $okapiToken = okapiLogin();

  foreach($checked as $importType){
    if(is_dir("$licenses/$importType")){
      foreach(scandir("$licenses$importType") as $licenseDir){
        if(!is_dir("$licenses$importType/$licenseDir")) continue;
        if(in_array($licenseDir, array(".", ".."))) continue;
        importResource("license", "$licenses$importType/$licenseDir", $okapiToken);
      }
    }

    if(is_dir("$subscriptions/$importType")){
      foreach(scandir("$subscriptions$importType") as $subscriptionDir){
        if(!is_dir("$subscriptions$importType/$subscriptionDir")) continue;
        if(in_array("$subscriptionDir", array(".", ".."))) continue;
        importResource("subscription", "$subscriptions$importType/$subscriptionDir", $okapiToken);
      }
    }
  }

  print "<hr><div class=\"flex-container\">Daten importiert.</div>";
}

?>