<?php
// ============================================================
// backend/includes/ipfs.php
// ============================================================

require_once __DIR__ . '/../config/db.php';

function ipfsUpload(string $filePath): array {
    if (!file_exists($filePath)) {
        return ['success' => false, 'message' => 'File not found: ' . $filePath];
    }
    $ch    = curl_init(IPFS_API . '/add');
    $cFile = new CURLFile($filePath, 'text/html', basename($filePath));
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ['file' => $cFile],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$response) {
        return ['success' => false, 'message' => 'IPFS upload failed. Is the IPFS daemon running?'];
    }
    $data = json_decode($response, true);
    return ['success' => true, 'cid' => $data['Hash'], 'url' => IPFS_GATEWAY . $data['Hash']];
}

function generateCertificateHTML(array $cert, array $student): string {
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }
    $outputPath = UPLOAD_PATH . $cert['certificate_id'] . '.html';
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
  body{font-family:"Times New Roman",serif;margin:0;}
  .cert{width:800px;margin:40px auto;padding:60px;border:12px double #8B6914;text-align:center;background:#fffef0;}
  .uni-name{font-size:28px;font-weight:bold;color:#1a1a6e;letter-spacing:2px;}
  .cert-title{font-size:36px;color:#8B6914;margin:20px 0;font-style:italic;}
  .student-name{font-size:30px;font-weight:bold;color:#1a1a6e;border-bottom:2px solid #8B6914;display:inline-block;padding:4px 30px;margin:10px 0;}
  .details{font-size:15px;color:#444;margin:6px 0;}
  .hash-box{font-family:monospace;font-size:10px;color:#999;word-break:break-all;background:#f5f5f5;padding:8px;border-radius:4px;margin-top:20px;}
  .cert-id{font-size:13px;font-weight:bold;color:#8B6914;margin-top:16px;}
  .signatures{display:flex;justify-content:space-around;margin-top:50px;font-size:13px;}
  .sig-line{border-top:1px solid #333;width:160px;padding-top:6px;text-align:center;}
</style></head><body><div class="cert">
  <div class="uni-name">LANDMARK UNIVERSITY</div>
  <div style="font-size:14px;color:#555;margin:4px 0 20px;">Omu-Aran, Kwara State, Nigeria</div>
  <div class="cert-title">Certificate of Achievement</div>
  <p style="font-size:16px;color:#333;">This is to certify that</p>
  <div class="student-name">' . htmlspecialchars($student['full_name']) . '</div>
  <p class="details">Matriculation Number: <strong>' . htmlspecialchars($student['matric_number']) . '</strong></p>
  <p class="details">Has successfully completed the requirements for the award of</p>
  <p class="details"><strong>Bachelor of Science in ' . htmlspecialchars($student['department']) . '</strong></p>
  <p class="details">with <strong>' . htmlspecialchars($student['degree_class']) . '</strong></p>
  <p class="details">Faculty of ' . htmlspecialchars($student['faculty']) . ' &nbsp;|&nbsp; Graduation Year: ' . htmlspecialchars($student['graduation_year']) . '</p>
  <div class="cert-id">Certificate ID: ' . htmlspecialchars($cert['certificate_id']) . '</div>
  <div class="hash-box">SHA-256: ' . htmlspecialchars($cert['sha256_hash']) . '<br>IPFS CID: ' . htmlspecialchars($cert['ipfs_cid']) . '</div>
  <div class="signatures">
    <div class="sig-line">Vice Chancellor</div>
    <div class="sig-line">Registrar</div>
    <div class="sig-line">Head of Department</div>
  </div>
</div></body></html>';
    file_put_contents($outputPath, $html);
    return $outputPath;
}
