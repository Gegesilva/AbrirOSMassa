<?php
session_start();
include '../conexaoSQL.php';
include 'gerarOS.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['login']) || !isset($_SESSION['password'])) {
    echo 'Sessão expirada.';
    exit;
}
$stmtUsuario = sqlsrv_query($conn, 'SELECT 1 AS Autorizado FROM TB01066 WHERE TB01066_USUARIO=? AND TB01066_SENHA=?', [$_SESSION['login'], $_SESSION['password']]);
if ($stmtUsuario === false) {
    echo exibirErroSql('Validação da sessão');
    exit;
}
if (!sqlsrv_fetch_array($stmtUsuario, SQLSRV_FETCH_ASSOC)) {
    echo 'Sessão inválida.';
    exit;
}
session_write_close();
$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
$series = isset($_POST['series']) ? json_decode($_POST['series'], true) : [];
$mes = isset($_POST['mes']) ? trim($_POST['mes']) : null;
if (!in_array($tipo, ['afericao', 'preventiva', 'revisao'], true)) {
    echo 'Tipo de OS inválido.';
    exit;
}
if (!is_array($series)) {
    $series = [];
}
$series = array_values(array_unique(array_filter(array_map('trim', $series))));
$contrato = isset($_POST['contrato']) ? trim($_POST['contrato']) : '';

if (!$series && $contrato !== '') {
    if ($tipo === 'revisao') {
        $sqlSeries = "SELECT E.TB02054_NUMSERIE AS Serie
            FROM TB02054 E
            WHERE E.TB02054_PRODUTO = ?
                AND NOT EXISTS (
                    SELECT 1
                    FROM TB02115 O
                    WHERE O.TB02115_NUMSERIE = E.TB02054_NUMSERIE
                        AND O.TB02115_PREVENTIVA = 'E'
                        AND O.TB02115_DTFECHA IS NULL
                )";
        $stmtSeries = sqlsrv_query($conn, $sqlSeries, [$contrato]);
    } else {
        $codigoTipo = $tipo === 'afericao' ? 'A' : 'S';
        $pendente = $tipo === 'afericao' ? "AND B.Possui = 'Não'" : '';
        $sqlSeries = "SELECT B.Serie
            FROM BM_CONTRLEITURA_ABRIROS B
            WHERE B.Contrato = ?
                AND B.MesFechamento = ?
                AND B.situacao <> 'I'
                AND B.libleitura = 'N'
                $pendente
                AND NOT EXISTS (
                    SELECT 1
                    FROM TB02115 O
                    WHERE O.TB02115_NUMSERIE = B.Serie
                        AND O.TB02115_PREVENTIVA = ?
                        AND O.TB02115_DTFECHA IS NULL
                )";
        $stmtSeries = sqlsrv_query($conn, $sqlSeries, [$contrato, $mes, $codigoTipo]);
    }

    if ($stmtSeries !== false) {
        while ($rowSerie = sqlsrv_fetch_array($stmtSeries, SQLSRV_FETCH_ASSOC)) {
            $series[] = $rowSerie['Serie'];
        }
    } else {
        echo exibirErroSql('Consulta de equipamentos para abertura em massa');
        exit;
    }
}

if (!$series) {
    echo 'Nenhum equipamento pendente encontrado.';
    exit;
}
$tecnico = isset($_POST['tecnico']) && $_POST['tecnico'] !== '' ? trim($_POST['tecnico']) : '0000';
echo gravaOS($conn, $tipo, $series, $mes, $tecnico);
