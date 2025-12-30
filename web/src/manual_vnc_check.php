<?php
/**
 * Page de test VNC isolée (Hardcoded)
 * Accès : http://192.168.10.248/manual_vnc_check.php
 */

$vncHost = $_SERVER['SERVER_NAME'];
$vncPort = 8085;
$vncToken = 'test'; 

// URL 1: Standard
$url1 = "http://{$vncHost}:{$vncPort}/vnc_lite.html?token={$vncToken}&autoconnect=true&resize=scale";

// URL 2: Explicit Path with websockify
$url2 = "http://{$vncHost}:{$vncPort}/vnc_lite.html?path=websockify%3Ftoken%3D{$vncToken}&autoconnect=true&resize=scale";

// URL 3: Explicit Path without websockify
$url3 = "http://{$vncHost}:{$vncPort}/vnc_lite.html?path=%3Ftoken%3D{$vncToken}&autoconnect=true&resize=scale";

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>MANUAL VNC CHECK</title>
    <style>
        body { background: #333; color: white; font-family: sans-serif; text-align: center; }
        .test-block { margin: 20px auto; padding: 20px; background: #222; border: 1px solid #555; width: 80%; }
        h3 { color: #aaf; }
        iframe { width: 100%; height: 400px; border: 2px solid #555; background: #000; }
        button { padding: 10px 20px; font-size: 16px; cursor: pointer; background: #4caf50; border: none; color: white; margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>TEST URL VARIATIONS</h1>
    <p>Le serveur dit "Token not present", ce qui suggère qu'il ne REÇOIT pas le token correctement.</p>
    <p>Essayons différentes façons de passer le token dans l'URL.</p>

    <div class="test-block">
        <h3>TEST 1 : Format Standard (Actuel)</h3>
        <p>URL: <code>.../vnc_lite.html?token=test</code></p>
        <button onclick="document.getElementById('frame1').src='<?= $url1 ?>'">Charger Test 1</button><br>
        <iframe id="frame1" src=""></iframe>
    </div>

    <div class="test-block">
        <h3>TEST 2 : Format "Path websockify"</h3>
        <p>URL: <code>.../vnc_lite.html?path=websockify?token=test</code></p>
        <button onclick="document.getElementById('frame2').src='<?= $url2 ?>'">Charger Test 2</button><br>
        <iframe id="frame2" src=""></iframe>
    </div>

    <div class="test-block">
        <h3>TEST 3 : Format "Path simple"</h3>
        <p>URL: <code>.../vnc_lite.html?path=?token=test</code></p>
        <button onclick="document.getElementById('frame3').src='<?= $url3 ?>'">Charger Test 3</button><br>
        <iframe id="frame3" src=""></iframe>
    </div>

</body>
</html>
