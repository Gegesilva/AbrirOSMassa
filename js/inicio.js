let modalConfirmacao;
document.addEventListener('DOMContentLoaded', () => {
  modalConfirmacao = new bootstrap.Modal(document.getElementById('modalConfirmacao'));
  const todos = document.getElementById('selecionarTodos');
  if (todos) todos.addEventListener('change', () => document.querySelectorAll('.check-serie').forEach(c => c.checked = todos.checked));
});
function seriesSelecionadas() { return Array.from(document.querySelectorAll('.check-serie:checked')).map(c => c.value); }
function prepararAbertura() {
  const series = seriesSelecionadas();
  if (!series.length) { alert('Selecione pelo menos um equipamento liberado.'); return; }
  const nomes = { afericao:'aferição', preventiva:'preventiva', revisao:'revisão interna' };
  const tipo = document.body.dataset.tipoOs;
  document.getElementById('textoConfirmacao').innerHTML = `Confirma a abertura de <strong>${series.length} OS de ${nomes[tipo]}</strong>?<br><small>O servidor verificará novamente se cada série continua liberada.</small>`;
  modalConfirmacao.show();
}
function confirmarAbertura() {
  const botao=document.getElementById('btnConfirmar'), dados=new URLSearchParams();
  dados.append('tipo',document.body.dataset.tipoOs); dados.append('series',JSON.stringify(seriesSelecionadas()));
  const competencia=document.getElementById('mesFiltro'); if(competencia) dados.append('mes',competencia.value);
  botao.disabled=true; botao.innerHTML='<span class="spinner-border spinner-border-sm"></span> Abrindo...';
  fetch('models/processa_os.php',{method:'POST',body:dados}).then(r=>r.text()).then(texto=>{
    alert(texto); if(texto.includes('Sucesso')) location.reload(); else {botao.disabled=false;botao.textContent='Confirmar e abrir OS';}
  }).catch(()=>{alert('Erro de comunicação com o servidor.');botao.disabled=false;botao.textContent='Confirmar e abrir OS';});
}
