<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Percorso della cartella corrente
$directory = __DIR__;

// Array per memorizzare i temi trovati
$themes = [];

// Scansiona tutti i file ZIP nella cartella
$files = glob($directory . '/*.zip');

foreach ($files as $zip_file) {
    $zip = new ZipArchive();
    
    if ($zip->open($zip_file) === true) {
        // Cerca il file style.css del tema
        $theme_info = null;
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            // Cerca style.css nel primo livello o nella cartella principale del tema
            // Solitamente è "nome-tema/style.css" oppure "style.css" se lo zip è flat
            if (preg_match('/^[^\/]+\/style\.css$/', $filename) || $filename === 'style.css') {
                $content = $zip->getFromIndex($i);
                
                // Verifica se contiene l'header del tema
                if (preg_match('/Theme Name:/i', $content)) {
                    $theme_info = [
                        'content' => $content,
                        'file' => $filename
                    ];
                    break;
                }
            }
        }
        
        $zip->close();
        
        if ($theme_info) {
            // Estrae le informazioni dall'header del tema
            $name = '';
            $version = '';
            $author = '';
            $description = '';
            $requires_php = '7.0';
            
            if (preg_match('/Theme Name:\s*(.+)/i', $theme_info['content'], $matches)) {
                $name = trim($matches[1]);
            }
            
            if (preg_match('/Version:\s*(.+)/i', $theme_info['content'], $matches)) {
                $version = trim($matches[1]);
            }
            
            if (preg_match('/Author:\s*(.+)/i', $theme_info['content'], $matches)) {
                $author = trim($matches[1]);
            }
            
            if (preg_match('/Description:\s*(.+)/i', $theme_info['content'], $matches)) {
                $description = trim($matches[1]);
            }
            
            if (preg_match('/Requires PHP:\s*(.+)/i', $theme_info['content'], $matches)) {
                $requires_php = trim($matches[1]);
            }
            
            // Estrae lo slug dal nome del file ZIP
            $zip_filename = basename($zip_file, '.zip');
            
            // Rimuove pattern comuni dai nomi dei file:
            // 1. Versioni: -1.2.3 o -v1.2.3
            // 2. Duplicati: " (N)" dove N è un numero
            // 3. Suffissi custom: -custom
            $slug = $zip_filename;
            
            // Rimuove " (N)" pattern (es: "jupiterx (2)" -> "jupiterx")
            $slug = preg_replace('/\s+\(\d+\)$/', '', $slug);
            
            // Rimuove versione dal nome del file se presente
            // Es: jupiterx-v4.14.1 -> jupiterx
            $slug = preg_replace('/-v?\d+(\.\d+)*(-custom)?$/', '', $slug);
            
            // Se non riesce a estrarre lo slug, usa il nome della cartella interna
            if (empty($slug)) {
                $first_dir = dirname($theme_info['file']);
                $slug = $first_dir;
            }
            
            // URL completo per il download
            $download_url = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/' . basename($zip_file);
            
            $themes[] = [
                'name' => $name ?: 'Unknown Theme',
                'slug' => $slug,
                'version' => $version ?: '1.0.0',
                'author' => $author ?: 'Unknown',
                'description' => $description ?: 'Custom Theme',
                'requires' => '',
                'requires_php' => $requires_php,
                'tested' => '',
                'download_url' => $download_url,
                'package' => $download_url, // Alias for compatibility
                'info_url' => '',
                'changelog' => '<h4>' . $version . '</h4><p>Updated custom version</p>',
                'zip_file' => basename($zip_file),
                'file_size' => filesize($zip_file),
                'last_modified' => date('Y-m-d H:i:s', filemtime($zip_file))
            ];
        }
    }
}

// Ordina per nome
usort($themes, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Restituisce il JSON
echo json_encode($themes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
