<?php
function gravaOS($conn,$tipo,$series,$mesReferencia=null,$tec='0000')
{
    $mapa=['afericao'=>['A','Aferição'],'preventiva'=>['S','Preventiva'],'revisao'=>['E','Revisão interna']];
    if(!isset($mapa[$tipo])||!$series)return 'Tipo de OS ou seleção inválida.';
    [$codigoTipo,$nomeTipo]=$mapa[$tipo]; $marcas=implode(',',array_fill(0,count($series),'?'));
    if(!sqlsrv_begin_transaction($conn))return 'Erro: não foi possível iniciar a abertura.';
    if($tipo==='revisao'){
        $sql="DECLARE @Geradas TABLE(Codigo VARCHAR(20));
        INSERT INTO TB02115(TB02115_CODIGO,TB02115_DTCAD,TB02115_NUMSERIE,TB02115_STATUS,TB02115_OPCAD,TB02115_CODTEC,TB02115_ATENDENTE,TB02115_SOLICITANTE,TB02115_DATA,TB02115_PRODUTO,TB02115_PREVENTIVA,TB02115_SITUACAO,TB02115_CODEMP,TB02115_NOME,TB02115_CONTPB,TB02115_CONTCOLOR,TB02115_CONTTOTAL)
        OUTPUT INSERTED.TB02115_CODIGO INTO @Geradas
        SELECT FORMAT(CAST(C.TB00002_COD AS INT)+ROW_NUMBER() OVER(ORDER BY E.TB02054_NUMSERIE),'000000'),GETDATE(),E.TB02054_NUMSERIE,'27','PAINEL OS MASSA',?,'PAINEL OS MASSA','PAINEL OS MASSA',GETDATE(),E.TB02054_PRODUTO,'E','A',E.TB02054_CODEMP,?,E.TB02054_MEDIDORPB,E.TB02054_MEDIDORCOLOR,E.TB02054_MEDIDORTOTAL
        FROM TB02054 E CROSS JOIN(SELECT TB00002_COD FROM TB00002 WITH(UPDLOCK,HOLDLOCK) WHERE TB00002_TABELA='TB02115')C
        WHERE E.TB02054_NUMSERIE IN($marcas) AND NOT EXISTS(SELECT 1 FROM TB02115 O WITH(UPDLOCK,HOLDLOCK) WHERE O.TB02115_NUMSERIE=E.TB02054_NUMSERIE AND O.TB02115_PREVENTIVA='E' AND O.TB02115_DTFECHA IS NULL);
        SELECT Codigo FROM @Geradas";
        $params=array_merge([$tec,$nomeTipo],$series);
    }else{
        $pendente=$tipo==='afericao'?"AND B.Possui='Não'":'';
        $sql="DECLARE @Geradas TABLE(Codigo VARCHAR(20));
        INSERT INTO TB02115(TB02115_CODIGO,TB02115_DTCAD,TB02115_NUMSERIE,TB02115_EMAIL,TB02115_CELULAR,TB02115_STATUS,TB02115_OPCAD,TB02115_CODCLI,TB02115_TIPOINTERV,TB02115_CODTEC,TB02115_ATENDENTE,TB02115_SOLICITANTE,TB02115_DATA,TB02115_PRODUTO,TB02115_PREVENTIVA,TB02115_SITUACAO,TB02115_CODEMP,TB02115_CONTRATO,TB02115_END,TB02115_NUM,TB02115_COMP,TB02115_BAIRRO,TB02115_CIDADE,TB02115_ESTADO,TB02115_CEP,TB02115_CODSITE,TB02115_NOME,TB02115_CONTPB,TB02115_CONTCOLOR,TB02115_CONTTOTAL,TB02115_LOCAL,TB02115_CODDEPTO)
        OUTPUT INSERTED.TB02115_CODIGO INTO @Geradas
        SELECT FORMAT(CAST(C.TB00002_COD AS INT)+ROW_NUMBER()OVER(ORDER BY B.Serie),'000000'),GETDATE(),B.Serie,B.EMAIL,B.FONE,'27','PAINEL OS MASSA',B.Codcli,'I',?,'PAINEL OS MASSA','PAINEL OS MASSA',GETDATE(),B.CodProd,?,'A','00',B.Contrato,B.ENDERECO,B.NUM,LEFT(B.COMP,20),B.Bairro,B.Cidade,B.Estado,B.CEP,B.CODSITE,?,L.PB,L.COR,ISNULL(L.PB,0)+ISNULL(L.COR,0),B.[local],B.coddepto
        FROM BM_CONTRLEITURA_ABRIROS B CROSS JOIN(SELECT TB00002_COD FROM TB00002 WITH(UPDLOCK,HOLDLOCK) WHERE TB00002_TABELA='TB02115')C
        OUTER APPLY(SELECT TOP 1 R.TB02117_MEDATUAL PB,R.TB02117_MEDATUALC COR FROM TB02117 R WHERE R.TB02117_NUMSERIE=B.Serie AND R.TB02117_CODIGO=B.Contrato ORDER BY R.TB02117_DATA DESC)L
        WHERE B.situacao<>'I' AND B.libleitura='N' AND B.MesFechamento=? $pendente AND B.Serie IN($marcas)
        AND NOT EXISTS(SELECT 1 FROM TB02115 O WITH(UPDLOCK,HOLDLOCK) WHERE O.TB02115_NUMSERIE=B.Serie AND O.TB02115_PREVENTIVA=? AND O.TB02115_DTFECHA IS NULL);
        SELECT Codigo FROM @Geradas";
        $params=array_merge([$tec,$codigoTipo,$nomeTipo.' - '.$mesReferencia,$mesReferencia],$series,[$codigoTipo]);
    }
    $stmt=sqlsrv_query($conn,$sql,$params);
    if($stmt===false){sqlsrv_rollback($conn);return 'Erro: OS não gravada. Nenhum registro foi alterado.';}
    do{$meta=sqlsrv_field_metadata($stmt);}while(!$meta&&sqlsrv_next_result($stmt));
    $codigos=[];while($row=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC))$codigos[]=$row['Codigo'];
    if(!$codigos){sqlsrv_rollback($conn);return 'Nenhuma OS aberta: os equipamentos já possuem OS aberta ou não atendem mais aos critérios.';}
    $qtd=count($codigos);
    if(sqlsrv_query($conn,"UPDATE TB00002 SET TB00002_COD=TB00002_COD+? WHERE TB00002_TABELA='TB02115'",[$qtd])===false){sqlsrv_rollback($conn);return 'Erro ao atualizar o contador. Nenhum registro foi alterado.';}
    $m=implode(',',array_fill(0,$qtd,'?'));
    $hist="INSERT INTO TB02130(TB02130_CODIGO,TB02130_DATA,TB02130_USER,TB02130_STATUS,TB02130_NOME,TB02130_OBS,TB02130_CODTEC,TB02130_PREVISAO,TB02130_NOMETEC,TB02130_TIPO,TB02130_CODCAD,TB02130_CODEMP,TB02130_DATAEXEC,TB02130_HORASCOM,TB02130_HORASFIM)
    SELECT O.TB02115_CODIGO,GETDATE(),'PAINEL OS MASSA',O.TB02115_STATUS,S.TB01073_NOME,O.TB02115_NOME,O.TB02115_CODTEC,NULL,T.TB01024_NOME,'O',O.TB02115_CODCLI,O.TB02115_CODEMP,GETDATE(),'00:00','00:00' FROM TB02115 O LEFT JOIN TB01073 S ON S.TB01073_CODIGO=O.TB02115_STATUS LEFT JOIN TB01024 T ON T.TB01024_CODIGO=O.TB02115_CODTEC WHERE O.TB02115_CODIGO IN($m)";
    if(sqlsrv_query($conn,$hist,$codigos)===false){sqlsrv_rollback($conn);return 'Erro ao gravar histórico. Nenhum registro foi alterado.';}
    sqlsrv_commit($conn);
    return "Sucesso: $qtd OS(s) de $nomeTipo aberta(s).\n\nNúmeros gerados: ".implode(', ',$codigos);
}
