const GROUP_PAYOUT = { 1: 6000, 2: 5000, 3: 3000, 4: 2000, 5: 1000 };

let rawData = [];
let filtersData = {};
let currentFilters = {};
let charts = {};
let radarData = [];
let historicoData = [];
let lastProcessedData = null;
let gridSortField = 'nome';
let gridSortAsc = true;

function jsonFromElement(id, fallback) {
    const el = document.getElementById(id);
    if (!el || !el.textContent.trim()) return fallback;
    try { const d = JSON.parse(el.textContent); return d ?? fallback; } catch { return fallback; }
}

function getPremiacao(grupo) { return GROUP_PAYOUT[grupo] || 0; }

function fmtBR(n, decimals) { return (n || 0).toLocaleString('pt-BR', { minimumFractionDigits: decimals ?? 0, maximumFractionDigits: decimals ?? 0 }); }

// ── Bootstrap ──
document.addEventListener('DOMContentLoaded', () => {
    rawData = jsonFromElement('raw-data', []);
    if (!Array.isArray(rawData)) rawData = [];
    filtersData = jsonFromElement('filters-data', {});
    radarData = jsonFromElement('radar-data', []);
    historicoData = jsonFromElement('historico-data', []);

    initFilters();
    initCharts();
    applyFilters();
});

// ── Filters ──
function initFilters() {
    populateSelect('filter-periodo', (filtersData.periods || []).map(p => ({ value: p, label: p })), 'Todos');
    populateSelect('filter-categoria', (filtersData.categories || []).map(c => ({ value: c, label: c })), 'Todas');
    populateSelect('filter-grupo', (filtersData.groups || []).map(g => ({ value: g, label: 'Grupo ' + g })), 'Todos');

    ['filter-periodo', 'filter-categoria', 'filter-grupo', 'filter-status'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', applyFilters);
    });

    const periodoEl = document.getElementById('filter-periodo');
    if (periodoEl) periodoEl.addEventListener('change', clearAIAnalysis);
}

function populateSelect(id, options, allLabel) {
    const sel = document.getElementById(id);
    if (!sel) return;
    sel.innerHTML = `<option value="">${allLabel}</option>`;
    options.forEach(o => { const opt = document.createElement('option'); opt.value = o.value; opt.textContent = o.label; sel.appendChild(opt); });
}

// ── Apply Filters (single entry point) ──
function applyFilters() {
    currentFilters = {
        periodo: document.getElementById('filter-periodo')?.value || '',
        categoria: document.getElementById('filter-categoria')?.value || '',
        grupo: document.getElementById('filter-grupo')?.value || '',
        status: document.getElementById('filter-status')?.value || '',
    };

    let filtered = [...rawData];
    if (currentFilters.periodo) filtered = filtered.filter(r => (r.ano + '-' + String(r.mes || 0).padStart(2, '0')) === currentFilters.periodo);
    if (currentFilters.categoria) filtered = filtered.filter(r => r.categoryName === currentFilters.categoria);
    if (currentFilters.grupo) filtered = filtered.filter(r => String(r.grupo) === String(currentFilters.grupo));

    const processed = processFilteredData(filtered);
    if (currentFilters.status) {
        const isE = currentFilters.status === 'Elegível';
        processed.distributors = processed.distributors.filter(d => d.elegivel === isE);
    }

    lastProcessedData = processed;
    renderKPIs(calculateKPIs(processed.distributors));
    updateCharts(processed);
    renderAuditGrid(processed.distributors);
}

