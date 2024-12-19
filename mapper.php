<?php


require('navbar.php');

print "<link rel=\"stylesheet\" href=\"style.css\">";


if(!is_file("config.json")){
  header("Location: setup.php");
}else{
  $config = json_decode(file_get_contents("config.json"), true);
}

?>

<script type="text/javascript">
  function showTable() {
    let value = document.querySelector('input[name="proptype"]:checked').value;
    console.log(value);
    let tableDiv = document.getElementById('map-table');
    if(value == "choice"){
      tableDiv.style.display = "block";
    }else{
      tableDiv.style.display = "none";
    }
  }
</script>

<?php

if(isset($_GET['type']) && $_GET['type'] == "subscription"){
  $type = "subscription";
  $propertyList = array();
  if(is_dir($config['SAVE_PATH'] . "\\subscriptionList")){
    foreach(scandir($config['SAVE_PATH'] . "\\subscriptionList") as $subscriptionDir){
      if(!is_dir($config['SAVE_PATH'] . "\\subscriptionList\\$subscriptionDir") or in_array($subscriptionDir, array(".", ".."))) continue;
      $subscription = json_decode(file_get_contents($config['SAVE_PATH'] . "\\subscriptionList\\$subscriptionDir\\daten.json"), true);
      if(isset($subscription['properties'])){
        foreach($subscription['properties'] as $property){
          if(!in_array($property['token'], $propertyList)) $propertyList[] = $property['token'];
        }
      }
    }
  }
}else{
  $type = "license";
  $propertyList = array();
  if(is_dir($config['SAVE_PATH'] . "\\licenseList")){
    foreach(scandir($config['SAVE_PATH'] . "\\licenseList") as $licenseDir){
      if(!is_dir($config['SAVE_PATH'] . "\\licenseList\\$licenseDir") or in_array($licenseDir, array(".", ".."))) continue;
      $license = json_decode(file_get_contents($config['SAVE_PATH'] . "\\licenseList\\$licenseDir\\daten.json"), true);
      if(isset($license['properties'])){
        foreach($license['properties'] as $property){
          if(!in_array($property['token'], $propertyList)) $propertyList[] = $property['token'];
        }
      }
    }
  }
}

// Set any new properties to "ignore"
if(is_file("mapping.json")){
  $mapping = json_decode(file_get_contents("mapping.json"), true);
}else{
  $mapping = array("license" => array(), "subscription" => array());
}
foreach($propertyList as $property){
  if(!isset($mapping[$type][$property])){
    $mapping[$type][$property] = array("folioName" => "", "type" => "ignore");
  }
}
file_put_contents("mapping.json", json_encode($mapping));
?>

