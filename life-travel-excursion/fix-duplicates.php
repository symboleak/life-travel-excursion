<?php
/**
 * Script de correction des fonctions dupliquées
 * Plugin Life Travel Excursion
 * 
 * Ce script corrige automatiquement les duplications de fonctions
 * qui empêchent l'activation du plugin
 */

// Protection contre l'accès direct
if (!defined('ABSPATH') && !isset($argv)) {
    exit('Accès direct interdit');
}

$plugin_file = __DIR__ . '/life-travel-excursion.php';

if (!file_exists($plugin_file)) {
    die("Erreur : Fichier plugin introuvable\n");
}

// Lire le contenu du fichier
$content = file_get_contents($plugin_file);
$original_content = $content;

// Tableau des fonctions dupliquées à corriger
$duplicates_to_fix = [
    [
        'function' => 'life_travel_excursion_check_simultaneous_excursions',
        'start_line' => 1705,
        'end_line' => 1719
    ],
    [
        'function' => 'life_travel_excursion_get_pricing_details',
        'start_line' => 1740,
        'end_line' => 1974
    ],
    [
        'function' => 'life_travel_excursion_display_booking_form',
        'start_line' => 2803,
        'end_line' => 2839
    ]
];

// Diviser le contenu en lignes
$lines = explode("\n", $content);
$total_lines = count($lines);

echo "Analyse du fichier plugin...\n";
echo "Total de lignes : $total_lines\n\n";

// Marquer les lignes à supprimer
$lines_to_remove = [];

foreach ($duplicates_to_fix as $duplicate) {
    echo "Traitement de la fonction dupliquée : {$duplicate['function']}\n";
    
    // Ajuster les indices (les tableaux PHP commencent à 0)
    $start_idx = $duplicate['start_line'] - 1;
    $end_idx = $duplicate['end_line'] - 1;
    
    // Vérifier que les indices sont valides
    if ($start_idx >= 0 && $end_idx < $total_lines) {
        // Rechercher le début exact de la fonction
        $found = false;
        for ($i = max(0, $start_idx - 5); $i <= min($total_lines - 1, $start_idx + 5); $i++) {
            if (strpos($lines[$i], 'function ' . $duplicate['function']) !== false) {
                $start_idx = $i;
                $found = true;
                break;
            }
        }
        
        if ($found) {
            // Trouver la fin de la fonction (accolade fermante correspondante)
            $brace_count = 0;
            $in_function = false;
            
            for ($i = $start_idx; $i < $total_lines; $i++) {
                $line = $lines[$i];
                
                if (strpos($line, 'function ' . $duplicate['function']) !== false) {
                    $in_function = true;
                }
                
                if ($in_function) {
                    $brace_count += substr_count($line, '{');
                    $brace_count -= substr_count($line, '}');
                    
                    $lines_to_remove[] = $i;
                    
                    if ($brace_count == 0 && strpos($line, '}') !== false) {
                        // Fonction terminée
                        break;
                    }
                }
            }
            
            echo "  - Marqué pour suppression : lignes " . ($start_idx + 1) . " à " . ($i + 1) . "\n";
        } else {
            echo "  - ATTENTION : Fonction non trouvée autour de la ligne {$duplicate['start_line']}\n";
        }
    } else {
        echo "  - ERREUR : Indices de ligne invalides\n";
    }
}

// Supprimer les lignes marquées
if (!empty($lines_to_remove)) {
    echo "\nSuppression des lignes dupliquées...\n";
    
    // Trier en ordre décroissant pour supprimer de la fin vers le début
    rsort($lines_to_remove);
    
    foreach ($lines_to_remove as $idx) {
        unset($lines[$idx]);
    }
    
    // Réindexer le tableau
    $lines = array_values($lines);
    
    // Reconstituer le contenu
    $content = implode("\n", $lines);
    
    // Créer une sauvegarde
    $backup_file = $plugin_file . '.backup_' . date('Y-m-d_H-i-s');
    file_put_contents($backup_file, $original_content);
    echo "Sauvegarde créée : " . basename($backup_file) . "\n";
    
    // Écrire le fichier corrigé
    file_put_contents($plugin_file, $content);
    echo "Fichier plugin corrigé avec succès !\n";
    
    // Vérifier la syntaxe PHP
    $syntax_check = shell_exec('php -l "' . $plugin_file . '" 2>&1');
    if (strpos($syntax_check, 'No syntax errors detected') !== false) {
        echo "✅ Syntaxe PHP valide\n";
    } else {
        echo "⚠️ Erreurs de syntaxe détectées :\n$syntax_check\n";
        echo "Restauration du fichier original...\n";
        file_put_contents($plugin_file, $original_content);
        unlink($backup_file);
    }
} else {
    echo "\nAucune duplication trouvée ou déjà corrigées.\n";
}

echo "\nTerminé !\n";
