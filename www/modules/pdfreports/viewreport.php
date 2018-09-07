<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

/**
 * Configuration file
 */
$centreonConf = "@CENTREON_ETC@/centreon.conf.php";
require_once $centreonConf;

require_once "DB-Func.php";

if(isset($_REQUEST["file"])){
  // Get parameters
  $file = urldecode($_REQUEST["file"]); // Decode URL-encoded string
 
  $file = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).\/])", '', $file);
  // Remove any runs of periods (thanks falstro!)
  $file = mb_ereg_replace("([\.]{2,})", '', $file);

  $filepath = getGeneralOptInfo("pdfreports_path_gen") . $file;
                                                                      #

  if(file_exists($filepath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($filepath).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    flush(); // Flush system output buffer
    readfile($filepath);
    exit;
  } else {

    echo "file ". $filepath . " does not exist";

  }
}
?>
  
</body>
</html>