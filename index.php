<?php
/**
 * index.php - С улучшенным масштабированием
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('BASE_PATH', __DIR__);
define('IMG_PATH', BASE_PATH . '/img');
define('OUTPUT_PATH', BASE_PATH . '/output');

// Создаем папки
foreach ([IMG_PATH, OUTPUT_PATH] as $dir) {
    if (!file_exists($dir)) mkdir($dir, 0777, true);
}

// ========== КОНФИГУРАЦИЯ ==========
$config = [
    'sprite_sizes' => [
        'sprite' => ['w' => 16, 'h' => 16],
        'object' => ['w' => 32, 'h' => 32],
        'tileSet' => ['w' => 16, 'h' => 16],
        'structure' => ['w' => 32, 'h' => 32],
        'machine' => ['w' => 48, 'h' => 48],
        'machineEffect' => ['w' => 32, 'h' => 32],
        'structureEffect' => ['w' => 32, 'h' => 32],
        'font' => ['w' => 8, 'h' => 12],
        'lfont' => ['w' => 16, 'h' => 16],
        'logo' => ['w' => 64, 'h' => 64],
        'stage' => ['w' => 32, 'h' => 32],
        'mainMenu' => ['w' => 32, 'h' => 32],
        'event' => ['w' => 32, 'h' => 32],
        'gameMain' => ['w' => 32, 'h' => 32],
        'default' => ['w' => 16, 'h' => 16],
    ]
];

// Подключаем класс парсера
if (!file_exists(__DIR__ . '/PMJParser.php')) {
    die('❌ Файл PMJParser.php не найден!');
}
require_once __DIR__ . '/PMJParser.php';

// ========== API ==========
if (isset($_GET['action']) && $_GET['action'] === 'getFiles') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    
    try {
        $files = glob(IMG_PATH . '/*.pmj');
        if (empty($files)) {
            echo json_encode([]);
            exit;
        }
        
        $results = [];
        foreach ($files as $file) {
            try {
                $parser = new PMJParser($file, $config, OUTPUT_PATH);
                $data = $parser->parse();
                $results[] = [
                    'name' => basename($file),
                    'success' => $data['success'] ?? false,
                    'count' => $data['count'] ?? 0,
                    'files' => $data['files'] ?? [],
                    'error' => $data['error'] ?? null
                ];
            } catch (Exception $e) {
                $results[] = [
                    'name' => basename($file),
                    'success' => false,
                    'count' => 0,
                    'files' => [],
                    'error' => $e->getMessage()
                ];
            }
        }
        echo json_encode($results);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ========== HTML ==========
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMJ Viewer</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#0a0e17;color:#e0e0e0;font-family:'Segoe UI',sans-serif;padding:15px}
        .header{background:#1a1a2e;padding:15px 20px;border-radius:12px;margin-bottom:15px;border:1px solid #0f3460;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
        .header h1{font-size:20px;color:#00d2ff}
        .header h1 span{color:#ff6b6b}
        .stats{color:#8899aa;font-size:13px}
        .stats strong{color:#00d2ff}
        .toolbar{background:#141c2b;padding:10px 15px;border-radius:8px;margin-bottom:15px;border:1px solid #1a2a3a;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
        .toolbar input{background:#0a0e17;border:1px solid #1a2a3a;color:#e0e0e0;padding:8px 12px;border-radius:6px;flex:1;min-width:150px;font-size:14px}
        .toolbar input:focus{outline:none;border-color:#00d2ff}
        .btn{background:#1a2a3a;border:1px solid #0f3460;color:#e0e0e0;padding:8px 16px;border-radius:6px;cursor:pointer;transition:0.3s;font-size:14px}
        .btn:hover{background:#0f3460;border-color:#00d2ff}
        .file{background:#141c2b;border-radius:12px;margin-bottom:15px;border:1px solid #1a2a3a;overflow:hidden}
        .file-header{padding:10px 15px;background:#1a1a2e;border-bottom:1px solid #1a2a3a;display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px}
        .file-header .name{font-weight:600;color:#00d2ff;font-size:14px}
        .badge{padding:2px 10px;border-radius:12px;font-size:10px;font-weight:600;text-transform:uppercase}
        .badge.ok{background:rgba(0,210,255,0.2);color:#00d2ff}
        .badge.err{background:rgba(255,107,107,0.2);color:#ff6b6b}
        .badge.info{background:rgba(255,255,255,0.1);color:#8899aa}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:10px;padding:15px}
        .item{background:#0a0e17;border-radius:8px;padding:8px;text-align:center;border:1px solid #1a2a3a;transition:0.3s;cursor:pointer}
        .item:hover{border-color:#00d2ff;transform:scale(1.05);box-shadow:0 0 20px rgba(0,210,255,0.2)}
        .item img{max-width:100%;max-height:70px;image-rendering:pixelated;display:block;margin:0 auto}
        .item .info{font-size:9px;color:#8899aa;margin-top:4px}
        
        /* Модальное окно */
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.92);z-index:1000;justify-content:center;align-items:center;cursor:pointer}
        .modal.active{display:flex}
        .modal-content{max-width:95%;max-height:95%;position:relative;background:#141c2b;padding:25px 30px;border-radius:16px;border:1px solid #0f3460;cursor:default;box-shadow:0 0 60px rgba(0,210,255,0.1)}
        .modal-content .image-wrapper{width:100%;height:70vh;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
        .modal-content .image-wrapper img{max-width:100%;max-height:100%;image-rendering:pixelated;display:block;transition:transform 0.15s ease;cursor:zoom-in}
        .modal-content .image-wrapper img.zoomed{cursor:zoom-out}
        .modal-content .info{color:#8899aa;font-size:13px;margin-top:12px;text-align:center;display:flex;justify-content:center;gap:20px;flex-wrap:wrap}
        .modal-content .info span{background:#0a0e17;padding:4px 12px;border-radius:6px}
        .modal-close{position:absolute;top:-12px;right:-12px;width:40px;height:40px;border-radius:50%;background:#ff6b6b;color:#fff;border:none;font-size:22px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:0.3s;box-shadow:0 0 20px rgba(255,107,107,0.3);z-index:20}
        .modal-close:hover{transform:scale(1.15);background:#ff4444}
        .modal-nav{position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,0.6);color:#fff;border:1px solid #0f3460;padding:20px 14px;font-size:28px;cursor:pointer;border-radius:8px;transition:0.3s;z-index:10}
        .modal-nav:hover{background:rgba(0,210,255,0.3);border-color:#00d2ff}
        .modal-nav.prev{left:10px}
        .modal-nav.next{right:10px}
        .modal-counter{position:absolute;top:10px;left:50%;transform:translateX(-50%);color:#8899aa;font-size:13px;background:rgba(0,0,0,0.8);padding:6px 16px;border-radius:20px;border:1px solid #1a2a3a;z-index:15}
        
        .zoom-controls{position:absolute;bottom:70px;left:50%;transform:translateX(-50%);display:flex;gap:8px;background:rgba(0,0,0,0.8);padding:8px 14px;border-radius:30px;border:1px solid #1a2a3a;z-index:15}
        .zoom-controls button{background:transparent;color:#e0e0e0;border:none;padding:6px 12px;font-size:16px;cursor:pointer;border-radius:6px;transition:0.3s;font-weight:bold}
        .zoom-controls button:hover{background:#0f3460;color:#00d2ff}
        .zoom-controls .zoom-level{color:#00d2ff;font-size:14px;padding:0 10px;min-width:60px;text-align:center;font-weight:bold}
        .zoom-controls .zoom-reset{font-size:14px}
        
        .loading,.no-results{text-align:center;padding:40px;color:#8899aa}
        .error-box{background:#2a0a0a;border:1px solid #ff6b6b;padding:15px;border-radius:8px;margin:15px;color:#ff6b6b}
        
        @media(max-width:600px){
            .header{flex-direction:column;align-items:stretch}
            .modal-content{padding:15px}
            .modal-nav{padding:12px 8px;font-size:20px}
            .modal-content .image-wrapper{height:50vh}
            .zoom-controls{bottom:55px;padding:6px 10px}
            .zoom-controls button{padding:4px 8px;font-size:14px}
        }
    </style>
</head>
<body>

<div class="header">
    <div><h1>📁 PMJ <span>Viewer</span></h1></div>
    <div class="stats">Всего: <strong id="total">0</strong> | Ресурсов: <strong id="resources">0</strong></div>
</div>

<div class="toolbar">
    <input type="text" id="search" placeholder="🔍 Поиск...">
    <button class="btn" onclick="location.reload()">🔄 Обновить</button>
</div>

<div id="container"><div class="loading">⏳ Загрузка...</div></div>

<!-- Модальное окно -->
<div class="modal" id="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()">✕</button>
        <button class="modal-nav prev" onclick="navImage(-1)">‹</button>
        <button class="modal-nav next" onclick="navImage(1)">›</button>
        <div class="modal-counter" id="modalCounter">1 / 1</div>
        
        <div class="image-wrapper" id="imageWrapper">
            <img id="modalImage" src="" alt="Увеличенное изображение" onclick="toggleZoom()">
        </div>
        
        <div class="zoom-controls">
            <button onclick="zoomOut()" title="Уменьшить">−</button>
            <span class="zoom-level" id="zoomLevel">100%</span>
            <button onclick="zoomIn()" title="Увеличить">+</button>
            <button class="zoom-reset" onclick="resetZoom()" title="Сбросить масштаб">⟲</button>
        </div>
        
        <div class="info" id="modalInfo">
            <span id="modalFile">📄 файл.pmj</span>
            <span id="modalResource">🖼 ресурс_000.png</span>
            <span id="modalSize">📐 0×0</span>
        </div>
    </div>
</div>

<script>
let allImages = [];
let currentImageIndex = 0;
let fileData = [];
let currentZoom = 1;
let zoomStep = 0.25;
let minZoom = 0.25;
let maxZoom = 10;

async function load() {
    const container = document.getElementById('container');
    container.innerHTML = '<div class="loading">⏳ Загрузка...</div>';
    
    try {
        const response = await fetch('?action=getFiles');
        const text = await response.text();
        
        if (!text.trim().startsWith('[') && !text.trim().startsWith('{')) {
            container.innerHTML = `
                <div class="error-box">
                    <strong>❌ Ошибка сервера:</strong><br>
                    <pre style="white-space:pre-wrap;font-size:12px;margin-top:10px;">${escapeHtml(text.substring(0, 500))}</pre>
                </div>
            `;
            return;
        }
        
        fileData = JSON.parse(text);
        
        if (fileData.error) {
            container.innerHTML = `<div class="error-box">❌ ${escapeHtml(fileData.error)}</div>`;
            return;
        }
        
        document.getElementById('total').textContent = fileData.length;
        document.getElementById('resources').textContent = fileData.reduce((s, f) => s + (f.success ? f.count : 0), 0);
        render(fileData);
        
    } catch (error) {
        container.innerHTML = `
            <div class="error-box">
                <strong>❌ Ошибка загрузки:</strong><br>
                ${escapeHtml(error.message)}
            </div>
        `;
        console.error('Error:', error);
    }
}

function render(items) {
    const search = document.getElementById('search').value.toLowerCase();
    const filtered = items.filter(f => f.name.toLowerCase().includes(search));
    
    if (filtered.length === 0) {
        document.getElementById('container').innerHTML = '<div class="no-results">📁 Нет файлов</div>';
        return;
    }
    
    allImages = [];
    for (const f of filtered) {
        if (f.success && f.files) {
            const base = f.name.replace('.pmj', '');
            for (const file of f.files) {
                allImages.push({
                    src: `output/${base}/${file}`,
                    name: file,
                    file: f.name
                });
            }
        }
    }
    
    let html = '';
    for (const f of filtered) {
        html += `<div class="file">
            <div class="file-header">
                <span class="name">📄 ${escapeHtml(f.name)}</span>
                <div>
                    <span class="badge ${f.success?'ok':'err'}">${f.success?'✅ OK':'❌ Error'}</span>
                    ${f.success ? `<span class="badge info">${f.count} ресурсов</span>` : ''}
                </div>
            </div>`;
        
        if (f.success && f.files && f.files.length > 0) {
            html += '<div class="grid">';
            const base = f.name.replace('.pmj', '');
            
            for (const file of f.files) {
                const src = `output/${base}/${file}`;
                const idx = allImages.findIndex(img => img.src === src);
                html += `
                    <div class="item" onclick="openModal(${idx >= 0 ? idx : 0})">
                        <img src="${src}" loading="lazy" alt="${file}">
                        <div class="info">${escapeHtml(file)}</div>
                    </div>
                `;
            }
            html += '</div>';
        } else if (!f.success) {
            html += `<div style="padding:15px;color:#ff6b6b;text-align:center;">⚠️ ${escapeHtml(f.error || 'Не удалось распарсить')}</div>`;
        }
        html += '</div>';
    }
    document.getElementById('container').innerHTML = html;
}

function openModal(index) {
    if (allImages.length === 0) return;
    currentImageIndex = Math.min(Math.max(0, index), allImages.length - 1);
    resetZoom();
    showImage(currentImageIndex);
    document.getElementById('modal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('modal').classList.remove('active');
    document.body.style.overflow = '';
    resetZoom();
}

function showImage(index) {
    const img = allImages[index];
    if (!img) return;
    
    const modalImg = document.getElementById('modalImage');
    modalImg.src = img.src;
    modalImg.className = '';
    modalImg.style.transform = 'scale(1)';
    
    document.getElementById('modalFile').textContent = `📄 ${img.file}`;
    document.getElementById('modalResource').textContent = `🖼 ${img.name}`;
    
    // Получаем размеры
    const tempImg = new Image();
    tempImg.onload = function() {
        document.getElementById('modalSize').textContent = `📐 ${this.width}×${this.height}`;
    };
    tempImg.src = img.src;
    
    document.getElementById('modalCounter').textContent = `${index + 1} / ${allImages.length}`;
    updateZoomDisplay();
}

function toggleZoom() {
    if (currentZoom > 1) {
        resetZoom();
    } else {
        zoomIn();
    }
}

function zoomIn() {
    if (currentZoom < maxZoom) {
        currentZoom = Math.min(currentZoom + zoomStep, maxZoom);
        applyZoom();
    }
}

function zoomOut() {
    if (currentZoom > minZoom) {
        currentZoom = Math.max(currentZoom - zoomStep, minZoom);
        applyZoom();
    }
}

function resetZoom() {
    currentZoom = 1;
    applyZoom();
}

function applyZoom() {
    const img = document.getElementById('modalImage');
    // Применяем масштаб через CSS transform
    img.style.transform = `scale(${currentZoom})`;
    // Меняем курсор при зуме
    img.className = currentZoom > 1 ? 'zoomed' : '';
    updateZoomDisplay();
}

function updateZoomDisplay() {
    document.getElementById('zoomLevel').textContent = Math.round(currentZoom * 100) + '%';
}

function navImage(direction) {
    let newIndex = currentImageIndex + direction;
    if (newIndex < 0) newIndex = allImages.length - 1;
    if (newIndex >= allImages.length) newIndex = 0;
    currentImageIndex = newIndex;
    resetZoom();
    showImage(currentImageIndex);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Закрытие по клику вне изображения
document.getElementById('modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Колесо мыши для масштабирования
document.getElementById('modalImage').addEventListener('wheel', function(e) {
    e.preventDefault();
    if (e.deltaY < 0) {
        zoomIn();
    } else {
        zoomOut();
    }
});

// Закрытие по Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
    if (e.key === 'ArrowLeft') navImage(-1);
    if (e.key === 'ArrowRight') navImage(1);
    if (e.key === '=' || e.key === '+') { e.preventDefault(); zoomIn(); }
    if (e.key === '-') { e.preventDefault(); zoomOut(); }
    if (e.key === '0') { e.preventDefault(); resetZoom(); }
});

// Поиск
document.getElementById('search').addEventListener('input', function() {
    render(fileData);
});

// Загрузка
load();
</script>
</body>
</html>