// ── Data Processing ──
function processFilteredData(filteredData) {
    const distributors = {};
    const categories = {};
    const monthlyPerformance = {};

    filteredData.forEach(row => {
        const distId = row.distributorId;
        const catName = row.categoryName;
        const periodKey = row.ano + '-' + String(row.mes || 0).padStart(2, '0');

        if (!distributors[distId]) {
            distributors[distId] = {
                id: distId, cnpj: row.distributorCnpj, nome: row.distributorName, grupo: row.grupo,
                total_volume_meta: 0, total_volume_realizado: 0,
                positivacao_foco_meta: 0, positivacao_foco_realizado: 0,
                categorias_foco: [], categorias_positivacao_foco: [], premiacao: 0,
            };
        }
        if (!categories[catName]) {
            categories[catName] = { nome: catName, foco: row.isFocusCategory, total_meta: 0, total_realizado: 0, distribuidores: [] };
        }

        if (row.kpiType === 'TOTAL_VOLUME') {
            distributors[distId].total_volume_meta += row.meta || 0;
            distributors[distId].total_volume_realizado += row.realizado || 0;
            categories[catName].total_meta += row.meta || 0;
            categories[catName].total_realizado += row.realizado || 0;
            if (!monthlyPerformance[periodKey]) monthlyPerformance[periodKey] = { periodo: periodKey, volume_meta: 0, volume_realizado: 0 };
            monthlyPerformance[periodKey].volume_meta += row.meta || 0;
            monthlyPerformance[periodKey].volume_realizado += row.realizado || 0;
        }

        if (row.kpiType === 'CATEGORY_POSITIVATION_FOCUS' && row.isFocusCategory) {
            distributors[distId].positivacao_foco_meta += row.meta || 0;
            distributors[distId].positivacao_foco_realizado += row.realizado || 0;
            if (!distributors[distId].categorias_foco.includes(catName)) distributors[distId].categorias_foco.push(catName);
            distributors[distId].categorias_positivacao_foco.push({ categoria: catName, meta: row.meta || 0, realizado: row.realizado || 0 });
        }

        if (!categories[catName].distribuidores.includes(distId)) categories[catName].distribuidores.push(distId);
    });

    Object.values(distributors).forEach(dist => {
        dist.categorias_foco = [...new Set(dist.categorias_foco)];
        dist.volume_percentual = dist.total_volume_meta > 0 ? (dist.total_volume_realizado / dist.total_volume_meta) * 100 : 0;
        dist.positivacao_percentual = dist.positivacao_foco_meta > 0 ? (dist.positivacao_foco_realizado / dist.positivacao_foco_meta) * 100 : 0;

        const porCat = {};
        dist.categorias_positivacao_foco.forEach(i => {
            if (!porCat[i.categoria]) porCat[i.categoria] = { meta: 0, realizado: 0 };
            porCat[i.categoria].meta += i.meta;
            porCat[i.categoria].realizado += i.realizado;
        });
        dist.categorias_positivacao_foco_100 = Object.values(porCat).reduce((c, a) => c + ((a.meta > 0 && (a.realizado / a.meta) >= 1.0) ? 1 : 0), 0);
        dist.elegivel = dist.volume_percentual >= 100 && dist.positivacao_percentual >= 100 && dist.categorias_positivacao_foco_100 >= 2;
        dist.premiacao = dist.elegivel ? getPremiacao(dist.grupo) : 0;
    });

    Object.values(categories).forEach(cat => { cat.percentual = cat.total_meta > 0 ? (cat.total_realizado / cat.total_meta) * 100 : 0; });

    const radarResult = Object.values(categories).filter(c => c.foco).map(c => ({ categoria: c.nome, atingimento: Math.round((c.percentual || 0) * 10) / 10 }));
    const histResult = Object.values(monthlyPerformance).sort((a, b) => a.periodo.localeCompare(b.periodo)).map(i => ({ ...i, percentual: i.volume_meta > 0 ? (i.volume_realizado / i.volume_meta) * 100 : 0 }));

    return { distributors: Object.values(distributors), categories: Object.values(categories), radarData: radarResult, historicoData: histResult };
}

// ── KPIs (5 cards) ──
function calculateKPIs(distributors) {
    const total = distributors.length;
    const elegiveis = distributors.filter(d => d.elegivel).length;
    const payout = distributors.reduce((s, d) => s + (d.premiacao || 0), 0);
    const volMeta = distributors.reduce((s, d) => s + (d.total_volume_meta || 0), 0);
    const volReal = distributors.reduce((s, d) => s + (d.total_volume_realizado || 0), 0);
    const ating = volMeta > 0 ? (volReal / volMeta) * 100 : 0;
    const taxa = total > 0 ? (elegiveis / total) * 100 : 0;

    return [
        { title: 'Participantes', value: String(total), icon: 'bi bi-people-fill', color: 'primary' },
        { title: 'Elegíveis', value: String(elegiveis), icon: 'bi bi-check-circle-fill', color: 'success', sub: taxa.toFixed(1) + '% do total' },
        { title: 'Payout Total', value: 'R$ ' + fmtBR(payout, 2), icon: 'bi bi-cash-stack', color: 'warning' },
        { title: 'Atingimento Médio', value: ating.toFixed(1) + '%', icon: 'bi bi-speedometer2', color: 'info' },
        { title: 'Taxa de Conversão', value: taxa.toFixed(1) + '%', icon: 'bi bi-trophy-fill', color: 'danger', sub: elegiveis + ' de ' + total },
    ];
}

