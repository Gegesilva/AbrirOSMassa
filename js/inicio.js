let modalSeriesAtuais = [];
let modalInstance = null;

function mostrarCarregamento(mensagem = 'Carregando...') {
    const overlay = document.getElementById('loadingOverlay');
    const texto = document.getElementById('loadingMessage');
    if (texto) texto.textContent = mensagem;
    if (overlay) {
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
    }
}

function ocultarCarregamento() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    modalInstance = new bootstrap.Modal(document.getElementById('modalTecnico'));
    document.querySelectorAll('form:not([data-no-loading])').forEach(function (formulario) {
        formulario.addEventListener('submit', function () { mostrarCarregamento('Aplicando filtros...'); });
    });
    atualizarSelecao();
});

window.addEventListener('pageshow', ocultarCarregamento);

function alternarFiltros() {
    document.querySelector('.workspace').classList.toggle('filters-collapsed');
}

function getSeriesSelecionadas() {
    return Array.from(document.querySelectorAll('.check-serie:checked')).map(function (check) {
        return check.value;
    });
}

function atualizarSelecao() {
    const disponiveis = Array.from(document.querySelectorAll('.check-serie'));
    const selecionadas = disponiveis.filter(function (check) { return check.checked; });
    const contador = document.getElementById('selectionCount');
    const botao = document.getElementById('openSelectedButton');
    const mestre = document.getElementById('checkAll');
    if (contador) contador.textContent = selecionadas.length + ' selecionado(s)';
    if (botao) botao.disabled = selecionadas.length === 0;
    if (mestre) {
        mestre.checked = disponiveis.length > 0 && selecionadas.length === disponiveis.length;
        mestre.indeterminate = selecionadas.length > 0 && selecionadas.length < disponiveis.length;
    }
}

function toggleAll(masterCheckbox) {
    document.querySelectorAll('.check-serie').forEach(function (check) {
        check.checked = masterCheckbox.checked;
    });
    atualizarSelecao();
}

function gerarOSSelecionadas(event) {
    gerarOS('', '', event);
}

function gerarOSIndividual(botao, event) {
    gerarOS('', botao.dataset.serie, event);
}

function gerarOS(contrato, serie = '', event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    modalSeriesAtuais = serie ? [serie] : getSeriesSelecionadas();
    if (!modalSeriesAtuais.length) {
        alert('Marque pelo menos um equipamento para abrir a OS.');
        return;
    }
    document.getElementById('textoModalTecnico').innerHTML =
        'Confirma a abertura de <strong>' + modalSeriesAtuais.length + ' OS</strong>?';
    modalInstance.show();
}

function confirmarGerarOS() {
    const botao = document.querySelector('#modalTecnico .modal-footer button:last-child');
    const formData = new URLSearchParams();
    const mes = document.getElementById('mesFiltro');
    formData.append('tipo', document.body.dataset.tipoOs);
    formData.append('series', JSON.stringify(modalSeriesAtuais));
    formData.append('contrato', '');
    formData.append('tecnico', document.getElementById('selectTecnico').value || '0000');
    if (mes) formData.append('mes', mes.value);

    ['cidadeFiltro', 'contratoFiltro', 'patFiltro', 'bairroFiltro', 'estadoFiltro', 'areaFiltro',
        'serieFiltro', 'modeloFiltro', 'situacaoFiltro', 'paginasDesdePreventivaFiltro'].forEach(function (id) {
        const campo = document.getElementById(id);
        formData.append(id, campo ? campo.value : '');
    });

    botao.disabled = true;
    modalInstance.hide();
    mostrarCarregamento('Gerando OS...');
    fetch('models/processa_os.php', { method: 'POST', body: formData })
        .then(function (response) { return response.text(); })
        .then(function (texto) {
            alert(texto);
            if (texto.includes('Sucesso')) location.reload();
            else {
                ocultarCarregamento();
                botao.disabled = false;
            }
        })
        .catch(function () {
            ocultarCarregamento();
            alert('Erro fatal ao comunicar com o servidor.');
            botao.disabled = false;
        });
}
