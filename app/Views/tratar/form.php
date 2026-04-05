<?php
declare(strict_types=1);
/** @var string|null $erro */
?>
<style>
    :root {
        --primary-color: #FF671F;
        --primary-dark: #e0520a;
        --dark-color: #1f2937;
        --border-color: #e5e7eb;
    }

    body {
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        background: linear-gradient(135deg, #FF671F 0%, #e0520a 100%);
        height: 100vh;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .treatment-page {
        max-width: 440px;
        width: 100%;
        padding: 0 1rem;
    }

    .card-treatment {
        background: white;
        border-radius: 16px;
        box-shadow: 0 25px 60px rgba(0,0,0,0.2);
        overflow: hidden;
        animation: slideUp 0.4s ease;
    }
    @keyframes slideUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

    .card-header-bar {
        background: linear-gradient(135deg, #FF671F, #e0520a);
        padding: 1rem 1.25rem;
        color: white;
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }
    .card-header-bar .icon-wrap {
        width: 38px; height: 38px; border-radius: 10px;
        background: rgba(255,255,255,0.18);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem; flex-shrink: 0;
    }
    .card-header-bar h2 { font-size: 0.95rem; font-weight: 700; margin: 0; }
    .card-header-bar small { font-size: 0.72rem; opacity: 0.8; display: block; margin-top: 1px; }

    .card-body-treatment { padding: 1.15rem 1.25rem; }

    .alert-danger-custom {
        background: rgba(239,68,68,0.08);
        border: 1px solid rgba(239,68,68,0.2);
        border-radius: 8px;
        padding: 0.55rem 0.85rem;
        color: #dc2626;
        font-size: 0.78rem;
        font-weight: 500;
        margin-bottom: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .steps-row {
        display: flex;
        gap: 0.35rem;
        margin-bottom: 0.85rem;
    }
    .step-pill {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.55rem;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid var(--border-color);
        font-size: 0.72rem;
        color: #6b7280;
    }
    .step-pill .sn {
        width: 18px; height: 18px; border-radius: 50%;
        background: var(--primary-color); color: white;
        font-weight: 700; font-size: 0.62rem;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }

    .form-row {
        display: flex;
        gap: 0.65rem;
        margin-bottom: 0.85rem;
    }
    .form-group { flex: 1; }
    .form-group label {
        display: block;
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--dark-color);
        margin-bottom: 0.25rem;
        letter-spacing: 0.3px;
    }
    .form-group .form-select,
    .form-group .form-control {
        border: 1.5px solid var(--border-color);
        border-radius: 8px;
        padding: 0.45rem 0.7rem;
        font-size: 0.85rem;
        color: var(--dark-color);
        transition: border-color 0.2s, box-shadow 0.2s;
        width: 100%;
        background: #fafbfc;
    }
    .form-group .form-select:focus,
    .form-group .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(255,103,31,0.12);
        outline: none;
        background: white;
    }

    .btn-download {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        width: 100%;
        padding: 0.6rem;
        border: none;
        border-radius: 10px;
        background: linear-gradient(135deg, #FF671F, #e0520a);
        color: white;
        font-size: 0.88rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.25s;
        box-shadow: 0 4px 14px rgba(255,103,31,0.3);
    }
    .btn-download:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(255,103,31,0.4);
    }
    .btn-download:active { transform: translateY(0); }

    .btn-back {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        width: 100%;
        padding: 0.5rem;
        border: 1.5px solid var(--border-color);
        border-radius: 10px;
        background: white;
        color: #6b7280;
        font-size: 0.8rem;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s;
        margin-top: 0.55rem;
    }
    .btn-back:hover { border-color: #d1d5db; background: #f9fafb; color: var(--dark-color); }

    .info-line {
        font-size: 0.72rem;
        color: #9ca3af;
        text-align: center;
        margin-top: 0.75rem;
        line-height: 1.45;
    }

    @media (max-width: 480px) {
        .treatment-page { padding: 0 0.5rem; }
        .card-body-treatment { padding: 1rem; }
        .form-row { flex-direction: column; gap: 0.5rem; }
    }
</style>

<div class="treatment-page">
    <div class="card-treatment">
        <div class="card-header-bar">
            <div class="icon-wrap"><i class="bi bi-file-earmark-spreadsheet"></i></div>
            <div>
                <h2>Apuração Mensal — Excel</h2>
                <small>Programa de Incentivo · Agência Molla</small>
            </div>
        </div>

        <div class="card-body-treatment">
            <?php if (!empty($erro)) : ?>
                <div class="alert-danger-custom">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="steps-row">
                <div class="step-pill"><div class="sn">1</div> Selecione o período</div>
                <div class="step-pill"><div class="sn">2</div> Clique em baixar</div>
                <div class="step-pill"><div class="sn">3</div> Excel pronto!</div>
            </div>

            <form action="/tratar" method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="bi bi-calendar-month me-1"></i>Mês</label>
                        <select name="mes" class="form-select" required>
                            <?php
                            $meses = [
                                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                                5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                                9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
                            ];
                            foreach ($meses as $num => $nome) {
                                $sel = $num === 5 ? ' selected' : '';
                                echo '<option value="' . $num . '"' . $sel . '>' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="bi bi-calendar-event me-1"></i>Ano</label>
                        <input type="number" name="ano" class="form-control" min="2020" max="2035" value="2025" required>
                    </div>
                </div>

                <button type="submit" class="btn-download">
                    <i class="bi bi-download"></i> Baixar Planilha Tratada
                </button>

                <a href="/" class="btn-back">
                    <i class="bi bi-arrow-left"></i> Voltar ao início
                </a>
            </form>

            <p class="info-line">
                Apuração "Bateu, Levou" + Acelerador 20%<br>
                Volume · Positivação · Movimento detalhado
            </p>
        </div>
    </div>
</div>
