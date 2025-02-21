<?php
if (php_sapi_name() !== "cli") {
    die("Please use command line: php updater.php");
}

// scan json files
chdir(__DIR__);
$files = [];
$langs = 'ca_ES,cs_CZ,de_DE,en_EN,es_AR,es_CL,es_CO,es_CR,es_DO,es_EC,es_ES,es_GT,es_MX,es_PA,es_PE,es_UY,eu_ES,fr_FR,gl_ES,it_IT,pl_PL,pt_BR,pt_PT,va_ES';
foreach (explode(',', $langs) as $lang) {
    $files[] = $lang . '.json';
}
foreach (scandir(__DIR__, SCANDIR_SORT_ASCENDING) as $filename) {
    if (is_file($filename) && substr($filename, -5) === '.json' && false === in_array($filename, $files)) {
        $files[] = $filename;
    }
}

// download json from facturascripts.com
foreach ($files as $filename) {
    $url = "https://facturascripts.com/EditLanguage?action=json&idproject=146&code=" . substr($filename, 0, -5);
    $newContent = file_get_contents($url);
    if (empty($newContent)) {
        if (file_exists($filename)) {
            unlink($filename);
            echo "Remove " . $filename . "\n";
            continue;
        }

        echo "Empty " . $filename . "\n";
        continue;
    }

    $oldContent = file_exists($filename) ? file_get_contents($filename) : '';
    if (strlen($newContent) > 10 && $newContent !== $oldContent) {
        echo "Download " . $filename . "\n";
        file_put_contents($filename, $newContent);
        continue;
    }

    echo "Skip " . $filename . "\n";
}
