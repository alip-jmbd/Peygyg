<?php
$token = 'nfp_s2LgNC9ssKenaWrmixK5qNTS2xEZt4u25f8c';
$apiUrl = 'https://api.netlify.com/api/v1/sites';
$message = '';
$resultUrl = '';
$messageType = '';

if (!class_exists('ZipArchive')) {
    $messageType = 'error';
    $message = "<strong>FATAL ERROR: Ekstensi PHP Zip belum aktif.</strong><br>Runtime serverless ini tidak memiliki 'ZipArchive'. Pastikan Anda telah menambahkan <code>extension = zip</code> di file <code>api/php.ini</code>.";
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['webfile'])) {
    if ($_FILES['webfile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['webfile']['tmp_name'];
        $fileName = $_FILES['webfile']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedfileExtensions = ['zip', 'html'];
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $deployFilePath = $fileTmpPath;
            $tempZipPath = '';

            if ($fileExtension === 'html') {
                $tempZipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('deploy_', true) . '.zip';
                $zip = new ZipArchive();
                if ($zip->open($tempZipPath, ZipArchive::CREATE) === TRUE) {
                    $zip->addFromString('index.html', file_get_contents($fileTmpPath));
                    $zip->close();
                    $deployFilePath = $tempZipPath;
                } else {
                    $message = 'Gagal membuat file ZIP sementara dari file HTML.';
                    $messageType = 'error';
                }
            }
            
            if (empty($message)) {
                $fileContents = file_get_contents($deployFilePath);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $apiUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContents);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/zip',
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $responseData = json_decode($response, true);

                if ($httpCode == 201 && isset($responseData['ssl_url'])) {
                    $message = 'DEPLOYMENT SUKSES!';
                    $messageType = 'success';
                    $resultUrl = $responseData['ssl_url'];
                } else {
                    $errorMsg = isset($responseData['message']) ? htmlspecialchars($responseData['message']) : 'Terjadi kesalahan yang tidak diketahui.';
                    $message = 'DEPLOYMENT GAGAL. Response: ' . $errorMsg;
                    $messageType = 'error';
                }
            }
            if ($tempZipPath && file_exists($tempZipPath)) {
                unlink($tempZipPath);
            }
        } else {
            $message = 'Format file tidak diizinkan. Harap unggah file .zip atau .html.';
            $messageType = 'error';
        }
    } else {
        $message = 'Terjadi kesalahan saat mengunggah file. Kode error: ' . $_FILES['webfile']['error'];
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prompt Engineer - Deployer</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,700;0,900;1,700&family=Roboto+Mono:wght@500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #F4F4F4;
            --primary-color: #0D0D0D;
            --accent-gradient: linear-gradient(135deg, #4A3AFF, #B58DFF);
            --success-color: #A7F3D0;
            --error-color: #FECACA;
            --border-width: 3px;
            --shadow-offset: 6px;
            --border-radius: 8px;
            --smooth-ease: cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-color);
            color: var(--primary-color);
            font-family: 'Archivo', sans-serif;
            display: grid;
            place-items: center;
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            width: 100%;
            max-width: 550px;
            background-color: white;
            border: var(--border-width) solid var(--primary-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-offset) var(--shadow-offset) 0px var(--primary-color);
            padding: clamp(1.5rem, 5vw, 2.5rem);
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 2rem;
            transition: all 0.3s var(--smooth-ease);
        }
        .header {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.25rem;
        }
        .logo { width: 80px; height: auto; }
        .header h1 {
            font-weight: 900;
            font-size: clamp(1.8rem, 8vw, 2.3rem);
            line-height: 1.1;
            text-transform: uppercase;
            letter-spacing: -1px;
        }
        .header h1 .highlight {
            background: var(--accent-gradient);
            color: transparent;
            -webkit-background-clip: text;
            background-clip: text;
        }
        .deploy-form { width: 100%; }
        .file-upload-wrapper {
            position: relative;
            width: 100%;
            border: var(--border-width) dashed var(--primary-color);
            border-radius: var(--border-radius);
            padding: 2.5rem 1.5rem;
            cursor: pointer;
            transition: all 0.3s var(--smooth-ease);
            background-color: #fafafa;
        }
        .file-upload-wrapper:hover {
            border-color: #4A3AFF;
            transform: translateY(-3px) scale(1.01);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .file-upload-wrapper input[type="file"] { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .file-upload-text {
            font-family: 'Roboto Mono', monospace;
            font-size: 1rem;
            pointer-events: none;
        }
        .file-upload-text span {
            font-weight: 700;
            text-decoration: underline;
            text-decoration-style: wavy;
            color: #4A3AFF;
        }
        #file-name {
            margin-top: 1rem;
            font-family: 'Roboto Mono', monospace;
            font-weight: 500;
            font-size: 0.9rem;
            color: #555;
            min-height: 1.2em;
            word-break: break-all;
        }
        .btn {
            font-family: 'Archivo', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            text-transform: uppercase;
            color: var(--primary-color);
            background-color: white;
            border: var(--border-width) solid var(--primary-color);
            border-radius: var(--border-radius);
            padding: 0.8rem 2rem;
            cursor: pointer;
            box-shadow: 4px 4px 0px var(--primary-color);
            transition: all .2s var(--smooth-ease);
            display: block;
            width: 100%;
            margin-top: 2rem;
        }
        .btn:hover {
            transform: translate(-3px, -3px);
            box-shadow: calc(var(--shadow-offset) + 1px) calc(var(--shadow-offset) + 1px) 0px var(--primary-color);
            background-color: #fffbe8;
        }
        .btn:active { transform: translate(4px, 4px); box-shadow: none; }
        .loader {
            border: 4px solid #e0e0e0;
            border-radius: 50%;
            border-top: 4px solid var(--primary-color);
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            display: none;
            margin: 2rem auto 0;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        #result-box {
            margin-top: 0.5rem;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            border: var(--border-width) solid var(--primary-color);
            text-align: left;
            display: none;
            flex-direction: column;
            gap: 1rem;
            animation: fadeIn 0.4s var(--smooth-ease);
            box-shadow: var(--shadow-offset) var(--shadow-offset) 0px var(--primary-color);
        }
        #result-box.visible { display: flex; }
        #result-box.success { background-color: var(--success-color); }
        #result-box.error { background-color: var(--error-color); }
        
        #result-box p { font-family: 'Roboto Mono', monospace; font-size: 1rem; line-height: 1.5; }
        #result-box strong { font-family: 'Archivo', sans-serif; font-weight: 900; }
        #result-box code { background: rgba(0,0,0,0.08); padding: 0.2rem 0.4rem; border-radius: 4px; border: 1px solid rgba(0,0,0,0.1);}
        #result-box a {
            font-family: 'Roboto Mono', monospace;
            font-weight: 500;
            color: var(--primary-color);
            background: rgba(255, 255, 255, 0.7);
            padding: 0.5rem 1rem;
            border: 2px solid var(--primary-color);
            border-radius: 4px;
            word-break: break-all;
            text-align: center;
            transition: all 0.2s var(--smooth-ease);
        }
        #result-box a:hover { background: white; transform: translateY(-2px); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <img src="https://files.catbox.moe/51i7nr.png" alt="Prompt Engineer Logo" class="logo">
            <h1>Prompt Engineer<br><span class="highlight">Deployer</span></h1>
        </header>

        <form action="" method="post" enctype="multipart/form-data" class="deploy-form" id="deploy-form">
            <div class="file-upload-wrapper">
                <input type="file" name="webfile" id="webfile" accept=".zip,.html" required>
                <div class="file-upload-text">
                    <p>Seret file ke sini atau <span>pilih file</span></p>
                    <p id="file-name"></p>
                </div>
            </div>
            <button type="submit" class="btn" id="submit-btn">Deploy</button>
            <div class="loader" id="loader"></div>
        </form>

        <?php if (!empty($message)): ?>
        <div id="result-box" class="visible <?php echo htmlspecialchars($messageType); ?>">
            <p><?php echo $message; ?></p>
            <?php if (!empty($resultUrl)): ?>
            <a href="<?php echo htmlspecialchars($resultUrl); ?>" target="_blank" rel="noopener noreferrer">Buka Website</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <script>
        const fileInput = document.getElementById('webfile');
        const fileNameDisplay = document.getElementById('file-name');
        const form = document.getElementById('deploy-form');
        const submitBtn = document.getElementById('submit-btn');
        const loader = document.getElementById('loader');
        fileInput.addEventListener('change', () => { fileNameDisplay.textContent = fileInput.files.length > 0 ? fileInput.files[0].name : ''; });
        form.addEventListener('submit', () => { if (fileInput.files.length > 0) { submitBtn.style.display = 'none'; loader.style.display = 'block'; } });
    </script>
</body>
</html>