function renderKPIs(kpis) {
    const c = document.getElementById('kpi-container');
    if (!c) return;
    c.innerHTML = kpis.map(k => `
        <div class="col">
            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="metric-label">${k.title}</div>
                        <div class="metric-value">${k.value}</div>
                        ${k.sub ? `<div style="font-size:.72rem;color:#9ca3af">${k.sub}</div>` : ''}
                    </div>
                    <div class="kpi-icon bg-${k.color} bg-opacity-10"><i class="${k.icon}" style="color:#fff"></i></div>
                </div>
            </div>
        </div>
    `).join('');
}

// ── Charts Init ──
function initCharts() {
    Object.values(charts).forEach(c => { if (c) c.destroy(); });
    charts = {};
    initScatterChart();
    initCategoriesChart();
    initRankingChart();
    initRadarChart();
    initHistoryChart();
}

function updateCharts(data) {
    updateScatterChart(data.distributors);
    updateCategoriesChart(data.categories);
    updateRankingChart(data.distributors);
    updateRadarChart(data.radarData);
    updateHistoryChart(data.historicoData);
}

// ── 1. Scatter (Desempenho) ──
function initScatterChart() {
    const ctx = document.getElementById('chart-distributors-performance');
    if (!ctx) return;
    charts.scatter = new Chart(ctx, {
        type: 'scatter',
        data: { datasets: [{ label: 'Distribuidores', data: [],
            backgroundColor: ctx2 => { const p = ctx2.raw; return p && p.elegivel ? '#10b981' : '#ef4444'; },
            borderColor: ctx2 => { const p = ctx2.raw; return p && p.elegivel ? '#059669' : '#dc2626'; },
            borderWidth: 2, pointRadius: 7, pointHoverRadius: 11
        }]},
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(31,41,55,0.95)', titleFont: { size: 12, weight: 'bold' }, bodyFont: { size: 11 }, padding: 10, cornerRadius: 8,
                    callbacks: {
                        title: c => c[0].raw.nome,
                        label: c => {
                            const p = c.raw;
                            return [
                                `Grupo: ${p.grupo}`,
                                `Volume: ${p.volume_percentual.toFixed(1)}% (meta ${fmtBR(p.vol_meta)} / real ${fmtBR(p.vol_real)})`,
                                `Positivação: ${p.positivacao_percentual.toFixed(1)}% (meta ${fmtBR(p.pos_meta)} / real ${fmtBR(p.pos_real)})`,
                                `Cat. Foco c/ 100%: ${p.cats_100}`,
                                `${p.elegivel ? '✓ Elegível — R$ ' + fmtBR(p.premiacao, 2) : '✗ Inelegível'}`,
                            ];
                        }
                    }
                }
            },
            scales: {
                x: { type: 'linear', title: { display: true, text: 'Volume (%)' }, min: 0, suggestedMax: 150,
                    grid: { color: ctx2 => ctx2.tick.value === 100 ? 'rgba(239,68,68,0.4)' : 'rgba(0,0,0,0.06)' }
                },
                y: { title: { display: true, text: 'Positivação (%)' }, min: 0, suggestedMax: 150,
                    grid: { color: ctx2 => ctx2.tick.value === 100 ? 'rgba(239,68,68,0.4)' : 'rgba(0,0,0,0.06)' }
                }
            }
        }
    });
}

function updateScatterChart(distributors) {
    if (!charts.scatter) return;
    charts.scatter.data.datasets[0].data = distributors.map(d => ({
        x: Math.round(d.volume_percentual * 10) / 10,
        y: Math.round(d.positivacao_percentual * 10) / 10,
        nome: d.nome, grupo: d.grupo, elegivel: d.elegivel,
        volume_percentual: d.volume_percentual, positivacao_percentual: d.positivacao_percentual,
        vol_meta: d.total_volume_meta, vol_real: d.total_volume_realizado,
        pos_meta: d.positivacao_foco_meta, pos_real: d.positivacao_foco_realizado,
        cats_100: d.categorias_positivacao_foco_100 || 0, premiacao: d.premiacao || 0,
    }));
    charts.scatter.update();
}