<h1>Liste der Eigenschaften für <?php echo $type == "license" ? "Lizenzverträge" : "Vereinbarungen";?></h1>
<?php
if(isset($_GET['prop'])){

  if(isset($_POST['save'])){
    #var_dump($_POST);
    if(is_file("mapping.json")){
      $mapping = json_decode(file_get_contents("mapping.json"), true);
    }else{
      $mapping = array("license" => array(), "subscription" => array());
    }
  
    $propertyMap = array();
    foreach($_POST as $key => $value){
      if($key == "proptype"){
        $propertyMap['type'] = $value;
      }elseif($key == "folioname"){
        $propertyMap['folioName'] = $value;
      }elseif(str_starts_with($key, "laser")){
        if(empty($value) || empty($_POST[str_replace("laser", "folio", $key)])) continue;
        $propertyMap[$value] = $_POST[str_replace("laser", "folio", $key)];
      }
    }

    $mapping[$type][$_GET['prop']] = $propertyMap;
    file_put_contents("mapping.json", json_encode($mapping));
    echo "<strong>Mapping gespeichert.</strong>";
  }
  
  if(is_file("mapping.json")){
    $mapping = json_decode(file_get_contents("mapping.json"), true);
    $folioName = $mapping[$type][$_GET['prop']]['folioName'] ?? "";
    $propType = $mapping[$type][$_GET['prop']]['type'] ?? "";
  }

  $possibleValues = array();
  foreach(scandir($config['SAVE_PATH'] . "\\$type" . "List") as $resourceDir){
    if(!is_dir($config['SAVE_PATH'] . "\\$type" . "List\\$resourceDir") or in_array($resourceDir, array(".", ".."))) continue;
    $resource = json_decode(file_get_contents($config['SAVE_PATH'] . "\\$type" . "List\\$resourceDir\\daten.json"), true);
    if(isset($resource['properties'])){
      foreach($resource['properties'] as $prop){
        if($prop['token'] == $_GET['prop']){
          if(isset($prop['value']) && !in_array($prop['value'], $possibleValues)) $possibleValues[] = $prop['value'];
        }
      }
    }
  }
?>
  <a href="mapper.php?type=<?php echo $type; ?>" class="button">Zurück</a>
  <h3> Eigenschaft "<?php echo $_GET['prop'] ?>" </h3>

  <form method="POST">
    <div class="row">
      <div class="col-2">
        <p>Typ:</p>
        <input type="radio" id="ignore" name="proptype" <?php echo $propType == "ignore" ? 'checked="checked"' : ''; ?> value="ignore" onclick="showTable()">
        <label for="ignore">Ignorieren</label><br>
        <input type="radio" id="text" name="proptype" <?php echo $propType == "text" ? 'checked="checked"' : ''; ?> value="text" onclick="showTable()">
        <label for="text">Text</label><br>
        <input type="radio" id="choice" name="proptype" <?php echo $propType == "choice" ? 'checked="checked"' : ''; ?> value="choice" onclick="showTable()">
        <label for="choice">Auswahlliste</label><br>
      </div>
      <div class="col-4">
        <p>Interner Name in FOLIO:</p>
        <input type="text" id="folioname" name="folioname" <?php echo "value=\"$folioName\"" ?> >
      </div>
    </div>
    <div class="row" id="map-table" <?php echo $propType != "choice" ? 'style="display: none;"' : ''; ?> >
      <table id="mapping-table" class="col-4 mapping">
        <tr>
          <th scope="col" class="col-6 mapping">Name in LAS:eR</th>
          <th scope="col" class="col-6 mapping">Interner Name in FOLIO</th>
        </tr>
        <?php
        $i = 0;
        foreach($possibleValues as $value){
          echo '<tr>';
          echo '<td class="col-6 mapping"><input name="laser' . $i . '" type="text" readonly class="col-12" value="' . $value . '"></td> <td class="col-6 mapping"><input name="folio' . $i . '" type="text" class="col-12" value="' . htmlentities($mapping[$type][$_GET['prop']][$value] ?? "") . '"></td>';
          echo '</tr>';
          $i++;
        }
        ?>
      </table>
    </div>
    <div class="row">
      <input type="submit" name="save" value="Speichern">
    </div>
  </form>
<?php }else{ ?>
  
  
  
<a href="mapper.php?type=<?php echo $type == "license" ? "subscription" : "license"; ?>"> Wechsel zu <?php echo $type == "license" ? "Vereinbarungen" : "Lizenzverträgen"; ?></a>
<br>
<?php 
  foreach($propertyList as $property){
    echo "<a href=\"mapper.php?type=$type&prop=" . htmlentities($property) . "\"><div class=\"col-2 property\">";
    echo "<p style=\"text-align: center; vertical-align: middle;\">$property</p></div></a>";
  }
  if(empty($propertyList)){
    echo "<h2>Keine Eigenschaften für " . ($type == "license" ? "Lizenzverträge" : "Vereinbarungen") . " gefunden. Bitte sicherstellen, dass diese exportiert im angegebenen Verzeichnis liegen.</h2>";
  }
}
?>
