<?php
session_start();

/**
 * Normalise en UTF-8 et met en minuscules en gérant les caractères accentués.
 */
function normalizeToLowerUtf8(string $text): string
{
    // S'assurer de l'encodage UTF-8
    $text = mb_convert_encoding($text, 'UTF-8', 'auto');

    // Normalisation Unicode en NFC si disponible
    if (extension_loaded('intl') && class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($text, Normalizer::FORM_C);
        if ($normalized !== false) {
            $text = $normalized;
        }
    }

    // Minuscules multibytes
    $text = mb_strtolower($text, 'UTF-8');

    return $text;
}

/**
 * Extraction du texte via pdftotext (Poppler).
 * Retourne chaîne vide en cas d'échec.
 */
function extractTextWithPoppler(string $pdfPath): string
{
    $outputTxt = $pdfPath . '.txt';
    // -layout conserve la mise en page un peu mieux
    $cmd = 'pdftotext -layout ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($outputTxt);
    exec($cmd, $out, $rv);

    if (!file_exists($outputTxt)) {
        return '';
    }

    $content = file_get_contents($outputTxt);
    // on peut supprimer le fichier temporaire si souhaité
    @unlink($outputTxt);
    return $content === false ? '' : $content;
}

/**
 * Analyse du PDF : extraction, normalisation, tokenisation, filtrage stopwords, comptage.
 * Retourne [ $countsAssocArray, $totalFilteredWords ]
 */
function analysePDF(string $filePath): array
{
    // 1) Extraire le texte (Poppler)
    $raw = extractTextWithPoppler($filePath);
    if ($raw === '') {
        throw new Exception("Impossible d'extraire le texte du PDF (pdftotext a échoué).");
    }

    // 2) Normaliser et mettre en minuscules (UTF-8 safe)
    $text = normalizeToLowerUtf8($raw);

    // 3) Nettoyage : garder lettres (incl. accents), chiffres, apostrophe, trait d'union et espaces
    // Utilise \p{L} (lettres Unicode) et \p{N} (chiffres)
    $text = preg_replace("/[^\p{L}\p{N}'\- \t\r\n]/u", ' ', $text);

    // 4) Split en mots (enlever vides)
    $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

    // 5) Stopwords (articles, pronoms, prépositions, mots très fréquents)
    $stopwords = [
        '0','1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40','41','42','43','44','45','46','47','48','49','50','51','52','53','54','55','56','57','58','59','60','61','62','63','64','65','66','67','68','69','70','71','72','73','74','75','76','77','78','79','80','81','82','83','84','85','86','87','88','89','90','91','92','93','94','95','96','97','98','99','100','101','102','103','104','105','106','107','108','109','110','111','112','113','114','115','116','117','118','119','120','121','122','123','124','125','126','127','128','129','130','131','132','133','134','135','136','137','138','139','140','141','142','143','144','145','146','147','148','149','150','151','152','153','154','155','156','157','158','159','160','161','162','163','164','165','166','167','168','169','170','171','172','173','174','175','176','177','178','179','180','181','182','183','184','185','186','187','188','189','190','191','192','193','194','195','196','197','198','199','200','201','202','203','204','205','206','207','208','209','210','211','212','213','214','215','216','217','218','219','220','221','222','223','224','225','226','227','228','229','230','231','232','233','234','235','236','237','238','239','240','241','242','243','244','245','246','247','248','249','250','251','252','253','254','255','256','257','258','259','260','261','262','263','264','265','266','267','268','269','270','271','272','273','274','275','276','277','278','279','280','281','282','283','284','285','286','287','288','289','290','291','292','293','294','295','296','297','298','299','300','301','302','303','304','305','306','307','308','309','310','311','312','313','314','315','316','317','318','319','320','321','322','323','324','325','326','327','328','329','330','331','332','333','334',
        'le','la','les','de','des','du','un','une','et','en','dans','que','qui','pour','par','a','au','aux',
        'ce','ces','se','sa','son','ses','sur','avec','sans','ne','pas','plus','ou','où','comme','mais',
        'on','il','elle','ils','elles','nous','vous','leur','leurs','y','à','d','l','c','j','m','t','qu',
        'aujourd','hui','été','être','fait','faire','dont','sera','ainsi','tout','tous','chaque',
        'moins','même','autre','peut','sont','sous','entre','vers','après','avant','pendant',
        'si','donc','car','cela','cet','cette','ces','vos','mes','tes','nos','leurs','ta','ma','mon',
        'est','ceux','n','lui','s','tu','on','ont','avons','je','alors','quand','certes','là','puis','votre','celui','celle',
        'dit','ton','ni','toi','moi','voilà','te','point','seront','très','afin','deux','croient','notre','dire','avez','descendre','part',
        'non','rien','auront','auprès','ô','e','aussi','ayant','était','eux','disant','ai','étant','étaient','suis','selon','dit','toute','toutes','chapitre','fut','v','choses','chose','c\'est','dès','hors','quelles','quels','laquelle','lequel','lesquelles','lesquels','lorsque','lorsqu\'','quelque','quelques','-','qu\'il','me','avait','devant','contre'
    ];

    // Normaliser stopwords (au cas où) pour correspondre au texte déjà normalisé
    $stopwords = array_unique(array_map('normalizeToLowerUtf8', $stopwords));

    // 6) Filtrage + comptage
    $filtered = array_filter($words, function ($w) use ($stopwords) {
        return $w !== '' && !in_array($w, $stopwords, true);
    });

    $counts = array_count_values($filtered);
    arsort($counts);

    return [$counts, count($filtered)];
}