// ── 2. Barras Agrupadas (Categorias) ──
function initCategoriesChart() {
    const ctx = document.getElementById('chart-categories-performance');
    if (!ctx) return;
    charts.categories = new Chart(ctx, {
        type: 'bar',
        data: { labels: [], datasets: [
            { label: 'Meta', data: [], backgroundColor: '#94a3b8', borderRadius: 3 },
            { label: 'Realizado', data: [], backgroundColor: '#10b981', borderRadius: 3 },
        ]},
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: { callbacks: { label: c => `${c.dataset.label}: ${fmtBR(c.raw)}` } }
            },
            scales: { x: { ticks: { font: { size: 10 }, maxRotation: 45, minRotation: 25 } }, y: { beginAtZero: true, ticks: { callback: v => fmtBR(v) } } }
        }
    });
}

function updateCategoriesChart(categories) {
    if (!charts.categories) return;
    const sorted = [...categories].sort((a, b) => b.total_meta - a.total_meta);
    charts.categories.data.labels = sorted.map(c => c.nome.length > 18 ? c.nome.substring(0, 18) + '…' : c.nome);
    charts.categories.data.datasets[0].data = sorted.map(c => Math.round(c.total_meta));
    charts.categories.data.datasets[1].data = sorted.map(c => Math.round(c.total_realizado));
    charts.categories.update();
}

// ── 3. Ranking Horizontal (gold = elegível) ──
function initRankingChart() {
    const ctx = document.getElementById('chart-distributor-ranking');
    if (!ctx) return;
    charts.ranking = new Chart(ctx, {
        type: 'bar',
        data: { labels: [], datasets: [{ label: 'Volume (%)', data: [], backgroundColor: [], borderRadius: 3 }] },
        options: {
            responsive: true, maintainAspectRatio: false, indexAxis: 'y',
            plugins: { legend: { display: false },
                tooltip: { callbacks: { label: c => `Volume: ${c.raw.toFixed(1)}% — ${c.raw >= 100 ? '✓ Meta batida' : '✗ Abaixo da meta'}` } }
            },
            scales: { x: { beginAtZero: true, suggestedMax: 150, ticks: { callback: v => v + '%' } }, y: { ticks: { font: { size: 10 } } } }
        }
    });
}

function updateRankingChart(distributors) {
    if (!charts.ranking) return;
    const sorted = [...distributors].sort((a, b) => b.volume_percentual - a.volume_percentual).slice(0, 10);
    charts.ranking.data.labels = sorted.map(d => d.nome.length > 20 ? d.nome.substring(0, 20) + '…' : d.nome);
    charts.ranking.data.datasets[0].data = sorted.map(d => Math.round(d.volume_percentual * 10) / 10);
    charts.ranking.data.datasets[0].backgroundColor = sorted.map(d => d.elegivel ? '#d4a017' : '#94a3b8');
    charts.ranking.update();
}

// ── 4. Radar (Mix Foco) ──
function initRadarChart() {
    const ctx = document.getElementById('chart-categories-mix');
    if (!ctx) return;
    charts.radar = new Chart(ctx, {
        type: 'radar',
        data: { labels: [], datasets: [{
            label: 'Atingimento (%)', data: [],
            backgroundColor: 'rgba(255,103,31,0.15)', borderColor: '#FF671F', borderWidth: 2,
            pointBackgroundColor: '#FF671F', pointBorderColor: '#fff'
        }]},
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { r: { beginAtZero: true, suggestedMax: 150, ticks: { callback: v => v + '%', font: { size: 9 } }, pointLabels: { font: { size: 10 } } } }
        }
    });
}

function updateRadarChart(src) {
    if (!charts.radar) return;
    const s = Array.isArray(src) && src.length > 0 ? src : radarData;
    charts.radar.data.labels = s.map(i => i.categoria.length > 18 ? i.categoria.substring(0, 18) + '…' : i.categoria);
    charts.radar.data.datasets[0].data = s.map(i => Math.round(i.atingimento * 10) / 10);
    charts.radar.update();
}

