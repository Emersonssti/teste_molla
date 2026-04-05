<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Services\Dashboard\DashboardDataProcessor;

final class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('home.index', [
            'title' => 'Desafio Técnico Agência Molla - Importar',
            'bodyClass' => '',
        ]);
    }

    public function upload(): void
    {
        try {
            // Verificar se um arquivo foi enviado
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Nenhum arquivo foi enviado ou houve erro no upload'
                ]);
                return;
            }

            $file = $_FILES['file'];
            $fileName = $file['name'];
            $fileTmpPath = $file['tmp_name'];
            $fileSize = $file['size'];

            // Validar tamanho do arquivo (máximo 10MB)
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($fileSize > $maxSize) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Arquivo muito grande. O tamanho máximo permitido é 10MB.'
                ]);
                return;
            }

            // Validar tipo de arquivo
            $allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
            $allowedExtensions = ['xlsx', 'xls'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($file['type'], $allowedTypes) && !in_array($fileExtension, $allowedExtensions)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Tipo de arquivo não permitido. Use apenas .xlsx ou .xls'
                ]);
                return;
            }

            // Criar nome único para o arquivo
            $uniqueFileName = 'upload_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $fileName);
            $uploadPath = BASE_PATH . '/storage/uploads/' . $uniqueFileName;

            // Criar diretório se não existir
            $uploadDir = dirname($uploadPath);
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Mover arquivo para o diretório de uploads
            if (!move_uploaded_file($fileTmpPath, $uploadPath)) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao salvar o arquivo no servidor'
                ]);
                return;
            }

            // Salvar informações na sessão
            $_SESSION['uploaded_file_path'] = $uploadPath;
            $_SESSION['uploaded_file_name'] = $fileName;

            // Limpar cache do dashboard
            DashboardDataProcessor::clearCache();

            echo json_encode([
                'success' => true,
                'message' => 'Arquivo enviado com sucesso',
                'fileName' => $fileName
            ]);

        } catch (\Throwable $e) {
            error_log('Erro no upload: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno do servidor: ' . $e->getMessage()
            ]);
        }
    }
}
