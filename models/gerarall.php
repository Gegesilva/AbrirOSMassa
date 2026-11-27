<?php
session_start();
ini_set('max_input_vars', 3000);
error_reporting(0);
ini_set('display_errors', '0');
include "../conexaoSQL.php"; // Ajuste o caminho da sua conexão se necessário

if (!isset($_SESSION["login"])) {
    echo "Erro: É necessário fazer login.";
    exit;
}

$mesFiltro = $_POST['mes'] ? $_POST['mes'] : null;
$tec = $_POST['tecnico'] ? $_POST['tecnico'] : null;

$cidade = $_POST['cidade'] ? $_POST['cidade'] : null;
$contrato = $_POST['contrato'] ? $_POST['contrato'] : null;

$condicaoCidade = "";
if ($cidade != "") {
    $condicaoCidade = " AND Cidade LIKE '%$cidade%' ";
}

$condicaoContrato = "";
if ($contrato != "") {
    $condicaoContrato = " AND Contrato LIKE '%$contrato%' ";
}


echo gravaOSAll($conn, $mesFiltro, $tec, $condicaoCidade, $condicaoContrato);


function gravaOSAll($conn, $mesReferencia, $tec, $cidade = null, $contrato = null)
{
    global $STATUS_AFERICAO;

    $nomeOS = "Aferição - " . $mesReferencia;

    $sqlInsert = " DECLARE @Geradas TABLE (Codigo VARCHAR(20)); ";

    $sqlInsert .= " INSERT INTO TB02115 (
            TB02115_CODIGO, 
            TB02115_DTCAD, 
            TB02115_NUMSERIE,
            TB02115_EMAIL, 
            TB02115_CELULAR, 
            TB02115_STATUS, 
            TB02115_OPCAD,
            TB02115_CODCLI, 
            TB02115_TIPOINTERV, 
            TB02115_CODTEC, 
            TB02115_ATENDENTE,
            TB02115_SOLICITANTE,
            TB02115_DATA, 
            TB02115_PRODUTO, 
            TB02115_PREVENTIVA, 
            TB02115_SITUACAO,
            TB02115_CODEMP, 
            TB02115_CONTRATO, 
            TB02115_END, 
            TB02115_NUM,
            TB02115_COMP, 
            TB02115_BAIRRO, 
            TB02115_CIDADE, 
            TB02115_ESTADO, 
            TB02115_CEP,
            TB02115_CODSITE, 
            TB02115_NOME,
            TB02115_CONTPB,
            TB02115_CONTCOLOR,
            TB02115_CONTTOTAL,
            TB02115_LOCAL, 
            TB02115_CODDEPTO
        )
        OUTPUT INSERTED.TB02115_CODIGO INTO @Geradas
        SELECT 
           FORMAT(
                (SELECT CAST(TB00002_COD AS INT) FROM TB00002 WHERE TB00002_TABELA = 'TB02115') 
                + ROW_NUMBER() OVER (ORDER BY Serie), '000000'
            ),
            GETDATE(), 
            Serie,
            EMAIL, 
            FONE, 
            ?, 
            'PAINEL OS MASSA',
            Codcli, 
            'I', 
            ?, 
            'PAINEL OS MASSA',
            'PAINEL OS MASSA',
            GETDATE(), 
            CodProd, 
            'A', 
            'A',
            '00', 
            Contrato, 
            ENDERECO, 
            NUM,
            LEFT(COMP, 20) Comp, 
            Bairro, 
            Cidade, 
            Estado, 
            CEP,
            CODSITE, 
            ?,
            CONT2117.PB,
            CONT2117.COR,
            CONT2117.PB + CONT2117.COR,
            [local],
            coddepto
        FROM BM_CONTRLEITURA_ABRIROS 

        OUTER APPLY (
                SELECT TOP 1 
                    TB02254_DATEREADING DTLEIT,
                    TB02254_A4MONO A4,
                    TB02254_A4COLOR A4C,
                    TB02254_SCANNER SCANNER,
                    TB02254_A3MONO A3,
                    TB02254_A3COLOR A3C
                FROM tb02254
                WHERE TB02254_SERIALNUMBER = BM_CONTRLEITURA_ABRIROS.Serie
                    AND TB02254_CONTRACT = BM_CONTRLEITURA_ABRIROS.Contrato
                ORDER BY TB02254_DATEREADING DESC
            ) UltimaLeitOrbix

        OUTER APPLY (
            SELECT TOP 1
                TB02117_MEDATUAL PB,
                TB02117_MEDATUALC COR
            FROM TB02117
            WHERE TB02117_NUMSERIE = BM_CONTRLEITURA_ABRIROS.Serie
                AND TB02117_CODIGO = BM_CONTRLEITURA_ABRIROS.Contrato
            ORDER BY TB02117_DTCAD DESC
        ) CONT2117

        WHERE situacao = 'A' 
            AND libleitura = 'N' 
            AND MesFechamento = ?
            AND Possui = 'Não'
            AND Serie NOT IN (
                SELECT TB02115_NUMSERIE
                FROM TB02115 
                WHERE TB02115_PREVENTIVA = 'A' 
                AND TB02115_NUMSERIE = Serie
                AND TB02115_DTFECHA IS NULL
            )

            AND (UltimaLeitOrbix.DTLEIT IS NULL OR DATEDIFF(day, UltimaLeitOrbix.DTLEIT, GETDATE()) > 5)
        
            $cidade
            $contrato;

        SELECT Codigo FROM @Geradas;
    ";

    $params = array($STATUS_AFERICAO, $tec, $nomeOS, $mesReferencia);

    $stmtInsert = sqlsrv_query($conn, $sqlInsert, $params);

    if ($stmtInsert === false) {
        return exibirErroSql('INSERT TB02115 em massa');
    }

    sqlsrv_next_result($stmtInsert);

    $osNumeros = [];
    while ($row = sqlsrv_fetch_array($stmtInsert, SQLSRV_FETCH_ASSOC)) {
        $osNumeros[] = $row['Codigo'];
    }

    $rowsAffected = count($osNumeros);

    if ($rowsAffected > 0) {
        $sqlUpdateContador = "UPDATE TB00002 SET TB00002_COD = TB00002_COD + ? WHERE TB00002_TABELA = 'TB02115'";
                              
        $stmtContador = sqlsrv_query($conn, $sqlUpdateContador, array($rowsAffected));
        if ($stmtContador === false) {
            return exibirErroSql('UPDATE do contador TB00002');
        }

        // --- INÍCIO DA GRAVAÇÃO DO HISTÓRICO ---
        $osListaParaSql = "'" . implode("', '", $osNumeros) . "'";

        $sqlHistorico = " INSERT INTO TB02130 (
                TB02130_CODIGO, 
                TB02130_DATA, 
                TB02130_USER, 
                TB02130_STATUS,
                TB02130_NOME, 
                TB02130_OBS, 
                TB02130_CODTEC, 
                TB02130_PREVISAO,
                TB02130_NOMETEC, 
                TB02130_TIPO, 
                TB02130_CODCAD, 
                TB02130_CODEMP,
                TB02130_DATAEXEC, 
                TB02130_HORASCOM, 
                TB02130_HORASFIM
            )
            SELECT 
                TB02115_CODIGO,
                GETDATE(),
                'PAINEL OS MASSA', 
                TB02115_STATUS, 
                TB01073_NOME, 
                TB02115_NOME, 
                TB02115_CODTEC,
                NULL,
                TB01024_NOME, 
                'O',
                TB02115_CODCLI,
                TB02115_CODEMP,
                GETDATE(), 
                '00:00', 
                '00:00'
            FROM TB02115
            LEFT JOIN TB01073 ON TB01073_CODIGO = TB02115_STATUS
            LEFT JOIN TB01024 ON TB01024_CODIGO = TB02115_CODTEC
            WHERE TB02115_CODIGO IN ($osListaParaSql)
        ";

        $stmtHistorico = sqlsrv_query($conn, $sqlHistorico);
        if ($stmtHistorico === false) {
            return exibirErroSql('INSERT do histórico TB02130');
        }

        // --- FIM DA GRAVAÇÃO DO HISTÓRICO ---

        $listaOS = implode(", ", $osNumeros);
        return "Sucesso: $rowsAffected OS(s) aberta(s) em massa.\n\nNúmeros gerados:\n$listaOS";
    }

    return "Nenhum equipamento pendente encontrado em nenhum contrato para o mês informado.";
}
