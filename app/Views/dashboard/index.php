<?php
declare(strict_types=1);
/** @var string $dashboardPayloadJson */
/** @var string $rawDataJson */
/** @var string $filtersJson */
?>
<style>
    :root {
        --primary-color: #FF671F;
        --primary-dark: #e0520a;
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
        --info-color: #06b6d4;
        --dark-color: #1f2937;
        --light-color: #f8fafc;
        --border-color: #e5e7eb;
        --gold: #d4a017;
    }

    body {
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        background: linear-gradient(135deg, #FF671F 0%, #e0520a 100%);
        min-height: 100vh;
        margin: 0;
    }

    .dashboard-container {
        background: white;
        border-radius: 20px 20px 0 0;
        margin-top: 16px;
        padding: 1.25rem;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        min-height: calc(100vh - 16px);
    }

    .header-section {
        background: linear-gradient(135deg, #FF671F, #e0520a);
        border-radius: 12px;
        padding: 1.15rem 1.25rem;
        margin-bottom: 1rem;
        color: white;
        box-shadow: 0 8px 24px rgba(0,0,0,0.18);
    }

    .filter-bar {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        align-items: end;
        margin-top: 0.75rem;
    }

    .filter-bar .filter-group { flex: 1; min-width: 140px; }
    .filter-bar .filter-group label { font-size: 0.72rem; margin-bottom: 2px; display: block; font-weight: 500; opacity: 0.85; }

    .filter-bar .form-select {
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 6px;
        background: rgba(255,255,255,0.12);
        color: white;
        padding: 0.3rem 0.6rem;
        font-size: 0.8rem;
        height: auto;
    }
    .filter-bar .form-select:focus { background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.5); color: white; box-shadow: 0 0 0 0.12rem rgba(255,255,255,0.2); }
    .filter-bar .form-select option { background: white; color: #333; }

    .kpi-card {
        background: white;
        border-radius: 10px;
        padding: 0.9rem 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border: 1px solid var(--border-color);
        transition: transform 0.2s, box-shadow 0.2s;
        height: 100%;
    }
    .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.12); }
    .metric-label { font-size: 0.78rem; color: #6b7280; font-weight: 500; margin-bottom: 0.25rem; }
    .metric-value { font-size: 1.55rem; font-weight: 700; color: var(--dark-color); line-height: 1.2; }
    .kpi-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; }

    .chart-card {
        background: white;
        border-radius: 10px;
        padding: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border: 1px solid var(--border-color);
        margin-bottom: 1rem;
    }
    .chart-title { font-weight: 600; color: var(--dark-color); margin-bottom: 0.5rem; font-size: 0.9rem; display: flex; align-items: center; justify-content: space-between; }
    .chart-title-text { display: flex; align-items: center; gap: 0.4rem; }
    .chart-container { position: relative; height: 320px; }

    /* Audit Grid */
    .audit-section { margin-top: 0.5rem; }
    .audit-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem; }
    .audit-header h5 { font-size: 1rem; font-weight: 700; color: var(--dark-color); margin: 0; }
    .audit-search { display: flex; gap: 0.5rem; align-items: center; }
    .audit-search input {
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 0.4rem 0.75rem;
        font-size: 0.82rem;
        width: 240px;
        outline: none;
        transition: border-color 0.2s;
    }
    .audit-search input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(255,103,31,0.15); }

    .audit-table-wrap { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border-color); }
    .audit-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    .audit-table thead th {
        background: #f8fafc;
        color: #374151;
        font-weight: 600;
        padding: 0.6rem 0.75rem;
        text-align: left;
        border-bottom: 2px solid var(--border-color);
        white-space: nowrap;
        position: sticky;
        top: 0;
        cursor: pointer;
        user-select: none;
    }
    .audit-table thead th:hover { background: #f1f5f9; }
    .audit-table tbody td {
        padding: 0.5rem 0.75rem;
        border-bottom: 1px solid #f3f4f6;
        color: #374151;
    }
    .audit-table tbody tr:hover { background: #fef3ec; }
    .audit-table tbody tr.row-elegivel { background: rgba(16,185,129,0.04); }
    .audit-table tbody tr.row-elegivel:hover { background: rgba(16,185,129,0.08); }

    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 0.2rem 0.55rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .badge-elegivel { background: rgba(16,185,129,0.1); color: #059669; }
    .badge-inelegivel { background: rgba(239,68,68,0.1); color: #dc2626; }

    .audit-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; font-size: 0.78rem; color: #6b7280; }

    /* Rules Modal */
    .btn-rules {
        background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.4); color: white;
        padding: 0.35rem 0.85rem; border-radius: 8px; font-size: 0.8rem; font-weight: 500;
        cursor: pointer; transition: all 0.25s; backdrop-filter: blur(6px);
    }
    .btn-rules:hover { background: rgba(255,255,255,0.25); border-color: rgba(255,255,255,0.7); }

    .rules-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:9999; justify-content:center; align-items:center; padding:1rem; }
    .rules-modal-overlay.active { display:flex; }
    .rules-modal { background:white; border-radius:16px; max-width:680px; width:100%; max-height:85vh; overflow-y:auto; box-shadow:0 25px 60px rgba(0,0,0,0.3); animation:modalSlideIn 0.3s ease; }
    @keyframes modalSlideIn { from{opacity:0;transform:translateY(30px) scale(0.97)} to{opacity:1;transform:translateY(0) scale(1)} }
    .rules-modal-header { background:linear-gradient(135deg,#FF671F,#e0520a); color:white; padding:1rem 1.25rem; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; }
    .rules-modal-header h3 { margin:0; font-size:1.05rem; font-weight:700; }
    .rules-modal-close { background:rgba(255,255,255,0.2); border:none; color:white; width:30px; height:30px; border-radius:8px; font-size:1rem; cursor:pointer; display:flex; align-items:center; justify-content:center; }
    .rules-modal-close:hover { background:rgba(255,255,255,0.35); }
    .rules-modal-body { padding:1.25rem; }
    .rule-block { margin-bottom:1rem; padding:0.85rem; background:#f8fafc; border-radius:8px; border-left:4px solid #FF671F; }
    .rule-block:last-child { margin-bottom:0; }
    .rule-block h5 { font-size:0.88rem; font-weight:700; color:#1f2937; margin:0 0 0.35rem; }
    .rule-block p,.rule-block li { font-size:0.82rem; color:#4b5563; margin:0; line-height:1.55; }
    .rule-block ul { padding-left:1.1rem; margin:0.25rem 0 0; }
    .premio-table { width:100%; border-collapse:separate; border-spacing:0; font-size:0.78rem; margin-top:0.4rem; border-radius:8px; overflow:hidden; }
    .premio-table th { background:#FF671F; color:white; padding:0.4rem 0.5rem; text-align:center; font-weight:600; }
    .premio-table td { padding:0.35rem 0.5rem; text-align:center; border-bottom:1px solid #e5e7eb; }
    .premio-table tr:nth-child(even) td { background:#f1f5f9; }

    /* AI Analysis Modal */
    .ai-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(5px); z-index:10000; justify-content:center; align-items:center; padding:1rem; }
    .ai-modal-overlay.active { display:flex; }
    .ai-modal { background:white; border-radius:16px; max-width:780px; width:100%; max-height:88vh; overflow-y:auto; box-shadow:0 30px 80px rgba(0,0,0,0.35); animation:modalSlideIn 0.35s ease; }
    .ai-modal-header {
        background: linear-gradient(135deg, #1a1a2e, #16213e);
        color: white; padding:1.15rem 1.5rem; border-radius:16px 16px 0 0;
        display:flex; justify-content:space-between; align-items:center;
    }
    .ai-modal-header h3 { margin:0; font-size:1.1rem; font-weight:700; display:flex; align-items:center; gap:0.5rem; }
    .ai-modal-body { padding:1.5rem; font-size:0.88rem; line-height:1.7; color:#1f2937; }
    .ai-modal-body h1,.ai-modal-body h2,.ai-modal-body h3,.ai-modal-body h4 { color:#1f2937; margin:1.2rem 0 0.5rem; }
    .ai-modal-body h2 { font-size:1.05rem; }
    .ai-modal-body h3 { font-size:0.95rem; }
    .ai-modal-body ul,.ai-modal-body ol { padding-left:1.4rem; margin:0.4rem 0; }
    .ai-modal-body li { margin-bottom:0.3rem; }
    .ai-modal-body strong { color:#111827; }
    .ai-modal-body code { background:#f1f5f9; padding:0.15rem 0.4rem; border-radius:4px; font-size:0.82rem; }
    .ai-modal-body blockquote { border-left:3px solid #FF671F; margin:0.75rem 0; padding:0.5rem 1rem; background:#fff7ed; border-radius:0 8px 8px 0; }

    .ai-loading { text-align:center; padding:3rem 1rem; }
    .ai-loading .spinner { width:40px; height:40px; border:3px solid #e5e7eb; border-top-color:#FF671F; border-radius:50%; animation:spin 0.8s linear infinite; margin:0 auto 1rem; }
    @keyframes spin { to{transform:rotate(360deg)} }
    .ai-loading p { color:#6b7280; font-size:0.88rem; }

    .ai-summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.75rem; margin-bottom:1.25rem; }
    .ai-summary-card { background:#f8fafc; border-radius:10px; padding:0.75rem 1rem; border:1px solid #e5e7eb; }
    .ai-summary-card .label { font-size:0.72rem; color:#6b7280; font-weight:500; text-transform:uppercase; letter-spacing:0.5px; }
    .ai-summary-card .val { font-size:1.15rem; font-weight:700; color:#1f2937; margin-top:0.15rem; }

    @media (max-width: 768px) {
        .dashboard-container { margin-top:0; border-radius:0; padding:0.75rem; }
        .header-section { padding:1rem; margin-bottom:0.75rem; }
        .chart-container { height:260px; }
        .metric-value { font-size:1.25rem; }
        .filter-bar { flex-direction: column; }
        .filter-bar .filter-group { min-width: 100%; }
        .audit-search input { width: 180px; }
    }
</style>

<div class="dashboard-container">
    <!-- Header -->
    <div class="header-section">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h4 mb-0 fw-bold">Dashboard de Campanha Promocional</h1>
                <small class="opacity-75">Agência Molla — Programa de Incentivo</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn-rules" onclick="requestAIAnalysis()" id="btn-ai-analysis">
                    <i class="bi bi-stars me-1"></i> Análise IA
                </button>
                <button class="btn-rules" onclick="document.getElementById('rules-modal').classList.add('active')">
                    <i class="bi bi-book me-1"></i> Regras
                </button>
                <div class="text-end" style="font-size:0.78rem">
                    <div class="fw-bold"><?php echo date('d/m/Y'); ?></div>
                    <small class="opacity-75">Atualização</small>
                </div>
            </div>
        </div>
        <!-- Filters always visible -->
        <div class="filter-bar">
            <div class="filter-group">
                <label>Período</label>
                <select class="form-select" id="filter-periodo"><option value="">Todos</option></select>
            </div>
            <div class="filter-group">
                <label>Categoria</label>
                <select class="form-select" id="filter-categoria"><option value="">Todas</option></select>
            </div>
            <div class="filter-group">
                <label>Grupo</label>
                <select class="form-select" id="filter-grupo"><option value="">Todos</option></select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select class="form-select" id="filter-status">
                    <option value="">Todos</option>
                    <option value="Elegível">Elegíveis</option>
                    <option value="Inelegível">Inelegíveis</option>
                </select>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row g-2 mb-3" id="kpi-container"></div>

    <!-- Charts 2x2 grid -->
    <div class="row g-2">
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="chart-title">
                    <div class="chart-title-text"><i class="bi bi-people-fill text-primary"></i> Desempenho dos Distribuidores</div>
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.72rem" onclick="downloadDistributorsCSV()"><i class="bi bi-download"></i> CSV</button>
                </div>
                <div class="chart-container"><canvas id="chart-distributors-performance"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="chart-title">
                    <div class="chart-title-text"><i class="bi bi-bar-chart-fill text-success"></i> Meta x Realizado — Categorias</div>
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.72rem" onclick="downloadCategoriesCSV()"><i class="bi bi-download"></i> CSV</button>
                </div>
                <div class="chart-container"><canvas id="chart-categories-performance"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-title">
                    <div class="chart-title-text"><i class="bi bi-trophy-fill text-warning"></i> Ranking Top 10</div>
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.72rem" onclick="downloadRankingCSV()"><i class="bi bi-download"></i> CSV</button>
                </div>
                <div class="chart-container"><canvas id="chart-distributor-ranking"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-title">
                    <div class="chart-title-text"><i class="bi bi-diagram-3-fill text-info"></i> Mix Categorias Foco</div>
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.72rem" onclick="downloadRadarCSV()"><i class="bi bi-download"></i> CSV</button>
                </div>
                <div class="chart-container"><canvas id="chart-categories-mix"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-title">
                    <div class="chart-title-text"><i class="bi bi-graph-up text-secondary"></i> Histórico de Performance</div>
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.72rem" onclick="downloadHistoricoCSV()"><i class="bi bi-download"></i> CSV</button>
                </div>
                <div class="chart-container"><canvas id="chart-performance-history"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Audit Grid -->
    <div class="audit-section">
        <div class="chart-card">
            <div class="audit-header">
                <h5><i class="bi bi-table me-2"></i>Grid de Auditoria — Apuração por Distribuidor</h5>
                <div class="audit-search">
                    <input type="text" id="grid-search" placeholder="Buscar distribuidor..." oninput="filterGrid()">
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.72rem" onclick="downloadGridCSV()"><i class="bi bi-download"></i> CSV</button>
                </div>
            </div>
            <div class="audit-table-wrap" style="max-height:420px;overflow-y:auto">
                <table class="audit-table" id="audit-table">
                    <thead>
                        <tr>
                            <th onclick="sortGrid('nome')">Distribuidor <i class="bi bi-arrow-down-up" style="font-size:.65rem;opacity:.4"></i></th>
                            <th onclick="sortGrid('grupo')">Grupo <i class="bi bi-arrow-down-up" style="font-size:.65rem;opacity:.4"></i></th>
                            <th onclick="sortGrid('volume_percentual')">% Volume <i class="bi bi-arrow-down-up" style="font-size:.65rem;opacity:.4"></i></th>
                            <th onclick="sortGrid('positivacao_percentual')">% Positivação <i class="bi bi-arrow-down-up" style="font-size:.65rem;opacity:.4"></i></th>
                            <th onclick="sortGrid('categorias_positivacao_foco_100')">Cat. Foco 100% <i class="bi bi-arrow-down-up" style="font-size:.65rem;opacity:.4"></i></th>
                            <th onclick="sortGrid('elegivel')">Status <i class="bi bi-arrow-down-up" style="font-size:.65rem;opacity:.4"></i></th>
                            <th onclick="sortGrid('premiacao')">Prêmio (R$) <i class="bi bi-arrow-down-up" style="font-size:.65rem;opacity:.4"></i></th>
                        </tr>
                    </thead>
                    <tbody id="audit-tbody"></tbody>
                </table>
            </div>
            <div class="audit-footer">
                <span id="grid-count"></span>
                <span id="grid-summary"></span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Regras -->
<div class="rules-modal-overlay" id="rules-modal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="rules-modal">
        <div class="rules-modal-header">
            <h3><i class="bi bi-book me-2"></i>Regras do Programa de Incentivo</h3>
            <button class="rules-modal-close" onclick="document.getElementById('rules-modal').classList.remove('active')">&times;</button>
        </div>
        <div class="rules-modal-body">
            <div class="rule-block">
                <h5>1. Período do Programa</h5>
                <p>Início em 01/09/2024 e término em 31/08/2025.</p>
            </div>
            <div class="rule-block">
                <h5>2. Indicadores de Meta</h5>
                <ul>
                    <li><strong>Volume sell-out total</strong> — apuração mensal, trimestral e aceleradores</li>
                    <li><strong>Volume Categoria-Foco</strong> — apuração trimestral</li>
                    <li><strong>Positivação Categoria-Foco</strong> — apuração mensal e trimestral</li>
                    <li><strong>Positivação Categoria-Foco Top Clientes</strong> — apuração trimestral (último mês do trimestre)</li>
                </ul>
            </div>
            <div class="rule-block">
                <h5>3. Mecânica "Bateu, Levou" (Mensal)</h5>
                <p>Para ser <strong>elegível</strong>, o distribuidor precisa atingir:</p>
                <ul>
                    <li><strong>100%</strong> do somatório de volume das categorias</li>
                    <li>Positivação foco: <strong>100% no total</strong> e em ao menos <strong>2 categorias com 100%</strong></li>
                </ul>
            </div>
            <div class="rule-block">
                <h5>4. Aceleradores</h5>
                <p>Todo mês uma categoria é escolhida como acelerador, recebendo premiação extra de <strong>20%</strong> sobre o prêmio mensal pelo atingimento do volume.</p>
            </div>
            <div class="rule-block">
                <h5>5. Tabela de Premiação</h5>
                <table class="premio-table">
                    <thead><tr><th>Premiação</th><th>G1</th><th>G2</th><th>G3</th><th>G4</th><th>G5</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Bateu, Levou</strong></td><td>R$ 6.000</td><td>R$ 5.000</td><td>R$ 3.000</td><td>R$ 2.000</td><td>R$ 1.000</td></tr>
                        <tr><td><strong>Acelerador 20%</strong></td><td>R$ 1.200</td><td>R$ 1.000</td><td>R$ 600</td><td>R$ 400</td><td>R$ 200</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- AI Analysis Modal -->
<div class="ai-modal-overlay" id="ai-modal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="ai-modal">
        <div class="ai-modal-header">
            <h3><i class="bi bi-stars"></i> Análise Estratégica — Campanha</h3>
            <button class="rules-modal-close" onclick="document.getElementById('ai-modal').classList.remove('active')">&times;</button>
        </div>
        <div class="ai-modal-body" id="ai-modal-body">
            <div class="ai-loading" id="ai-loading">
                <div class="spinner"></div>
                <p>Processando dados e gerando análise com IA...</p>
                <p style="font-size:0.75rem;color:#9ca3af">Consolidando KPIs da campanha e gerando análise com IA...</p>
            </div>
            <div id="ai-content" style="display:none"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script type="application/json" id="dashboard-payload"><?php echo $dashboardPayloadJson ?? '{}'; ?></script>
<script type="application/json" id="raw-data"><?php echo $rawDataJson ?? '[]'; ?></script>
<script type="application/json" id="filters-data"><?php echo $filtersJson ?? '{}'; ?></script>
<script type="application/json" id="radar-data"><?php echo $radarDataJson ?? '[]'; ?></script>
<script type="application/json" id="historico-data"><?php echo $historicoDataJson ?? '[]'; ?></script>
<script src="<?php echo htmlspecialchars(asset('js/dashboard.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