// ── 5. Line (Histórico) ──
function initHistoryChart() {
    const ctx = document.getElementById('chart-performance-history');
    if (!ctx) return;
    charts.history = new Chart(ctx, {
        type: 'line',
        data: { labels: [], datasets: [{
            label: 'Atingimento Volume (%)', data: [],
            borderColor: '#FF671F', backgroundColor: 'rgba(255,103,31,0.1)',
            borderWidth: 2, fill: true, tension: 0.4, pointRadius: 4, pointBackgroundColor: '#FF671F'
        }]},
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => `Atingimento: ${c.raw.toFixed(1)}%` } } },
            scales: { y: { beginAtZero: true, suggestedMax: 150, ticks: { callback: v => v + '%' } } }
        }
    });
}

function updateHistoryChart(src) {
    if (!charts.history) return;
    const s = Array.isArray(src) && src.length ? src : historicoData;
    charts.history.data.labels = s.map(i => i.periodo);
    charts.history.data.datasets[0].data = s.map(i => Math.round((i.percentual || 0) * 10) / 10);
    charts.history.update();
}

// ── Audit Grid ──
let gridData = [];

function renderAuditGrid(distributors) {
    gridData = [...distributors];
    applyGridSort();
    drawGrid();
}

function drawGrid() {
    const tbody = document.getElementById('audit-tbody');
    if (!tbody) return;

    const search = (document.getElementById('grid-search')?.value || '').toLowerCase();
    const visible = gridData.filter(d => !search || d.nome.toLowerCase().includes(search) || d.cnpj.includes(search));

    tbody.innerHTML = visible.map(d => {
        const volClass = d.volume_percentual >= 100 ? 'color:#059669;font-weight:600' : 'color:#dc2626';
        const posClass = d.positivacao_percentual >= 100 ? 'color:#059669;font-weight:600' : 'color:#dc2626';
        const catClass = d.categorias_positivacao_foco_100 >= 2 ? 'color:#059669;font-weight:600' : 'color:#dc2626';
        return `<tr class="${d.elegivel ? 'row-elegivel' : ''}">
            <td><strong>${esc(d.nome)}</strong><br><span style="font-size:.7rem;color:#9ca3af">${esc(d.cnpj)}</span></td>
            <td class="text-center">${d.grupo}</td>
            <td style="${volClass}">${d.volume_percentual.toFixed(1)}%</td>
            <td style="${posClass}">${d.positivacao_percentual.toFixed(1)}%</td>
            <td class="text-center" style="${catClass}">${d.categorias_positivacao_foco_100}</td>
            <td>${d.elegivel
                ? '<span class="badge-status badge-elegivel"><i class="bi bi-check-circle-fill"></i> Elegível</span>'
                : '<span class="badge-status badge-inelegivel"><i class="bi bi-x-circle-fill"></i> Inelegível</span>'}</td>
            <td style="font-weight:600">${d.premiacao > 0 ? 'R$ ' + fmtBR(d.premiacao, 2) : '—'}</td>
        </tr>`;
    }).join('');

    const countEl = document.getElementById('grid-count');
    if (countEl) countEl.textContent = `${visible.length} de ${gridData.length} distribuidores`;

    const summEl = document.getElementById('grid-summary');
    if (summEl) {
        const eleg = visible.filter(d => d.elegivel).length;
        const pay = visible.reduce((s, d) => s + d.premiacao, 0);
        summEl.textContent = `${eleg} elegíveis · Payout: R$ ${fmtBR(pay, 2)}`;
    }
}

function filterGrid() { drawGrid(); }

function sortGrid(field) {
    if (gridSortField === field) { gridSortAsc = !gridSortAsc; } else { gridSortField = field; gridSortAsc = true; }
    applyGridSort();
    drawGrid();
}

function applyGridSort() {
    gridData.sort((a, b) => {
        let va = a[gridSortField], vb = b[gridSortField];
        if (typeof va === 'string') { va = va.toLowerCase(); vb = (vb || '').toLowerCase(); }
        if (typeof va === 'boolean') { va = va ? 1 : 0; vb = vb ? 1 : 0; }
        if (va < vb) return gridSortAsc ? -1 : 1;
        if (va > vb) return gridSortAsc ? 1 : -1;
        return 0;
    });
}

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

