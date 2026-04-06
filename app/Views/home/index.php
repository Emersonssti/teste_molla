<?php
declare(strict_types=1);
?>
<div class="container text-center">
    <div class="mb-3">
        <div class="brand-box shadow-sm mb-4 d-inline-flex align-items-center gap-3 px-4 py-3 justify-content-center">
            <img src="/assets/img/logo.png" alt="Agência Molla" style="height:48px;">
            <div class="text-center">
                <h1 class="fw-bold h4 mb-1 text-white">Desafio Técnico Agência Molla</h1>
            </div>
        </div>
        <p class="text-muted">Importe, analise e trate suas planilhas</p>
    </div>

    <div id="step-upload">
        <div class="upload-area rounded-4 p-5 mx-auto shadow-sm" id="btn-upload">
            <div class="icon-box-upload mb-3 shadow">
                <i class="bi bi-upload text-white fs-3"></i>
            </div>
            <h5 class="fw-bold">Arraste sua planilha aqui</h5>
            <p class="text-muted small">ou clique para selecionar um arquivo</p>
            <div class="mt-4 text-muted small"><i class="bi bi-filetype-xlsx me-2"></i>Formatos aceitos: .xlsx, .xls</div>
        </div>

        <div class="mt-3 d-flex flex-wrap justify-content-center gap-4">
            <button id="btn-dashboard" class="card-option p-4 rounded-4 shadow-sm disabled" disabled>
                <div class="icon-box-opt bg-blue shadow"><i class="bi bi-bar-chart-line text-white fs-3"></i></div>
                <h6 class="fw-bold mb-1">Gerar Relatório</h6>
                <p class="text-muted small mb-0">Visualize dashboards e gráficos</p>
            </button>
            <button id="btn-tratar" class="card-option p-4 rounded-4 shadow-sm disabled" disabled>
                <div class="icon-box-opt bg-green shadow"><i class="bi bi-file-earmark-arrow-down text-white fs-3"></i></div>
                <h6 class="fw-bold mb-1">Tratar Excel</h6>
                <p class="text-muted small mb-0">Apuração mensal e planilha para o cliente</p>
            </button>
        </div>
    </div>

    <div id="step-options" class="d-none">
        <p class="text-muted small mb-0">Arquivo carregado</p>
        <p class="fw-bold mb-5" id="uploaded-file-name">Base de Dados.xlsx</p>
        <div class="d-flex flex-wrap justify-content-center gap-4">
            <a href="/dashboard" class="card-option p-4 rounded-4 shadow-sm">
                <div class="icon-box-opt bg-blue shadow"><i class="bi bi-bar-chart-line text-white fs-3"></i></div>
                <h6 class="fw-bold mb-1">Gerar Relatório</h6>
                <p class="text-muted small mb-0">Visualize dashboards e gráficos</p>
            </a>
            <a href="/tratar" class="card-option p-4 rounded-4 shadow-sm">
                <div class="icon-box-opt bg-green shadow"><i class="bi bi-file-earmark-arrow-down text-white fs-3"></i></div>
                <h6 class="fw-bold mb-1">Tratar Excel</h6>
                <p class="text-muted small mb-0">Apuração mensal e planilha para o cliente</p>
            </a>
        </div>
    </div>
</div>

<!-- Input file oculto para upload -->
<input type="file" id="file-input" accept=".xlsx,.xls" style="display: none;">

<script src="<?= htmlspecialchars(asset('js/home.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
