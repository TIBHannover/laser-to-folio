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

  <h2>Allgemeine Abfrage</h2>
  <div class="row" style="align-content: center">
    <div class="col-6">
      <p>Zur Zeit werden folgende laserIDs unterstützt:</p>
      <ul>
        <li>license:XXXXX</li>
        <li>subscription:XXXXX</li>
      </ul>
      <input type="text" name="v" size=48>
      <input type="submit" name="request" value="UID Abfragen">
    </div>
  </div>
</form>

<?php
if(isset($_POST['request'])){
  // Simple request, output json response
  print "<hr><h2>Ausgabe:";
  if(empty($_POST['v'])){
    print "Keine laserID angegeben!<br>";
  }else{
    echo "<div class=\"flex-container\"><textarea style=\"overflow:auto;resize:none\" rows=\"30\" cols=\"150\" readonly=True>" . callAPI($_POST['v']) . "</textarea></div>";
  }
}
?>