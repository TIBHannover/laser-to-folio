<?php 

require_once("util.php");
require("navbar.php");

print "<link rel=\"stylesheet\" href=\"style.css\">";

if(!is_file("config.json")){
  header("Location: setup.php");
}else{
  $config = json_decode(file_get_contents("config.json"), true);
}

if(is_file("syncsettings.json")){
  $syncsettings = json_decode(file_get_contents("syncsettings.json"), true);
}

?>

<form method="POST">
  <div class="row">
    <div class="col-4">
      <h2>Synchronisieren (Konsortiallizenzen)</h2>
      <input type="submit" name="sync" value="Synchronisieren">
      <br>Letzte Synchronisation: TIME
    </div>
    <div class="col-4">
      <h2>Einstellungen</h2><br>
      <input type="checkbox" id="participation" name="participation" value="participation">
      <label for="participation">Konsortial</label><br>
      <input type="checkbox" id="local" name="local" value="local">
      <label for="local">Lokal</label><br>
      <input type="checkbox" id="licenses" name="licenses" value="licenses">
      <label for="licenses">Lizenzverträge</label><br>
      <input type="checkbox" id="subscriptions" name="subscriptions" value="subscriptions">
      <label for="subscriptions">Vereinbarungen</label><br>
    </div>
  </div>
</form>


<?php

if(isset($_POST['sync'])){
  // Fetch list of local subscription
  $SAVE_PATH = $config['SAVE_PATH'];
  
  print "<hr><div class=\"flex-container\">Daten synchronisiert.</div>";
}

?>