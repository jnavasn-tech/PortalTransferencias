<?php
header('Content-Type: text/html; charset=utf-8');

  $host_name = 'db5018789145.hosting-data.io';
  $database = 'dbs14848135';
  $user_name = 'dbu3208202';
  $password = 'Banca-123.';

  $conn = new mysqli($host_name, $user_name, $password, $database);

  if ($conn->connect_error) {
    die('<p>Failed to connect to MySQL: '. $conn->connect_error .'</p>');
  } 
session_start();

function mk($p){ return password_hash($p, PASSWORD_BCRYPT); }

$conn->query("INSERT IGNORE INTO administradores(usuario,password) VALUES('admin', '".mk("Admin123!")."')");
$conn->query("INSERT IGNORE INTO cajeros(nombre,usuario,password,estado) VALUES('Cajero 1','cajero1','".mk("Cajero123!")."','activo')");

echo "Admin: admin / Admin123!  |  Cajero: cajero1 / Cajero123!";
