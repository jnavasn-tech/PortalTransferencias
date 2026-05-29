<?php
header('Content-Type: text/html; charset=utf-8');
  $host_name = 'db5018789145.hosting-data.io';
  $database = 'dbs14848135';
  $user_name = 'dbu3208202';
  $password = 'Banca-123.';

  $conn = new mysqli($host_name, $user_name, $password, $database);

  if ($conn->connect_error) {
    die('<p>Failed to connect to MySQL: '. $conn->connect_error .'</p>');
  } else {
    echo '<p>Connection to MySQL server successfully established.</p>';
  }
?>

