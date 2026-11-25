<?php
header('Content-type: text/html; charset=utf-8'); session_start();
ini_set('max_input_vars',3000); error_reporting(0); ini_set('display_errors','0');
include_once 'conexaoSQL.php';
if(empty($_SESSION['login'])||!isset($_SESSION['password'])){echo "<script>alert('É necessário fazer login!');location.href='./Login.php';</script>";exit;}
$stmtUsuario=sqlsrv_query($conn,'SELECT 1 AS Autorizado FROM TB01066 WHERE TB01066_USUARIO=? AND TB01066_SENHA=?',[$_SESSION['login'],$_SESSION['password']]);
$usuarioAutorizado=$stmtUsuario?sqlsrv_fetch_array($stmtUsuario,SQLSRV_FETCH_ASSOC):false;
if(!$usuarioAutorizado){echo "<script>alert('É necessário fazer login!');location.href='./Login.php';</script>";exit;}
include_once 'models/painel_os.php';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function dataBr($v){return $v instanceof DateTime?$v->format('d/m/Y'):h($v);}
function cont($v){return $v===null||$v===''?'0':h($v);}
$titulos=['afericao'=>'OS de Aferição','preventiva'=>'OS Preventiva','revisao'=>'OS de Revisão Interna'];
?>
<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($titulos[$tipoOS])?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="shortcut icon" href="MEDIA/logo.png"><link rel="stylesheet" href="css/style.css"></head>
<body data-tipo-os="<?=h($tipoOS)?>"><header class="header-bg"><div class="container-fluid px-4 pt-3">
<img src="MEDIA/logo.png" class="logo-left" alt="Logo"><h4 class="text-center mb-3">ABERTURA DE OS EM MASSA</h4>
<nav class="os-navigation mx-auto" aria-label="Tipos de OS">
<a href="?tipo=afericao" class="os-nav-button <?=$tipoOS==='afericao'?'active':''?>">Aferição<small>Preventiva = A</small></a>
<a href="?tipo=preventiva" class="os-nav-button <?=$tipoOS==='preventiva'?'active':''?>">Preventiva<small>Preventiva = S</small></a>
<a href="?tipo=revisao" class="os-nav-button <?=$tipoOS==='revisao'?'active':''?>">Revisão interna<small>Preventiva = E</small></a></nav>
<div class="d-flex flex-wrap justify-content-center gap-2 my-3"><span class="summary-pill">Equipamentos <strong><?=$totais['equipamentos']?></strong></span><span class="summary-pill summary-ok">Disponíveis <strong><?=$totais['disponiveis']?></strong></span><span class="summary-pill summary-block">OS abertas <strong><?=$totais['bloqueadas']?></strong></span>
<?php if($tipoOS!=='revisao'&&$competenciaUsada!==$mesFiltro):?><span class="summary-pill summary-info">Sem dados em <?=h($mesFiltro)?>; exibindo <?=h($competenciaUsada)?></span><?php endif;?></div>
<form method="GET" class="filter-panel mx-auto"><input type="hidden" name="tipo" value="<?=h($tipoOS)?>"><div class="row g-2 align-items-end">
<?php if($tipoOS!=='revisao'):?>
<div class="col-lg-2 col-md-3"><label>Competência</label><input class="form-control" id="mesFiltro" name="mesFiltro" value="<?=h($mesFiltro)?>" placeholder="MM/AAAA" required></div>
<div class="col-lg-2 col-md-3"><label>Contrato</label><input class="form-control" name="contratoFiltro" value="<?=h($contratoFiltro)?>"></div>
<div class="col-lg-2 col-md-3"><label>Bairro</label><input class="form-control" name="bairroFiltro" value="<?=h($bairroFiltro)?>"></div>
<div class="col-lg-2 col-md-3"><label>Cidade</label><input class="form-control" name="cidadeFiltro" value="<?=h($cidadeFiltro)?>"></div>
<div class="col-lg-1 col-md-3"><label>Estado</label><input class="form-control" name="estadoFiltro" value="<?=h($estadoFiltro)?>"></div>
<div class="col-lg-2 col-md-3"><label>Área de intervenção</label><input class="form-control" name="areaFiltro" value="<?=h($areaFiltro)?>"></div>
<div class="col-lg-2 col-md-3"><label>Série</label><input class="form-control" name="serieFiltro" value="<?=h($serieFiltro)?>"></div>
<div class="col-lg-2 col-md-3"><label>Patrimônio</label><input class="form-control" name="patFiltro" value="<?=h($patFiltro)?>"></div>
<?php if($tipoOS==='preventiva'):?><div class="col-lg-2 col-md-3"><label>Modelo / produção</label><input class="form-control" name="modeloFiltro" value="<?=h($modeloFiltro)?>"></div><?php endif;?>
<?php else:?>
<div class="col-md-3"><label>Situação</label><input class="form-control" name="situacaoFiltro" value="<?=h($situacaoFiltro)?>"></div><div class="col-md-4"><label>Modelo</label><input class="form-control" name="modeloFiltro" value="<?=h($modeloFiltro)?>"></div><div class="col-md-4"><label>Série</label><input class="form-control" name="serieFiltro" value="<?=h($serieFiltro)?>"></div>
<?php endif;?><div class="col-lg-1 col-md-2"><button class="btn btn-filter w-100" type="submit">Buscar</button></div></div></form></div></header>
<main class="container-fluid px-4 pb-5"><?php if($erroConsulta):?><div class="alert alert-danger">Não foi possível consultar os dados deste tipo de OS.</div><?php else:?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2"><div><h5 class="mb-0"><?=h($titulos[$tipoOS])?></h5><small class="text-muted">Selecione os equipamentos liberados para abertura em massa.</small></div><div class="d-flex gap-2 align-items-center"><label class="select-all"><input type="checkbox" id="selecionarTodos" checked> Selecionar disponíveis</label><button class="btn btn-primary px-4" type="button" onclick="prepararAbertura()">ABRIR OS SELECIONADAS</button></div></div>
<div class="card card-table overflow-hidden"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th class="text-center">Selecionar</th><th>Série / patrimônio</th><th>Modelo</th><?php if($tipoOS==='revisao'):?><th>Situação</th><?php else:?><th>Contrato / localização</th><?php endif;?><?php if($tipoOS==='preventiva'):?><th>Última leitura (5 contadores)</th><th>Última preventiva</th><th>Corretiva / instalação</th><?php endif;?><?php if($tipoOS==='afericao'):?><th>Última OS de aferição</th><?php endif;?><th>Status</th></tr></thead><tbody>
<?php foreach($registros as $r):$aberta=$tipoOS==='revisao'?$r['OSAberta']:(!empty($r['UltimaOS'])&&$r['FechamentoUltimaOS']===null?$r['UltimaOS']:null);?>
<tr class="<?=$aberta?'row-blocked':''?>"><td class="text-center"><?php if(!$aberta):?><input class="form-check-input check-serie" type="checkbox" value="<?=h($r['Serie'])?>" checked><?php else:?>—<?php endif;?></td><td><strong><?=h($r['Serie'])?></strong><br><small>Pat.: <?=h($r['Patrimonio'])?></small></td><td><?=h($r['Modelo'])?><?php if(!empty($r['Marca'])):?><br><small><?=h($r['Marca'])?></small><?php endif;?></td>
<?php if($tipoOS==='revisao'):?><td><strong><?=h($r['Situacao'])?></strong><br><small>Código: <?=h($r['CodSituacao'])?></small></td><?php else:?><td><strong><?=h($r['Contrato'])?></strong><br><small><?=h($r['Cidade'])?> / <?=h($r['Estado'])?> · <?=h($r['Bairro'])?></small></td><?php endif;?>
<?php if($tipoOS==='preventiva'):?><td><small><?=dataBr($r['DataLeitura'])?></small><div class="counter-list"><span>A4 <?=cont($r['A4Mono'])?></span><span>A4C <?=cont($r['A4Color'])?></span><span>DG <?=cont($r['Scanner'])?></span><span>A3 <?=cont($r['A3Mono'])?></span><span>A3C <?=cont($r['A3Color'])?></span></div></td><td><?=$r['UltimaOS']?'<strong>OS '.h($r['UltimaOS']).'</strong><br><small>'.dataBr($r['DataUltimaOS']).' · '.h($r['DiasDesdePreventiva']).' dias</small>':'<span class="text-muted">Sem preventiva</span>'?></td><td><?=$r['UltimaCorretiva']?'<strong>OS '.h($r['UltimaCorretiva']).'</strong><br><small>'.dataBr($r['DataCorretiva']).' · Cont. '.cont($r['ContadorCorretiva']).'</small>':'<span class="text-muted">Sem registro</span>'?></td><?php endif;?>
<?php if($tipoOS==='afericao'):?><td><?=$r['UltimaOS']?'<strong>OS '.h($r['UltimaOS']).'</strong><br><small>'.dataBr($r['DataUltimaOS']).'</small>':'<span class="text-muted">Nenhuma OS anterior</span>'?></td><?php endif;?>
<td><?php if($aberta):?><span class="badge text-bg-danger">BLOQUEADA · OS <?=h($aberta)?></span><br><small><?=h($tipoOS==='revisao'?$r['TecnicoOS']:$r['TecnicoUltimaOS'])?> · <?=h($tipoOS==='revisao'?$r['StatusOS']:$r['StatusUltimaOS'])?></small><?php elseif(!empty($r['UltimaOS'])):?><span class="badge text-bg-secondary">Última OS <?=h($r['UltimaOS'])?> encerrada</span><?php else:?><span class="badge text-bg-success">LIBERADA</span><?php endif;?></td></tr><?php endforeach;?>
<?php if(!$registros):?><tr><td colspan="9" class="text-center text-muted py-5">Nenhum equipamento encontrado com os filtros informados.</td></tr><?php endif;?></tbody></table></div></div><?php endif;?></main>
<div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Confirmar abertura</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><p id="textoConfirmacao" class="mb-0"></p></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="btnConfirmar" onclick="confirmarAbertura()">Confirmar e abrir OS</button></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="js/inicio.js"></script></body></html>
