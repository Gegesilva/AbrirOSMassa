<?php
function valorFiltro($nome, $padrao = '') { return isset($_GET[$nome]) ? trim($_GET[$nome]) : $padrao; }
function voltarCompetencia($competencia, $meses) {
    $data = DateTime::createFromFormat('!m/Y', $competencia) ?: new DateTime('first day of this month');
    $data->modify("-$meses month"); return $data->format('m/Y');
}
function executarConsulta($conn, $sql, $params) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return [[], sqlsrv_errors()];
    $dados = []; while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $dados[] = $row;
    return [$dados, null];
}

$tipoOS = isset($_GET['tipo']) && in_array($_GET['tipo'], ['afericao','preventiva','revisao'], true) ? $_GET['tipo'] : 'afericao';
$mesFiltro = valorFiltro('mesFiltro', date('m/Y')); $contratoFiltro = valorFiltro('contratoFiltro');
$bairroFiltro = valorFiltro('bairroFiltro'); $cidadeFiltro = valorFiltro('cidadeFiltro');
$estadoFiltro = valorFiltro('estadoFiltro'); $areaFiltro = valorFiltro('areaFiltro');
$serieFiltro = valorFiltro('serieFiltro'); $patFiltro = valorFiltro('patFiltro');
$situacaoFiltro = valorFiltro('situacaoFiltro'); $modeloFiltro = valorFiltro('modeloFiltro');
$competenciaUsada = $mesFiltro; $registros = []; $erroConsulta = null;