// --- Remise à zéro de la session ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_session'])) {
    unset($_SESSION['frequencies'], $_SESSION['totalWords'], $_SESSION['pdfName']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// --- Si nouveau upload, on supprime l'ancienne session de résultats ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file']) && !empty($_FILES['pdf_file']['name'])) {
    unset($_SESSION['frequencies'], $_SESSION['totalWords'], $_SESSION['pdfName']);
}

// --- Traitement upload PDF ---
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file']) && !empty($_FILES['pdf_file']['tmp_name'])) {
    $tmp = $_FILES['pdf_file']['tmp_name'];
    $name = basename($_FILES['pdf_file']['name']);
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $dest = $uploadDir . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        $errorMsg = "Erreur lors du téléversement du fichier.";
    } else {
        try {
            [$counts, $totalFiltered] = analysePDF($dest);
            // Stocker en session pour réutilisation lors de la recherche
            $_SESSION['frequencies'] = $counts;
            $_SESSION['totalWords'] = $totalFiltered;
            $_SESSION['pdfName'] = $name;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }
    }
}

// --- Récupérer depuis session si disponible (pour recherche sans re-upload) ---
$frequencies = $_SESSION['frequencies'] ?? [];
$totalWords = $_SESSION['totalWords'] ?? 0;
$pdfName = $_SESSION['pdfName'] ?? null;

// --- Recherche d'un mot (utilise les fréquences en session) ---
$searchResult = null;
$searchedWordDisplay = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_word']) && $frequencies) {
    $rawSearch = trim($_POST['search_word']);
    $normalizedSearch = normalizeToLowerUtf8($rawSearch);
    // nettoyer la recherche comme lors de l'analyse
    $normalizedSearch = preg_replace("/[^\p{L}\p{N}'\-]/u", '', $normalizedSearch);

    $searchResult = $frequencies[$normalizedSearch] ?? 0;
    $searchedWordDisplay = $rawSearch;
}

