<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Services\Settlement\MonthlySettlementCalculator;
use App\Services\Spreadsheet\InvalidSpreadsheetStructureException;
use App\Services\Spreadsheet\PerformanceSheetReader;
use App\Services\Spreadsheet\SpreadsheetTreatmentService;

final class TratarController extends Controller
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    public function form(): void
    {
        $erro = isset($_GET['erro']) ? (string) $_GET['erro'] : null;
        $this->view('tratar.form', [
            'title' => 'Tratar planilha — Desafio Técnico Agência Molla',
            'bodyClass' => 'py-5',
            'erro' => $erro !== '' ? $erro : null,
        ]);
    }

    public function process(): void
    {
        $month = (int) ($_POST['mes'] ?? 0);
        $year = (int) ($_POST['ano'] ?? 0);

        if ($month < 1 || $month > 12) {
            $this->redirectErro('Selecione um mês válido.');
            return;
        }
        if ($year < 2020 || $year > 2035) {
            $this->redirectErro('Informe um ano válido (2020 a 2035).');
            return;
        }

        $uploadedPath = null;
        $uploadedName = null;

        if (isset($_FILES['planilha']) && is_array($_FILES['planilha'])) {
            $file = $_FILES['planilha'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                if (($file['size'] ?? 0) > self::MAX_BYTES) {
                    $this->redirectErro('Arquivo muito grande (máximo 10 MB).');
                    return;
                }

                $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
                if (!in_array($ext, ['xlsx', 'xls'], true)) {
                    $this->redirectErro('Formato inválido. Use .xlsx ou .xls.');
                    return;
                }

                $tmpIn = (string) $file['tmp_name'];
                if ($tmpIn === '' || !is_uploaded_file($tmpIn)) {
                    $this->redirectErro('Upload inválido.');
                    return;
                }

                $uploadedPath = $tmpIn;
                $uploadedName = $file['name'] ?? 'planilha.xlsx';
            }
        }

        if ($uploadedPath === null) {
            if (isset($_SESSION['uploaded_file_path']) && is_file($_SESSION['uploaded_file_path'])) {
                $uploadedPath = $_SESSION['uploaded_file_path'];
                $uploadedName = $_SESSION['uploaded_file_name'] ?? 'planilha.xlsx';
            }
        }

        if ($uploadedPath === null) {
            $this->redirectErro('Nenhum arquivo disponível para tratamento. Faça upload na tela inicial.');
            return;
        }

        try {
            $reader = new PerformanceSheetReader();
            $dataset = $reader->loadFromPath($uploadedPath);

            $calculator = new MonthlySettlementCalculator();
            $report = $calculator->calculate($dataset, $month, $year);

            $treatment = new SpreadsheetTreatmentService();
            $outPath = $treatment->buildTreatedWorkbook($report);

            $fname = sprintf('Teste_BI_Molla_Apuracao_%d_%02d.xlsx', $year, $month);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fname . '"');
            header('Content-Length: ' . (string) filesize($outPath));
            readfile($outPath);
            @unlink($outPath);
            exit;
        } catch (InvalidSpreadsheetStructureException $e) {
            $this->redirectErro($e->getMessage());
            return;
        } catch (\Throwable $e) {
            if (config('debug')) {
                $this->redirectErro('Erro ao processar: ' . $e->getMessage());
                return;
            }
            $this->redirectErro('Não foi possível processar a planilha. Verifique o arquivo e tente novamente.');
            return;
        }
    }

    private function redirectErro(string $message): void
    {
        $q = http_build_query(['erro' => $message], '', '&', PHP_QUERY_RFC3986);
        header('Location: /tratar?' . $q, true, 302);
        exit;
    }
}
