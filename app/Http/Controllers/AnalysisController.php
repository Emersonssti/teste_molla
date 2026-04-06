<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Models\SpreadsheetDataset;
use App\Services\Dashboard\DashboardDataProcessor;
use App\Services\Spreadsheet\PerformanceSheetReader;
use App\Services\Spreadsheet\SpreadsheetPathResolver;

final class AnalysisController extends Controller
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const API_KEY = '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';
    private const API_MODEL = 'llama-3.3-70b-versatile';

    public function generate(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $dataset = $this->loadDataset();
            $allRows = $dataset->all();

            $periodoFilter = $_GET['periodo'] ?? null;
            if ($periodoFilter) {
                $parts = explode('-', $periodoFilter);
                if (count($parts) === 2) {
                    $filterAno = (int)$parts[0];
                    $filterMes = (int)$parts[1];
                    $allRows = array_filter($allRows, fn($row) => ($row->ano ?? 0) === $filterAno && ($row->mesNumero ?? 0) === $filterMes);
                    $allRows = array_values($allRows);
                }
            }

            $summary = $this->buildSummary($allRows);
            $prompt = $this->buildPrompt($summary);
            $grokResponse = $this->callGrok($prompt);

            echo json_encode([
                'success' => true,
                'summary' => $summary,
                'analysis' => $grokResponse,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function loadDataset(): SpreadsheetDataset
    {
        $uploadedPath = $_SESSION['uploaded_file_path'] ?? null;
        if ($uploadedPath && is_file($uploadedPath)) {
            $path = $uploadedPath;
        } else {
            $path = (new SpreadsheetPathResolver())->defaultPerformanceFile();
        }

        return (new PerformanceSheetReader())->loadFromPath($path);
    }

    private function buildSummary(array $rows): array
    {
        $distributors = [];
        $categories = [];
        $periodos = [];
        $volumePorPeriodo = [];

        foreach ($rows as $row) {
            $distId = $row->distributorId;
            if (!isset($distributors[$distId])) {
                $distributors[$distId] = [
                    'nome' => $row->distributorName,
                    'grupo' => $row->grupo,
                    'vol_meta' => 0.0,
                    'vol_real' => 0.0,
                    'pos_meta' => 0.0,
                    'pos_real' => 0.0,
                    'pos_by_cat' => [],
                ];
            }

            $catName = $row->categoryName;
            if (!isset($categories[$catName])) {
                $categories[$catName] = [
                    'nome' => $catName,
                    'foco' => $row->isFocusCategory,
                    'meta' => 0.0,
                    'real' => 0.0,
                ];
            }

            $mesNum = $row->mesNumero ?? 0;
            $anoNum = $row->ano ?? 0;
            $periodoKey = $anoNum * 100 + $mesNum;
            $periodo = $mesNum . '/' . $anoNum;
            if (!isset($periodos[$periodoKey])) $periodos[$periodoKey] = $periodo;

            if ($row->kpiType === 'TOTAL_VOLUME') {
                $distributors[$distId]['vol_meta'] += (float)($row->meta ?? 0);
                $distributors[$distId]['vol_real'] += (float)($row->realizado ?? 0);
                $categories[$catName]['meta'] += (float)($row->meta ?? 0);
                $categories[$catName]['real'] += (float)($row->realizado ?? 0);

                if (!isset($volumePorPeriodo[$periodo])) $volumePorPeriodo[$periodo] = ['meta' => 0, 'real' => 0];
                $volumePorPeriodo[$periodo]['meta'] += (float)($row->meta ?? 0);
                $volumePorPeriodo[$periodo]['real'] += (float)($row->realizado ?? 0);
            }

            if ($row->kpiType === 'CATEGORY_POSITIVATION_FOCUS' && $row->isFocusCategory) {
                $distributors[$distId]['pos_meta'] += (float)($row->meta ?? 0);
                $distributors[$distId]['pos_real'] += (float)($row->realizado ?? 0);
                if (!isset($distributors[$distId]['pos_by_cat'][$catName])) {
                    $distributors[$distId]['pos_by_cat'][$catName] = ['meta' => 0.0, 'real' => 0.0];
                }
                $distributors[$distId]['pos_by_cat'][$catName]['meta'] += (float)($row->meta ?? 0);
                $distributors[$distId]['pos_by_cat'][$catName]['real'] += (float)($row->realizado ?? 0);
            }
        }

        $premios = [1 => 6000, 2 => 5000, 3 => 3000, 4 => 2000, 5 => 1000];
        $elegiveis = [];
        $quaseElegiveis = [];
        $volumePorGrupo = [];

        foreach ($distributors as &$d) {
            $d['vol_pct'] = $d['vol_meta'] > 0 ? ($d['vol_real'] / $d['vol_meta']) * 100 : 0;
            $d['pos_pct'] = $d['pos_meta'] > 0 ? ($d['pos_real'] / $d['pos_meta']) * 100 : 0;

            $cats100 = 0;
            foreach ($d['pos_by_cat'] as $agg) {
                if ($agg['meta'] > 0 && ($agg['real'] / $agg['meta']) >= 1.0) $cats100++;
            }
            $d['cats_100'] = $cats100;
            $d['elegivel'] = $d['vol_pct'] >= 100 && $d['pos_pct'] >= 100 && $cats100 >= 2;
            $d['premio'] = $d['elegivel'] ? ($premios[$d['grupo']] ?? 0) : 0;

            if ($d['elegivel']) $elegiveis[] = $d;
            if (!$d['elegivel'] && $d['vol_pct'] >= 95) $quaseElegiveis[] = $d;

            $g = $d['grupo'];
            if (!isset($volumePorGrupo[$g])) $volumePorGrupo[$g] = ['soma_pct' => 0, 'count' => 0];
            $volumePorGrupo[$g]['soma_pct'] += $d['vol_pct'];
            $volumePorGrupo[$g]['count']++;
        }
        unset($d);

        foreach ($categories as &$cat) {
            $cat['pct'] = $cat['meta'] > 0 ? ($cat['real'] / $cat['meta']) * 100 : 0;
        }
        unset($cat);

        $catsSorted = array_values($categories);
        usort($catsSorted, fn($a, $b) => $b['pct'] <=> $a['pct']);

        $volMetaTotal = array_sum(array_column(array_values($distributors), 'vol_meta'));
        $volRealTotal = array_sum(array_column(array_values($distributors), 'vol_real'));

        $mediaGrupo = [];
        ksort($volumePorGrupo);
        foreach ($volumePorGrupo as $g => $v) {
            $mediaGrupo['Grupo ' . $g] = round($v['soma_pct'] / $v['count'], 1) . '%';
        }

        $evolucao = [];
        ksort($volumePorPeriodo);
        foreach ($volumePorPeriodo as $p => $v) {
            $evolucao[$p] = $v['meta'] > 0 ? round(($v['real'] / $v['meta']) * 100, 1) . '%' : '0%';
        }

        $totalDist = count($distributors);
        $totalEleg = count($elegiveis);

        ksort($periodos);
        $periodosOrdenados = array_values($periodos);
        $periodoLabel = count($periodosOrdenados) > 1
            ? reset($periodosOrdenados) . ' a ' . end($periodosOrdenados)
            : ($periodosOrdenados[0] ?? '—');

        return [
            'periodo' => $periodoLabel,
            'abrangencia' => count($periodosOrdenados) > 1 ? 'Todos os períodos da campanha' : 'Período único',
            'periodos_disponiveis' => $periodosOrdenados,
            'total_distribuidores' => $totalDist,
            'elegiveis' => $totalEleg,
            'taxa_elegibilidade' => $totalDist > 0 ? round(($totalEleg / $totalDist) * 100, 1) : 0,
            'payout_total' => array_sum(array_column($elegiveis, 'premio')),
            'volume_meta_total' => round($volMetaTotal, 0),
            'volume_real_total' => round($volRealTotal, 0),
            'atingimento_volume_geral' => $volMetaTotal > 0 ? round(($volRealTotal / $volMetaTotal) * 100, 1) : 0,
            'media_volume_por_grupo' => $mediaGrupo,
            'evolucao_volume_por_periodo' => $evolucao,
            'top3_categorias' => array_map(fn($c) => $c['nome'] . ' (' . round($c['pct'], 1) . '%)', array_slice($catsSorted, 0, 3)),
            'bottom3_categorias' => array_map(fn($c) => $c['nome'] . ' (' . round($c['pct'], 1) . '%)', array_slice(array_reverse($catsSorted), 0, 3)),
            'quase_elegiveis' => count($quaseElegiveis),
            'quase_elegiveis_detalhe' => array_map(fn($d) => [
                'nome' => $d['nome'], 'grupo' => $d['grupo'],
                'volume' => round($d['vol_pct'], 1) . '%', 'positivacao' => round($d['pos_pct'], 1) . '%',
                'cats_foco_100' => $d['cats_100'],
            ], array_slice($quaseElegiveis, 0, 10)),
        ];
    }

    private function buildPrompt(array $summary): string
    {
        $dados = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
Você é um Consultor Estratégico de Trade Marketing sênior, especialista em campanhas de incentivo para distribuidores.

Aqui estão os KPIs consolidados da campanha de incentivo:

```json
{$dados}
```

**Contexto do Regulamento:**
- Programa de 01/09/2024 a 31/08/2025.
- Elegibilidade mensal: Volume Total ≥ 100% + Positivação Foco ≥ 100% no total e em ao menos 2 categorias foco com 100%.
- Premiação "Bateu, Levou": Grupo 1 = R\$ 6.000, Grupo 2 = R\$ 5.000, Grupo 3 = R\$ 3.000, Grupo 4 = R\$ 2.000, Grupo 5 = R\$ 1.000.
- Acelerador mensal: +20% sobre o prêmio base para quem atingir a categoria aceleradora do mês.

**Responda em português, de forma direta e executiva:**

1. **Conclusões dos KPIs**: Apresente as principais conclusões que os indicadores revelam sobre a saúde da campanha. Analise taxa de elegibilidade, atingimento de volume, performance por grupo e categorias. Destaque padrões, riscos e oportunidades.

2. **Ações Sugeridas para os Últimos Meses**: Sugira ações concretas e viáveis para melhorar os resultados nos meses finais da campanha, visando aumentar o número de ganhadores e o volume de vendas. Seja específico e priorize por impacto.

3. **"Quase Elegíveis"**: Analise o potencial de conversão dos distribuidores próximos da elegibilidade (volume ≥95%). Que ações focadas podem convertê-los?

4. **Visão por Grupo**: Há algum grupo (1 a 5) que merece atenção diferenciada? Por quê?

Formate a resposta com títulos em **negrito** e bullets. Seja conciso mas estratégico.
PROMPT;
    }

    private function callGrok(string $prompt): string
    {
        $payload = json_encode([
            'model' => self::API_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => 'Você é um consultor estratégico de Trade Marketing sênior. Responda sempre em português do Brasil, de forma executiva e acionável.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 3000,
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . self::API_KEY,
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Falha na conexão com a API: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException('API retornou HTTP ' . $httpCode . ': ' . substr((string)$response, 0, 500));
        }

        $data = json_decode((string)$response, true);
        return $data['choices'][0]['message']['content'] ?? 'Sem resposta da IA.';
    }
}