// ── CSV Downloads ──
function downloadCSV(rows, filename) {
    const csv = rows.map(r => r.map(f => { const s = String(f); return s.includes(',') || s.includes('"') || s.includes('\n') ? '"' + s.replace(/"/g, '""') + '"' : s; }).join(',')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob); a.download = filename; a.click();
}

function downloadGridCSV() {
    const search = (document.getElementById('grid-search')?.value || '').toLowerCase();
    const visible = gridData.filter(d => !search || d.nome.toLowerCase().includes(search) || d.cnpj.includes(search));
    if (!visible.length) { alert('Sem dados'); return; }
    downloadCSV([
        ['Distribuidor', 'CNPJ', 'Grupo', '% Volume', '% Positivação', 'Cat. Foco 100%', 'Status', 'Prêmio (R$)'],
        ...visible.map(d => [d.nome, d.cnpj, d.grupo, d.volume_percentual.toFixed(2) + '%', d.positivacao_percentual.toFixed(2) + '%', d.categorias_positivacao_foco_100, d.elegivel ? 'Elegível' : 'Inelegível', d.premiacao.toFixed(2)])
    ], 'auditoria_distribuidores.csv');
}

function downloadDistributorsCSV() {
    const dist = getFilteredDistributors();
    if (!dist.length) { alert('Sem dados'); return; }
    downloadCSV([
        ['Distribuidor', 'CNPJ', 'Grupo', 'Vol. Meta', 'Vol. Real.', 'Vol. %', 'Pos. Meta', 'Pos. Real.', 'Pos. %', 'Elegível'],
        ...dist.map(d => [d.nome, d.cnpj, d.grupo, d.total_volume_meta, d.total_volume_realizado, d.volume_percentual.toFixed(2) + '%', d.positivacao_foco_meta, d.positivacao_foco_realizado, d.positivacao_percentual.toFixed(2) + '%', d.elegivel ? 'Sim' : 'Não'])
    ], 'distribuidores.csv');
}

function downloadCategoriesCSV() {
    const cats = lastProcessedData?.categories || [];
    if (!cats.length) { alert('Sem dados'); return; }
    downloadCSV([
        ['Categoria', 'Foco', 'Meta', 'Realizado', '%', 'Distribuidores'],
        ...cats.map(c => [c.nome, c.foco ? 'Sim' : 'Não', c.total_meta, c.total_realizado, (c.percentual || 0).toFixed(2) + '%', c.distribuidores.length])
    ], 'categorias.csv');
}

function downloadRankingCSV() {
    const dist = getFilteredDistributors().sort((a, b) => b.volume_percentual - a.volume_percentual);
    if (!dist.length) { alert('Sem dados'); return; }
    downloadCSV([
        ['#', 'Distribuidor', 'Vol. %', 'Pos. %', 'Elegível'],
        ...dist.map((d, i) => [i + 1, d.nome, d.volume_percentual.toFixed(2) + '%', d.positivacao_percentual.toFixed(2) + '%', d.elegivel ? 'Sim' : 'Não'])
    ], 'ranking.csv');
}

function downloadRadarCSV() {
    const r = lastProcessedData?.radarData || radarData || [];
    if (!r.length) { alert('Sem dados'); return; }
    downloadCSV([['Categoria', 'Atingimento %'], ...r.map(i => [i.categoria, i.atingimento.toFixed(2) + '%'])], 'mix_foco.csv');
}

function downloadHistoricoCSV() {
    const h = lastProcessedData?.historicoData || historicoData || [];
    if (!h.length) { alert('Sem dados'); return; }
    downloadCSV([['Período', 'Vol. Meta', 'Vol. Real.', 'Ating. %'], ...h.map(i => [i.periodo, i.volume_meta || 0, i.volume_realizado || 0, (i.percentual || 0).toFixed(2) + '%'])], 'historico.csv');
}

function getFilteredDistributors() { return lastProcessedData?.distributors || []; }

// ── AI Analysis ──
let aiAnalysisCache = null;
let aiLastPeriodo = null;

function clearAIAnalysis() {
    aiAnalysisCache = null;
    aiLastPeriodo = null;
    const content = document.getElementById('ai-content');
    const modal = document.getElementById('ai-modal');
    if (content) content.innerHTML = '';
    if (modal) modal.classList.remove('active');
}

function requestAIAnalysis() {
    const modal = document.getElementById('ai-modal');
    const loading = document.getElementById('ai-loading');
    const content = document.getElementById('ai-content');
    const btn = document.getElementById('btn-ai-analysis');
    const currentPeriodo = document.getElementById('filter-periodo')?.value || '';

    modal.classList.add('active');

    if (aiAnalysisCache && aiLastPeriodo === currentPeriodo) {
        loading.style.display = 'none';
        content.style.display = 'block';
        return;
    }

    aiAnalysisCache = null;
    loading.style.display = 'block';
    content.style.display = 'none';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Analisando...'; }

    const params = currentPeriodo ? '?periodo=' + encodeURIComponent(currentPeriodo) : '';
    fetch('/api/analysis' + params)
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Erro desconhecido');

            aiAnalysisCache = data;
            aiLastPeriodo = currentPeriodo;
            const summary = data.summary;

            let html = `<div class="ai-summary-grid">
                <div class="ai-summary-card"><div class="label">Período</div><div class="val">${summary.periodo}</div></div>
                <div class="ai-summary-card"><div class="label">Distribuidores</div><div class="val">${summary.total_distribuidores}</div></div>
                <div class="ai-summary-card"><div class="label">Elegíveis</div><div class="val" style="color:#059669">${summary.elegiveis} (${summary.taxa_elegibilidade}%)</div></div>
                <div class="ai-summary-card"><div class="label">Payout Total</div><div class="val" style="color:#d97706">R$ ${(summary.payout_total || 0).toLocaleString('pt-BR')}</div></div>
                <div class="ai-summary-card"><div class="label">Atingimento Volume</div><div class="val">${summary.atingimento_volume_geral}%</div></div>
                <div class="ai-summary-card"><div class="label">Quase Elegíveis</div><div class="val" style="color:#ea580c">${summary.quase_elegiveis}</div></div>
            </div>`;

            html += `<div style="border-top:1px solid #e5e7eb;padding-top:1rem;margin-top:0.5rem">`;
            html += renderMarkdown(data.analysis);
            html += `</div>`;

            content.innerHTML = html;
            loading.style.display = 'none';
            content.style.display = 'block';
        })
        .catch(err => {
            loading.style.display = 'none';
            content.style.display = 'block';
            content.innerHTML = `<div style="text-align:center;padding:2rem;color:#dc2626">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:2rem"></i>
                <p style="margin-top:0.75rem;font-weight:600">Erro ao gerar análise</p>
                <p style="font-size:0.82rem;color:#6b7280">${esc(err.message)}</p>
                <button class="btn btn-outline-danger btn-sm mt-2" onclick="aiAnalysisCache=null;requestAIAnalysis()">Tentar novamente</button>
            </div>`;
        })
        .finally(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-stars me-1"></i> Análise IA'; }
        });
}

function renderMarkdown(text) {
    if (!text) return '';
    let html = text
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`(.+?)`/g, '<code>$1</code>')
        .replace(/^### (.+)$/gm, '<h3>$1</h3>')
        .replace(/^## (.+)$/gm, '<h2>$1</h2>')
        .replace(/^# (.+)$/gm, '<h1>$1</h1>')
        .replace(/^---$/gm, '<hr style="border:none;border-top:1px solid #e5e7eb;margin:1rem 0">')
        .replace(/^> (.+)$/gm, '<blockquote>$1</blockquote>')
        .replace(/^\d+\.\s+(.+)$/gm, '<li class="ol-item">$1</li>')
        .replace(/^[-•]\s+(.+)$/gm, '<li>$1</li>');

    html = html.replace(/((?:<li class="ol-item">.*<\/li>\n?)+)/g, '<ol>$1</ol>');
    html = html.replace(/((?:<li>.*<\/li>\n?)+)/g, (match) => {
        if (match.includes('ol-item')) return match;
        return '<ul>' + match + '</ul>';
    });
    html = html.replace(/class="ol-item"/g, '');

    html = html.split('\n').map(line => {
        const trimmed = line.trim();
        if (!trimmed) return '';
        if (trimmed.startsWith('<h') || trimmed.startsWith('<ul') || trimmed.startsWith('<ol') || trimmed.startsWith('<li') || trimmed.startsWith('<hr') || trimmed.startsWith('<blockquote') || trimmed.startsWith('</')) return line;
        return '<p>' + line + '</p>';
    }).join('\n');

    html = html.replace(/<p><\/p>/g, '');
    return html;
}
