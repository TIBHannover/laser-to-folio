<?php
$splitName = explode("/", $_SERVER['SCRIPT_NAME']);
$filename = end($splitName);
$index = "";
$porter = "";
$mapper = "";
$setup = "";
$sync = "";

switch ($filename) {
  case 'index.php':
    $index = 'class="active"';
    break;
  case 'porter.php':
    $porter = 'class="active"';
    break;
  case 'mapper.php':
    $mapper = 'class="active"';
    break;
  case 'setup.php':
    $setup = 'class="active"';
    break;
  case 'sync.php':
    $sync = 'class="active"';
    break;
}
?>
<head><title>LAS:eR 2 FOLIO</title></head>
<div class="topnav">
  <a <?php echo $index; ?> href="index.php">LAS:eR Abfrage</a>
  <a <?php echo $porter; ?> href="porter.php">Import/Export</a>
  <a <?php echo $sync; ?> href="sync.php">Sync</a>
  <a <?php echo $mapper; ?> href="mapper.php">Eigenschaften Mapping</a>
  <a <?php echo $setup; ?> href="setup.php">Konfiguration</a>
</div>