<?php
session_start(); include '../conexaoSQL.php'; include 'gerarOS.php';
if($_SERVER['REQUEST_METHOD']!=='POST'||empty($_SESSION['login'])||!isset($_SESSION['password'])){echo 'Sessão expirada.';exit;}
$stmtUsuario=sqlsrv_query($conn,'SELECT 1 AS Autorizado FROM TB01066 WHERE TB01066_USUARIO=? AND TB01066_SENHA=?',[$_SESSION['login'],$_SESSION['password']]);
if(!$stmtUsuario||!sqlsrv_fetch_array($stmtUsuario,SQLSRV_FETCH_ASSOC)){echo 'Sessão inválida.';exit;}
$tipo=isset($_POST['tipo'])?$_POST['tipo']:''; $series=isset($_POST['series'])?json_decode($_POST['series'],true):[];
$mes=isset($_POST['mes'])?trim($_POST['mes']):null;
if(!in_array($tipo,['afericao','preventiva','revisao'],true)){echo 'Tipo de OS inválido.';exit;}
if(!is_array($series)||!$series){echo 'Nenhum equipamento selecionado.';exit;}
$series=array_values(array_unique(array_filter(array_map('trim',$series))));
echo gravaOS($conn,$tipo,$series,$mes,'0000');
