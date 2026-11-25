<?php
include_once __DIR__ . '/Config.php';

function getConnection()
{
    global $server, $base, $usuarioBanco, $SenhaBanco;

    $connectionInfo = array(
        'Database' => $base,
        'UID' => $usuarioBanco,
        'PWD' => $SenhaBanco,
        'CharacterSet' => 'UTF-8'
    );

    return sqlsrv_connect($server, $connectionInfo);
}

$conn = getConnection();

if ($conn === false) {
    die('Falha na conexão: ' . print_r(sqlsrv_errors(), true));
}