// --- Téléchargement CSV (optionnel) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_csv']) && $frequencies) {
    $filename = 'frequence_mots.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    // BOM pour faciliter ouverture sous Excel (Windows)
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'mot', 'occurrence']);
    $i = 1;
    foreach ($frequencies as $word => $count) {
        fputcsv($out, [$i++, $word, $count]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Analyseur de PDF - Fréquence des mots</title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Crimson+Text:ital,wght@0,400;0,600;1,400&display=swap');

* { box-sizing: border-box; }

body { 
    font-family: 'Crimson Text', serif;
    background: linear-gradient(rgba(20, 15, 10, 0.85), rgba(40, 30, 20, 0.9)), 
                url('https://images.unsplash.com/photo-1481627834876-b7833e8f5570?q=80&w=2028') center/cover fixed;
    margin: 0; 
    padding: 30px;
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #e8dcc4;
    min-height: 100vh;
}

.container {
    width: 100%;
    max-width: 900px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

h1 { 
    font-family: 'Cinzel', serif;
    font-size: 2.2em; 
    margin-bottom: 10px;
    text-align: center;
    color: #f4e4c1;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
    letter-spacing: 2px;
    font-weight: 700;
}

form { 
    background: linear-gradient(135deg, rgba(61, 43, 31, 0.95), rgba(82, 61, 46, 0.95));
    padding: 25px; 
    border-radius: 8px;
    border: 3px solid #8b7355;
    box-shadow: 0 8px 32px rgba(0,0,0,0.6),
                inset 0 1px 0 rgba(255,255,255,0.1);
    width: 100%;
    max-width: 420px;
    margin-bottom: 30px;
    position: relative;
}

form::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(45deg, #8b7355, #a0826d);
    border-radius: 8px;
    z-index: -1;
    opacity: 0.3;
}

label {
    color: #f4e4c1;
    font-size: 1.1em;
    font-weight: 600;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
}

input[type="file"], input[type="text"] { 
    width: 100%;
    margin-bottom: 12px;
    margin-top: 8px;
    padding: 10px;
    border: 2px solid #8b7355;
    border-radius: 4px;
    background: rgba(242, 234, 211, 0.95);
    color: #2c1810;
    font-family: 'Crimson Text', serif;
    font-size: 1em;
}

input[type="file"]::file-selector-button {
    background: linear-gradient(135deg, #6b5445, #8b7355);
    color: #f4e4c1;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-family: 'Cinzel', serif;
    margin-right: 10px;
}

input[type="file"]::file-selector-button:hover {
    background: linear-gradient(135deg, #8b7355, #a0826d);
}

input[type="submit"], button { 
    background: linear-gradient(135deg, #6b5445, #8b7355);
    color: #f4e4c1; 
    border: 2px solid #a0826d;
    padding: 12px 24px; 
    border-radius: 5px; 
    cursor: pointer;
    font-size: 15px;
    font-family: 'Cinzel', serif;
    font-weight: 600;
    letter-spacing: 1px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.4);
    transition: all 0.3s ease;
}

input[type="submit"]:hover, button:hover { 
    background: linear-gradient(135deg, #8b7355, #a0826d);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.6);
}

.reset-btn {
    background: linear-gradient(135deg, #7a3e3e, #9d4f4f);
    border-color: #b06060;
    margin-left: 10px;
}

.reset-btn:hover {
    background: linear-gradient(135deg, #9d4f4f, #b06060);
}

table { 
    border-collapse: collapse; 
    width: 100%;
    max-width: 800px;
    background: linear-gradient(135deg, rgba(61, 43, 31, 0.98), rgba(82, 61, 46, 0.98));
    border-radius: 8px; 
    overflow: hidden; 
    box-shadow: 0 8px 32px rgba(0,0,0,0.6);
    margin: 20px 0;
    border: 3px solid #8b7355;
}

th, td { 
    border: 1px solid #8b7355; 
    padding: 12px 16px; 
    text-align: left;
    color: #e8dcc4;
}

th { 
    background: linear-gradient(135deg, #4a3428, #5d4434);
    font-family: 'Cinzel', serif;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-size: 0.95em;
    color: #f4e4c1;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
}

tr:nth-child(even) { 
    background: rgba(82, 61, 46, 0.4);
}

tr:hover {
    background: rgba(107, 84, 69, 0.5);
    transition: background 0.3s ease;
}

.info { 
    margin-bottom: 20px;
    text-align: center;
    width: 100%;
    background: linear-gradient(135deg, rgba(61, 43, 31, 0.9), rgba(82, 61, 46, 0.9));
    padding: 15px 20px;
    border-radius: 8px;
    border: 2px solid #8b7355;
    box-shadow: 0 4px 16px rgba(0,0,0,0.5);
    color: #f4e4c1;
    font-size: 1.1em;
}

.info strong {
    color: #d4af37;
    font-family: 'Cinzel', serif;
}

.download-btn { 
    background: linear-gradient(135deg, #4a6b4a, #5d8a5d);
    color: #f4e4c1;
    padding: 12px 20px;
    border-radius: 5px;
    text-decoration: none;
    border: 2px solid #6fa36f;
    cursor: pointer;
    font-size: 15px;
    font-family: 'Cinzel', serif;
    font-weight: 600;
    box-shadow: 0 4px 8px rgba(0,0,0,0.4);
    transition: all 0.3s ease;
}

.download-btn:hover { 
    background: linear-gradient(135deg, #5d8a5d, #6fa36f);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.6);
}

.error { 
    color: #ffcccc; 
    margin-bottom: 15px; 
    background: linear-gradient(135deg, rgba(122, 62, 62, 0.9), rgba(157, 79, 79, 0.9));
    padding: 15px; 
    border-radius: 8px;
    border: 2px solid #b06060;
    width: 100%;
    max-width: 420px;
    text-align: center;
    box-shadow: 0 4px 16px rgba(0,0,0,0.5);
    font-weight: 600;
}

#scrollTopBtn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: linear-gradient(135deg, #6b5445, #8b7355);
    color: #f4e4c1;
    border: 2px solid #a0826d;
    border-radius: 50%;
    width: 55px;
    height: 55px;
    font-size: 24px;
    cursor: pointer;
    display: none;
    box-shadow: 0 6px 16px rgba(0,0,0,0.6);
    z-index: 1000;
    transition: all 0.3s ease;
    font-family: serif;
}

#scrollTopBtn:hover {
    background: linear-gradient(135deg, #8b7355, #a0826d);
    transform: scale(1.15);
}

#scrollTopBtn.show {
    display: block;
    animation: fadeIn 0.3s;
}

.loader-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(20, 15, 10, 0.95);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}

.loader-overlay.active {
    display: flex;
}

.loader-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(61, 43, 31, 0.95), rgba(82, 61, 46, 0.95));
    padding: 40px;
    border-radius: 12px;
    border: 3px solid #8b7355;
    box-shadow: 0 8px 32px rgba(0,0,0,0.8);
}

.loader {
    border: 8px solid rgba(244, 228, 193, 0.3);
    border-top: 8px solid #d4af37;
    border-radius: 50%;
    width: 70px;
    height: 70px;
    animation: spin 1s linear infinite;
}

.loader-text {
    color: #f4e4c1;
    margin-top: 25px;
    font-size: 1.2em;
    text-align: center;
    font-family: 'Cinzel', serif;
    letter-spacing: 1px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.button-container {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin: 15px 0;
}

.table-header {
    width: 100%;
    max-width: 800px;
    text-align: center;
    margin-bottom: 15px;
}

.table-header form {
    display: inline-block;
}

/* Décoration ornementale */
h1::before,
h1::after {
    content: '❦';
    margin: 0 15px;
    color: #d4af37;
    font-size: 0.8em;
}
</style>
</head>

<body>

<!-- Animation de chargement -->
<div class="loader-overlay" id="loaderOverlay">
    <div class="loader-container">
        <div class="loader"></div>
        <div class="loader-text">Analyse du PDF en cours...</div>
    </div>
</div>

<!-- Bouton retour en haut -->
<button id="scrollTopBtn" title="Retour en haut">↑</button>

<div class="container">
    <h1>Analyseur de PDF - Fréquence des mots</h1>

    <?php if ($errorMsg): ?>
        <div class="error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <!-- Upload form -->
    <form method="post" enctype="multipart/form-data" id="uploadForm">
        <label for="pdf_file">Choisissez un fichier PDF :</label><br>
        <input type="file" name="pdf_file" id="pdf_file" accept=".pdf" required><br>
        <div class="button-container">
            <input type="submit" value="Analyser le PDF">
            <?php if ($frequencies): ?>
                <button type="button" class="reset-btn" onclick="if(confirm('Voulez-vous vraiment réinitialiser toutes les données ?')) document.getElementById('resetForm').submit();">Remise à zéro</button>
            <?php endif; ?>
        </div>
    </form>

    <!-- Formulaire caché pour la remise à zéro -->
    <form method="post" id="resetForm" style="display:none;">
        <input type="hidden" name="reset_session" value="1">
    </form>

    <?php if ($frequencies): ?>
        <div class="info">
            <strong>Résultats pour :</strong> <?= htmlspecialchars($pdfName) ?><br>
            <strong>Total des mots (hors stopwords) :</strong> <?= number_format($totalWords, 0, ',', ' ') ?>
        </div>

        <!-- Recherche form -->
        <form method="post" style="margin-bottom:18px;">
            <label for="search_word">Rechercher un mot :</label><br>
            <input type="text" name="search_word" id="search_word" required>
            <div style="text-align: center; margin-top: 10px;">
                <input type="submit" value="Chercher">
            </div>
        </form>

        <?php if ($searchResult !== null): ?>
            <p><strong>Résultat :</strong> Le mot <strong>"<?= htmlspecialchars($searchedWordDisplay) ?>"</strong> apparaît <strong><?= number_format($searchResult, 0, ',', ' ') ?></strong> fois.</p>
        <?php endif; ?>

        <!-- Bouton CSV au-dessus du tableau -->
        <div class="table-header">
            <form method="post" style="margin: 0; padding: 0;">
                <input type="hidden" name="download_csv" value="1">
                <button type="submit" class="download-btn">📥 Télécharger en CSV</button>
            </form>
        </div>

        <!-- Tableau -->
        <table id="resultsTable">
            <thead>
                <tr><th>#</th><th>Mot</th><th>Occurrence</th></tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($frequencies as $word => $count): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($word, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><?= number_format($count, 0, ',', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
// Bouton retour en haut
const scrollTopBtn = document.getElementById('scrollTopBtn');

window.addEventListener('scroll', function() {
    if (window.pageYOffset > 300) {
        scrollTopBtn.classList.add('show');
    } else {
        scrollTopBtn.classList.remove('show');
    }
});

scrollTopBtn.addEventListener('click', function() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});

// Animation de chargement lors de l'upload
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('pdf_file');
    if (fileInput.files.length > 0) {
        document.getElementById('loaderOverlay').classList.add('active');
    }
});
</script>
<!-- Lecteur audio vintage -->
<div id="audioPlayer" style="position: fixed; bottom: 20px; left: 20px; background: linear-gradient(135deg, rgba(61, 43, 31, 0.98), rgba(82, 61, 46, 0.98)); padding: 15px; border-radius: 8px; border: 3px solid #8b7355; box-shadow: 0 8px 32px rgba(0,0,0,0.8); display: flex; align-items: center; gap: 15px; z-index: 1000; max-width: 320px;">
    <img src="https://images.nexusmods.com/images/games/v2/1950/tile.jpg" alt="Cover" style="width: 60px; height: 60px; border-radius: 4px; border: 2px solid #8b7355; object-fit: cover;">
    <div style="flex: 1;">
        <div style="color: #f4e4c1; font-family: 'Cinzel', serif; font-size: 0.9em; margin-bottom: 5px; font-weight: 600;">Possessed by Disease - Nier Automata</div>
        <audio id="bgMusic" loop style="width: 100%; height: 30px;">
            <source src="Possessed-by-Disease-(From-NieR_Automata)-(128-kbps).mp3" type="audio/mpeg">
        </audio>
    </div>
    <button onclick="toggleAudio()" id="playBtn" style="background: linear-gradient(135deg, #6b5445, #8b7355); color: #f4e4c1; border: 2px solid #a0826d; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 18px; min-width: 40px;">▶</button>
</div>
<div style="text-align:center; margin-top:15px; opacity:0.7;">
    <!-- Icône GPLv3 -->
    <a href="https://www.gnu.org/licenses/gpl-3.0.fr.html" target="_blank" style="margin-right:12px;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/9/93/GPLv3_Logo.svg"
             alt="GNU GPLv3"
             style="height:24px; width:auto; vertical-align:middle;">
    </a>

    <!-- Icône GitHub fond transparent -->
    <a href="https://github.com/sbois" target="_blank">
        <img src="https://raw.githubusercontent.com/simple-icons/simple-icons/develop/icons/github.svg"
             alt="GitHub"
             style="height:24px; width:auto; vertical-align:middle;">
    </a>
</div>
<script>
const audio = document.getElementById('bgMusic');
const playBtn = document.getElementById('playBtn');

function toggleAudio() {
    if (audio.paused) {
        audio.play();
        playBtn.textContent = '⏸';
    } else {
        audio.pause();
        playBtn.textContent = '▶';
    }
}

// Tenter de jouer automatiquement (peut être bloqué par le navigateur)
audio.play().catch(() => {
    // Si bloqué, le bouton permettra de démarrer manuellement
});
</script>
</body>
</html>