if ($tipoOS === 'revisao') {
    $sql = "SELECT E.TB02054_NUMSERIE Serie, E.TB02054_PAT Patrimonio, E.TB02054_PRODUTO CodProduto,
                P.TB01010_NOME Modelo, E.TB02054_STATUS CodSituacao, S.TB01096_NOME Situacao,
                OS.TB02115_CODIGO OSAberta, OS.TB02115_DTCAD DataOSAbertura,
                T.TB01024_NOME TecnicoOS, ST.TB01073_NOME StatusOS
            FROM TB02054 E
            LEFT JOIN TB01010 P ON P.TB01010_CODIGO=E.TB02054_PRODUTO
            LEFT JOIN TB01096 S ON S.TB01096_CODIGO=E.TB02054_STATUS
            OUTER APPLY (SELECT TOP 1 O.TB02115_CODIGO,O.TB02115_DTCAD,O.TB02115_CODTEC,O.TB02115_STATUS
                FROM TB02115 O WHERE O.TB02115_NUMSERIE=E.TB02054_NUMSERIE
                AND O.TB02115_PREVENTIVA='E' AND O.TB02115_DTFECHA IS NULL
                ORDER BY O.TB02115_DTCAD DESC,O.TB02115_CODIGO DESC) OS
            LEFT JOIN TB01024 T ON T.TB01024_CODIGO=OS.TB02115_CODTEC
            LEFT JOIN TB01073 ST ON ST.TB01073_CODIGO=OS.TB02115_STATUS
            WHERE E.TB02054_NUMSERIE IS NOT NULL
              AND (?='' OR E.TB02054_STATUS=? OR S.TB01096_NOME LIKE '%'+?+'%')
              AND (?='' OR E.TB02054_PRODUTO=? OR P.TB01010_NOME LIKE '%'+?+'%')
              AND (?='' OR E.TB02054_NUMSERIE LIKE '%'+?+'%')
            ORDER BY P.TB01010_NOME,E.TB02054_NUMSERIE";
    $params=[$situacaoFiltro,$situacaoFiltro,$situacaoFiltro,$modeloFiltro,$modeloFiltro,$modeloFiltro,$serieFiltro,$serieFiltro];
    [$registros,$erroConsulta]=executarConsulta($conn,$sql,$params);
} else {
    $codigoTipo=$tipoOS==='afericao'?'A':'S';
    $sql="SELECT B.Contrato,B.Cliente,B.Serie,B.Pat Patrimonio,B.Equipamento Modelo,B.marca Marca,
                B.Estado,B.Cidade,B.Bairro,EQ.TB02112_TIPOINTERV AreaIntervencao,
                U.TB02115_CODIGO UltimaOS,U.TB02115_DTCAD DataUltimaOS,U.TB02115_DTFECHA FechamentoUltimaOS,
                Tec.TB01024_NOME TecnicoUltimaOS,St.TB01073_NOME StatusUltimaOS,
                L.DataLeitura,L.A4Mono,L.A4Color,L.Scanner,L.A3Mono,L.A3Color,
                C.TB02115_CODIGO UltimaCorretiva,C.DataIntervencao DataCorretiva,C.Contador ContadorCorretiva,
                CASE WHEN U.TB02115_DTCAD IS NULL THEN NULL ELSE DATEDIFF(DAY,U.TB02115_DTCAD,GETDATE()) END DiasDesdePreventiva
            FROM BM_CONTRLEITURA_ABRIROS B
            INNER JOIN TB02112 EQ ON EQ.TB02112_NUMSERIE=B.Serie AND EQ.TB02112_CODIGO=B.Contrato
            OUTER APPLY (SELECT TOP 1 O.TB02115_CODIGO,O.TB02115_DTCAD,O.TB02115_DTFECHA,O.TB02115_CODTEC,O.TB02115_STATUS
                FROM TB02115 O WHERE O.TB02115_NUMSERIE=B.Serie AND O.TB02115_PREVENTIVA=?
                ORDER BY O.TB02115_DTCAD DESC,O.TB02115_CODIGO DESC) U
            LEFT JOIN TB01024 Tec ON Tec.TB01024_CODIGO=U.TB02115_CODTEC
            LEFT JOIN TB01073 St ON St.TB01073_CODIGO=U.TB02115_STATUS
            OUTER APPLY (SELECT TOP 1 X.* FROM (
                SELECT O.TB02254_DATEREADING DataLeitura,O.TB02254_A4MONO A4Mono,O.TB02254_A4COLOR A4Color,O.TB02254_SCANNER Scanner,O.TB02254_A3MONO A3Mono,O.TB02254_A3COLOR A3Color
                FROM TB02254 O WHERE O.TB02254_SERIALNUMBER=B.Serie AND O.TB02254_CONTRACT=B.Contrato
                UNION ALL SELECT R.TB02117_DATA,R.TB02117_MEDATUAL,R.TB02117_MEDATUALC,R.TB02117_MEDATUALDG,R.TB02117_MEDATUALGF,R.TB02117_MEDATUALGFC
                FROM TB02117 R WHERE R.TB02117_NUMSERIE=B.Serie AND R.TB02117_CODIGO=B.Contrato) X ORDER BY X.DataLeitura DESC) L
            OUTER APPLY (SELECT TOP 1 O.TB02115_CODIGO,I.TB02122_DATA DataIntervencao,I.TB02122_CONTADOR Contador
                FROM TB02115 O LEFT JOIN TB02122 I ON I.TB02122_NUMOS=O.TB02115_CODIGO
                WHERE O.TB02115_NUMSERIE=B.Serie AND O.TB02115_PREVENTIVA IN ('I','N')
                ORDER BY COALESCE(I.TB02122_DATA,O.TB02115_DTCAD) DESC,O.TB02115_CODIGO DESC) C
            WHERE B.situacao<>'I' AND B.libleitura='N' AND B.MesFechamento=?
              AND (?='' OR B.Contrato LIKE '%'+?+'%') AND (?='' OR B.Bairro LIKE '%'+?+'%')
              AND (?='' OR B.Cidade LIKE '%'+?+'%') AND (?='' OR B.Estado LIKE '%'+?+'%')
              AND (?='' OR EQ.TB02112_TIPOINTERV LIKE '%'+?+'%') AND (?='' OR B.Serie LIKE '%'+?+'%')
              AND (?='' OR B.Pat LIKE '%'+?+'%') AND (?='' OR B.Equipamento LIKE '%'+?+'%')
            ORDER BY B.Contrato,B.Serie";
    for($i=0;$i<120;$i++) {
        $competenciaUsada=voltarCompetencia($mesFiltro,$i);
        $params=[$codigoTipo,$competenciaUsada,$contratoFiltro,$contratoFiltro,$bairroFiltro,$bairroFiltro,$cidadeFiltro,$cidadeFiltro,$estadoFiltro,$estadoFiltro,$areaFiltro,$areaFiltro,$serieFiltro,$serieFiltro,$patFiltro,$patFiltro,$modeloFiltro,$modeloFiltro];
        [$registros,$erroConsulta]=executarConsulta($conn,$sql,$params);
        if($erroConsulta||count($registros)>0) break;
    }
}
$totais=['equipamentos'=>count($registros),'disponiveis'=>0,'bloqueadas'=>0];
foreach($registros as $r){$b=$tipoOS==='revisao'?!empty($r['OSAberta']):(!empty($r['UltimaOS'])&&$r['FechamentoUltimaOS']===null);$totais[$b?'bloqueadas':'disponiveis']++;}
