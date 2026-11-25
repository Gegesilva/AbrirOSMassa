let modalContratoAtual = '';
let modalSerieAtual = '';
let modalInstance = null;

document.addEventListener('DOMContentLoaded', function () {
    modalInstance = new bootstrap.Modal(document.getElementById('modalTecnico'));
});

function getSeriesSelecionadas(contrato = '') {
    return Array.from(document.querySelectorAll('.check-serie:checked')).filter(function (check) {
        return !contrato || check.dataset.contrato === contrato;
    }).map(function (check) {
        return check.value;
    });
}

function gerarOS(contrato, serie = '', event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    modalContratoAtual = contrato;
    modalSerieAtual = serie;
    const selecionadas = serie ? [serie] : getSeriesSelecionadas(contrato);

    const checkboxesAbertos = document.querySelectorAll('.check-serie[data-contrato="' + contrato + '"]').length > 0;
    if (!selecionadas.length && checkboxesAbertos) {
        alert('Nenhum equipamento selecionado. Marque pelo menos um para abrir a OS.');
        return;
    }

    document.getElementById('textoModalTecnico').innerHTML = selecionadas.length
        ? 'Confirma a abertura de <strong>' + selecionadas.length + ' OS</strong>?'
        : 'Confirma a abertura das OS pendentes deste grupo?';
    modalInstance.show();
}

function confirmarGerarOS() {
    const botao = document.querySelector('#modalTecnico .modal-footer button:last-child');
    const formData = new URLSearchParams();
    const mes = document.getElementById('mesFiltro');
    const series = modalSerieAtual ? [modalSerieAtual] : getSeriesSelecionadas(modalContratoAtual);

    formData.append('tipo', document.body.dataset.tipoOs);
    formData.append('series', JSON.stringify(series));
    formData.append('contrato', modalContratoAtual);
    formData.append('tecnico', document.getElementById('selectTecnico').value || '0000');
    if (mes) formData.append('mes', mes.value);

    botao.disabled = true;
    fetch('models/processa_os.php', { method: 'POST', body: formData })
        .then(function (response) { return response.text(); })
        .then(function (texto) {
            alert(texto);
            if (texto.includes('Sucesso')) {
                location.reload();
            } else {
                botao.disabled = false;
            }
        })
        .catch(function () {
            alert('Erro fatal ao comunicar com o servidor.');
            botao.disabled = false;
        });
}

function abrirSeries(event, contrato, linhaAtual, mes, patFiltro) {
    if (event.target.classList.contains('check-serie')) return;

    event.preventDefault();
    event.stopPropagation();
    const linhaDetalhes = linhaAtual.nextElementSibling;
    const containerConteudo = linhaDetalhes.querySelector('.series-content');

    if (linhaDetalhes.style.display === 'table-row') {
        linhaDetalhes.style.display = 'none';
        return;
    }

    document.querySelectorAll('.series-content').forEach(function (elemento) {
        elemento.parentElement.parentElement.style.display = 'none';
    });

    linhaDetalhes.style.display = 'table-row';
    if (containerConteudo.innerHTML.trim() !== '') return;

    containerConteudo.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Carregando...</div>';
    const tipo = document.body.dataset.tipoOs;
    const endpoint = tipo === 'afericao' ? 'models/buscar_series.php' : 'models/buscar_series_novos.php';
    const cidade = document.getElementById('cidadeFiltro');
    const dados = new URLSearchParams();
    dados.append('tipo', tipo);
    dados.append('grupo', contrato);
    dados.append('contrato', contrato);
    dados.append('mes', mes || '');
    dados.append('cidade', cidade ? cidade.value : '');
    dados.append('patFiltro', patFiltro || '');
    ['bairroFiltro', 'estadoFiltro', 'areaFiltro', 'serieFiltro', 'modeloFiltro', 'situacaoFiltro'].forEach(function (id) {
        const campo = document.getElementById(id) || document.querySelector('[name="' + id + '"]');
        dados.append(id, campo ? campo.value : '');
    });

    fetch(endpoint, { method: 'POST', body: dados })
        .then(function (response) { return response.text(); })
        .then(function (html) { containerConteudo.innerHTML = html; })
        .catch(function () { containerConteudo.innerHTML = '<div class="alert alert-danger">Erro de comunicação com o servidor.</div>'; });
}

function toggleAll(masterCheckbox, contrato) {
    document.querySelectorAll('.check-serie[data-contrato="' + contrato + '"]').forEach(function (check) {
        check.checked = masterCheckbox.checked;
    });
}
