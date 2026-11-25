<?php
include_once __DIR__ . '/Config.php';

function detalhesErroSql($contexto = '')
{
    $erros = sqlsrv_errors(SQLSRV_ERR_ALL);
    $mensagens = [];

    if (is_array($erros)) {
        foreach ($erros as $erro) {
            $codigo = isset($erro['code']) ? $erro['code'] : '';
            $mensagem = isset($erro['message']) ? $erro['message'] : 'Erro não identificado.';
            $mensagens[] = trim($codigo . ' - ' . $mensagem, ' -');
        }
    }

    $detalhe = $mensagens ? implode(' | ', $mensagens) : 'Erro não identificado pelo driver SQL Server.';
    $prefixo = $contexto !== '' ? $contexto . ': ' : '';
    error_log('[AbrirOSMassa][SQL] ' . $prefixo . $detalhe);
    return $prefixo . $detalhe;
}

function exibirErroSql($contexto = '')
{
    return 'Erro SQL: ' . detalhesErroSql($contexto);
}

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
    die('<pre>' . htmlspecialchars(exibirErroSql('Falha na conexão'), ENT_QUOTES, 'UTF-8') . '</pre>');
}
