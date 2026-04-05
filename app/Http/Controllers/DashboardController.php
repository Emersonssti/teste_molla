<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Models\SpreadsheetDataset;
use App\Services\Dashboard\DashboardDataService;
use App\Services\Dashboard\DashboardDataProcessor;
use App\Services\Spreadsheet\PerformanceSheetReader;
use App\Services\Spreadsheet\SpreadsheetPathResolver;

final class DashboardController extends Controller
{
    public function index(): void
    {
        // Verificar se há arquivo enviado na sessão
        $uploadedPath = $_SESSION['uploaded_file_path'] ?? null;
        $useUploadedFile = $uploadedPath && is_file($uploadedPath);

        if ($useUploadedFile) {
            $path = $uploadedPath;
        } else {
            $pathResolver = new SpreadsheetPathResolver();
            $path = $pathResolver->defaultPerformanceFile();
        }

        try {
            $dataset = (new PerformanceSheetReader())->loadFromPath($path);
        } catch (\Throwable) {
            $dataset = SpreadsheetDataset::empty($path);
        }

        // Obter dados processados incluindo dados brutos para filtros
        $processedData = DashboardDataProcessor::processDataset($dataset);

        $dashboard = new DashboardDataService();
        $payload = $dashboard->build($dataset);

        $this->view('dashboard.index', [
            'title' => 'Relatórios — Desafio Técnico Agência Molla',
            'bodyClass' => 'p-4',
            'dashboardPayload' => $payload,
            'dashboardPayloadJson' => $payload->toJson(),
            'rawDataJson' => json_encode($processedData['allData'], JSON_UNESCAPED_UNICODE),
            'filtersJson' => json_encode($processedData['filters'], JSON_UNESCAPED_UNICODE),
            'radarDataJson' => json_encode($processedData['dashboard']['radar_data'], JSON_UNESCAPED_UNICODE),
            'historicoDataJson' => json_encode($processedData['dashboard']['historico_performance'], JSON_UNESCAPED_UNICODE),
            'uploadedFileName' => $useUploadedFile ? ($_SESSION['uploaded_file_name'] ?? basename($uploadedPath)) : null,
        ]);
    }

    public function debugCache(): void
    {
        header('Content-Type: application/json');

        // Verificar se há arquivo enviado na sessão
        $uploadedPath = $_SESSION['uploaded_file_path'] ?? null;
        $useUploadedFile = $uploadedPath && is_file($uploadedPath);

        if ($useUploadedFile) {
            $path = $uploadedPath;
        } else {
            $pathResolver = new SpreadsheetPathResolver();
            $path = $pathResolver->defaultPerformanceFile();
        }

        try {
            $dataset = (new PerformanceSheetReader())->loadFromPath($path);
        } catch (\Throwable) {
            $dataset = SpreadsheetDataset::empty($path);
        }

        $data = DashboardDataProcessor::processDataset($dataset);

        echo json_encode([
            'cache_status' => DashboardDataProcessor::getCacheStatus(),
            'data_structure' => [
                'allData_count' => count($data['allData'] ?? []),
                'filters' => [
                    'periods_count' => count($data['filters']['periods'] ?? []),
                    'categories_count' => count($data['filters']['categories'] ?? []),
                    'distributors_count' => count($data['filters']['distributors'] ?? []),
                    'groups_count' => count($data['filters']['groups'] ?? []),
                ],
                'dashboard' => [
                    'distributors_count' => count($data['dashboard']['distributors'] ?? []),
                    'categories_count' => count($data['dashboard']['categories'] ?? []),
                    'kpis_count' => count($data['dashboard']['kpis'] ?? []),
                ]
            ],
            'sample_data' => [
                'first_distributor' => ($data['dashboard']['distributors'] ?? [])[0] ?? null,
                'first_category' => ($data['dashboard']['categories'] ?? [])[0] ?? null,
                'kpis' => $data['dashboard']['kpis'] ?? [],
            ],
            'session' => [
                'uploaded_file' => $_SESSION['uploaded_file_path'] ?? null,
                'uploaded_file_name' => $_SESSION['uploaded_file_name'] ?? null,
                'uploaded_file_exists' => isset($_SESSION['uploaded_file_path']) && is_file($_SESSION['uploaded_file_path']),
                'using_uploaded_file' => $useUploadedFile,
                'current_file_path' => $path,
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
