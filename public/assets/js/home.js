document.addEventListener('DOMContentLoaded', () => {
    console.log('JavaScript carregado, inicializando...');

    const btnUpload = document.getElementById('btn-upload');
    const btnDashboard = document.getElementById('btn-dashboard');
    const btnTratar = document.getElementById('btn-tratar');
    const stepUpload = document.getElementById('step-upload');
    const stepOptions = document.getElementById('step-options');
    const fileInput = document.getElementById('file-input');

    console.log('Elementos encontrados:', {
        btnUpload: !!btnUpload,
        btnDashboard: !!btnDashboard,
        btnTratar: !!btnTratar,
        stepUpload: !!stepUpload,
        stepOptions: !!stepOptions,
        fileInput: !!fileInput
    });

    if (!btnUpload || !stepUpload || !stepOptions || !fileInput) {
        console.error('Elementos essenciais não encontrados!');
        return;
    }

    // Configurar o input file que já existe no HTML
    fileInput.addEventListener('change', function(e) {
        console.log('Arquivo selecionado:', e.target.files);
        const file = e.target.files[0];
        if (file) {
            handleFile(file);
        }
        // Reset do input para permitir seleção do mesmo arquivo novamente
        this.value = '';
    });

    console.log('Input file configurado');

    // Evento de clique na área de upload
    btnUpload.addEventListener('click', function(e) {
        console.log('Área de upload clicada');
        e.preventDefault();
        e.stopPropagation();

        console.log('Tentando abrir seletor de arquivo...');
        fileInput.click();
        console.log('fileInput.click() executado');
    });

    // Eventos de drag and drop
    btnUpload.addEventListener('dragover', (e) => {
        e.preventDefault();
        btnUpload.classList.add('drag-over');
    });

    btnUpload.addEventListener('dragleave', () => {
        btnUpload.classList.remove('drag-over');
    });

    btnUpload.addEventListener('drop', (e) => {
        e.preventDefault();
        btnUpload.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        console.log('Arquivo(s) solto(s):', files.length);
        if (files.length > 0) {
            handleFile(files[0]);
        }
    });

    // Eventos dos botões
    if (btnDashboard) {
        btnDashboard.addEventListener('click', () => {
            window.location.href = '/dashboard';
        });
    }

    if (btnTratar) {
        btnTratar.addEventListener('click', () => {
            window.location.href = '/tratar';
        });
    }

    function handleFileSelect(e) {
        console.log('handleFileSelect chamado (removido - usando inline)');
    }

    function handleFile(file) {
        console.log('Iniciando processamento do arquivo:', file.name, file.size, file.type);

        // Validar tamanho do arquivo (máximo 10MB)
        const maxSize = 10 * 1024 * 1024; // 10MB
        if (file.size > maxSize) {
            alert('Arquivo muito grande. O tamanho máximo permitido é 10MB.');
            return;
        }

        // Validar tipo de arquivo
        const allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
        const allowedExtensions = ['xlsx', 'xls'];
        const fileExtension = file.name.split('.').pop().toLowerCase();

        console.log('Verificando tipo de arquivo:', file.type, fileExtension);

        if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
            alert('Tipo de arquivo não permitido. Use apenas .xlsx ou .xls');
            return;
        }

        console.log('Arquivo validado, iniciando upload real...');

        // Mostrar loading
        btnUpload.innerHTML = `
            <div class="icon-box-upload mb-3 shadow">
                <i class="bi bi-hourglass-split text-white fs-3"></i>
            </div>
            <h5 class="fw-bold">Enviando arquivo...</h5>
            <p class="text-muted small">Aguarde enquanto processamos sua planilha</p>
        `;

        // Desabilitar botões durante upload
        setButtonsEnabled(false);

        // Criar FormData para enviar o arquivo
        const formData = new FormData();
        formData.append('file', file);

        // Fazer upload via fetch
        fetch('/upload', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Resposta do servidor:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Dados da resposta:', data);

            if (data.success) {
                console.log('Upload bem-sucedido');

                // Atualizar interface
                stepUpload.classList.add('d-none');
                stepOptions.classList.remove('d-none');

                // Atualizar nome do arquivo
                const fileNameElement = document.getElementById('uploaded-file-name');
                if (fileNameElement) {
                    fileNameElement.textContent = data.fileName || file.name;
                }

                // Habilitar botões
                setButtonsEnabled(true);
            } else {
                console.error('Erro no upload:', data.message);
                alert('Erro no upload: ' + (data.message || 'Erro desconhecido'));
                resetUploadArea();
                setButtonsEnabled(true);
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            alert('Erro ao enviar arquivo. Tente novamente.');
            resetUploadArea();
            setButtonsEnabled(true);
        });
    }

    function setButtonsEnabled(enabled) {
        if (btnDashboard) {
            btnDashboard.disabled = !enabled;
            btnDashboard.classList.toggle('disabled', !enabled);
        }
        if (btnTratar) {
            btnTratar.disabled = !enabled;
            btnTratar.classList.toggle('disabled', !enabled);
        }
    }

    function resetUploadArea() {
        btnUpload.innerHTML = `
            <div class="icon-box-upload mb-3 shadow">
                <i class="bi bi-upload text-white fs-3"></i>
            </div>
            <h5 class="fw-bold">Arraste sua planilha aqui</h5>
            <p class="text-muted small">ou clique para selecionar um arquivo</p>
            <div class="mt-4 text-muted small"><i class="bi bi-filetype-xlsx me-2"></i>Formatos aceitos: .xlsx, .xls</div>
        `;
    }
});
