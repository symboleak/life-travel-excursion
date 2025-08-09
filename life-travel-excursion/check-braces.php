<?php
/**
 * Script pour vérifier les structures d'accolades et les erreurs de syntaxe
 * dans un fichier PHP
 * 
 * Ce script analyse un fichier PHP ligne par ligne pour trouver les accolades
 * ouvrantes et fermantes et s'assurer qu'elles sont correctement équilibrées.
 */

// Fichier à vérifier
$file_to_check = isset($argv[1]) ? $argv[1] : 'life-travel-excursion.php';

if (!file_exists($file_to_check)) {
    die("Le fichier $file_to_check n'existe pas.\n");
}

echo "Analyse du fichier $file_to_check...\n";

// Lire le contenu du fichier
$content = file_get_contents($file_to_check);
$lines = explode("\n", $content);

// Compteurs et structure de données
$open_braces = 0;
$brace_positions = [];
$line_number = 0;
$in_string = false;
$string_delimiter = '';
$in_comment = false;
$in_doc_comment = false;

// Analyser ligne par ligne
foreach ($lines as $line) {
    $line_number++;
    $chars = str_split($line);
    $position = 0;
    
    foreach ($chars as $char) {
        $position++;
        
        // Gestion des commentaires et des chaînes pour éviter les faux positifs
        if (!$in_string && !$in_comment && !$in_doc_comment && $char === '/' && isset($chars[$position]) && $chars[$position] === '/') {
            // Commentaire simple ligne
            break; // Passer à la ligne suivante
        }
        
        if (!$in_string && !$in_comment && !$in_doc_comment && $char === '/' && isset($chars[$position]) && $chars[$position] === '*') {
            // Début d'un bloc de commentaire
            $in_doc_comment = true;
            continue;
        }
        
        if ($in_doc_comment && $char === '*' && isset($chars[$position]) && $chars[$position] === '/') {
            // Fin d'un bloc de commentaire
            $in_doc_comment = false;
            continue;
        }
        
        // Ignorer tout à l'intérieur d'un commentaire
        if ($in_doc_comment || $in_comment) {
            continue;
        }
        
        // Gestion des chaînes de caractères
        if (!$in_string && ($char === "'" || $char === '"')) {
            $in_string = true;
            $string_delimiter = $char;
            continue;
        }
        
        if ($in_string && $char === $string_delimiter && isset($chars[$position-2]) && $chars[$position-2] !== '\\') {
            // Fin d'une chaîne (non échappée)
            $in_string = false;
            continue;
        }
        
        // Ignorer tout à l'intérieur d'une chaîne
        if ($in_string) {
            continue;
        }
        
        // Compter les accolades
        if ($char === '{') {
            $open_braces++;
            $brace_positions[] = [
                'type' => 'open',
                'line' => $line_number,
                'position' => $position,
                'content' => trim($line)
            ];
        } else if ($char === '}') {
            $open_braces--;
            $brace_positions[] = [
                'type' => 'close',
                'line' => $line_number,
                'position' => $position,
                'content' => trim($line)
            ];
            
            // Détecter un déséquilibre immédiat
            if ($open_braces < 0) {
                echo "ERREUR: Accolade fermante sans ouvrante correspondante à la ligne $line_number, position $position\n";
                echo "Contexte: " . trim($line) . "\n";
                break;
            }
        }
    }
    
    // Fin de ligne, réinitialiser les drapeaux de commentaires simple ligne
    $in_comment = false;
}

// Vérifier l'équilibre final
if ($open_braces === 0) {
    echo "Les accolades sont équilibrées. Structure valide.\n";
} else {
    echo "ERREUR: La structure des accolades n'est pas équilibrée.\n";
    echo "Nombre d'accolades ouvrantes sans fermeture: $open_braces\n";
    
    // Afficher les dernières accolades ouvertes
    echo "\nDernières accolades ouvertes:\n";
    $count = 0;
    $open_count = 0;
    
    foreach ($brace_positions as $brace) {
        if ($brace['type'] === 'open') {
            $open_count++;
            echo "Accolade ouverte {$open_count}: Ligne {$brace['line']}, Position {$brace['position']}: {$brace['content']}\n";
            $count++;
            
            if ($count >= 5 && $open_count >= $open_braces - 5) {
                break;
            }
        } else if ($brace['type'] === 'close') {
            $open_count--;
        }
    }
}

// Analyser la structure des if/else
echo "\nAnalyse des structures if/else...\n";
$if_positions = [];
$else_positions = [];
$line_number = 0;

foreach ($lines as $line) {
    $line_number++;
    
    // Recherche de motifs if et else
    if (preg_match('/\bif\s*\(/i', $line)) {
        $if_positions[] = [
            'line' => $line_number,
            'content' => trim($line)
        ];
    }
    
    if (preg_match('/\}\s*else\b/i', $line) || preg_match('/^\s*else\b/i', $line)) {
        $else_positions[] = [
            'line' => $line_number,
            'content' => trim($line)
        ];
    }
}

echo "Structures if trouvées: " . count($if_positions) . "\n";
echo "Structures else trouvées: " . count($else_positions) . "\n";

// Vérifier les else sans if correspondant
if (count($else_positions) > 0) {
    echo "\nDernières structures else trouvées:\n";
    $count = 0;
    foreach (array_slice($else_positions, -5) as $else) {
        echo "Else à la ligne {$else['line']}: {$else['content']}\n";
        $count++;
        if ($count >= 5) break;
    }
}

// Vérifier la présence de la structure conditionnelle principale à la fin du fichier
echo "\nVérification de la structure conditionnelle principale en fin de fichier...\n";
$last_lines = array_slice($lines, -20);
$found_main_if_else = false;

foreach ($last_lines as $index => $line) {
    if (preg_match('/}\s*else\s*{/i', $line)) {
        echo "Structure if/else trouvée en fin de fichier à la ligne " . (count($lines) - 20 + $index + 1) . ":\n";
        echo trim($line) . "\n";
        $found_main_if_else = true;
    }
}

if (!$found_main_if_else) {
    echo "ATTENTION: Aucune structure if/else principale trouvée en fin de fichier.\n";
}

echo "\nFin de l'analyse.\n";
