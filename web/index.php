<?php
  require('config.php');

  function conectar() {
    global $con;
    $con = mysqli_connect(SERVER, USER, PASS, DB);
    if (mysqli_connect_errno()) {
      echo "DB Error";
      exit();
    }
    mysql_select_db(DB);
  }

  function desconectar() {
    mysqli_close($con);
  }

  header('Content-type: text/plain');

  $t = $_GET['t'];
  $c = $_GET['c'];
  $r = $_GET['r'];

  if (isset($c)) {
    conectar();

    echo "Packets heard from $c:\n";
    if (isset($t)) {
      $stmt = mysqli_prepare($con, "select UNIX_TIMESTAMP(ts), data from packets where callsign = ? and ts > FROM_UNIXTIME(?) order by ts");
      mysqli_stmt_bind_param($stmt, "sd", $c, $t);
   
    } else {
      $stmt = mysqli_prepare($con, "select UNIX_TIMESTAMP(ts), data from packets where callsign = ? order by ts");
      mysqli_stmt_bind_param($stmt, "s", $c);
    }
    
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $ts, $data);
    while (mysqli_stmt_fetch($stmt)) {
      if (isset($r)) {
          echo "$data\n";
      } else {
          echo "$ts,$data\n";
      }
    }
    desconectar();

  } else {
    echo "Allowed params: (c)allsign, (t)imestamp, (r)aw";
  }
 
?>
