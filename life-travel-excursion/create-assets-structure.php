<?php
/**
 * Script de création de la structure d'assets pour Life Travel
 * 
 * Ce script aide à créer les dossiers et fichiers nécessaires 
 * pour le système d'optimisation des images et SVG.
 */

// Chemin du plugin
$plugin_dir = dirname(__FILE__);

// Dossiers à créer
$directories = [
    'assets/svg',
    'assets/img-opt',
    'assets/img-opt/fallback',
    'offline/assets/img',
    'offline/assets/css',
    'offline/assets/js'
];

echo "===== Création de la structure de dossiers pour Life Travel =====\n";

// Créer les dossiers
foreach ($directories as $directory) {
    $path = $plugin_dir . '/' . $directory;
    
    if (!is_dir($path)) {
        if (mkdir($path, 0755, true)) {
            echo "✓ Dossier créé : $directory\n";
        } else {
            echo "❌ Erreur lors de la création du dossier : $directory\n";
        }
    } else {
        echo "✓ Le dossier existe déjà : $directory\n";
    }
}

// Liste des SVG à créer
$svg_files = [
    'offline' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
  <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 10.15 4.63 8.45 5.69 7.1L16.9 18.31C15.55 19.37 13.85 20 12 20ZM18.31 16.9L7.1 5.69C8.45 4.63 10.15 4 12 4C16.41 4 20 7.59 20 12C20 13.85 19.37 15.55 18.31 16.9Z" fill="#E74C3C"/>
</svg>',
    'wifi' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
  <path d="M1 9L3 11C7.97 6.03 16.03 6.03 21 11L23 9C16.93 2.93 7.08 2.93 1 9ZM9 17L12 20L15 17C13.35 15.34 10.66 15.34 9 17ZM5 13L7 15C9.76 12.24 14.24 12.24 17 15L19 13C15.14 9.14 8.87 9.14 5 13Z" fill="#2ECC71"/>
</svg>',
    'cart' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
  <path d="M7 18C5.9 18 5.01 18.9 5.01 20C5.01 21.1 5.9 22 7 22C8.1 22 9 21.1 9 20C9 18.9 8.1 18 7 18ZM1 2V4H3L6.6 11.59L5.25 14.04C5.09 14.32 5 14.65 5 15C5 16.1 5.9 17 7 17H19V15H7.42C7.28 15 7.17 14.89 7.17 14.75L7.2 14.63L8.1 13H15.55C16.3 13 16.96 12.59 17.3 11.97L20.88 5.5C20.96 5.34 21 5.17 21 5C21 4.45 20.55 4 20 4H5.21L4.27 2H1ZM17 18C15.9 18 15.01 18.9 15.01 20C15.01 21.1 15.9 22 17 22C18.1 22 19 21.1 19 20C19 18.9 18.1 18 17 18Z" fill="#3498DB"/>
</svg>',
    'refresh' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
  <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4C7.58 4 4.01 7.58 4.01 12C4.01 16.42 7.58 20 12 20C15.73 20 18.84 17.45 19.73 14H17.65C16.83 16.33 14.61 18 12 18C8.69 18 6 15.31 6 12C6 8.69 8.69 6 12 6C13.66 6 15.14 6.69 16.22 7.78L13 11H20V4L17.65 6.35Z" fill="#F39C12"/>
</svg>',
    'sync' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
  <path d="M12 4V1L8 5L12 9V6C15.31 6 18 8.69 18 12C18 13.01 17.75 13.97 17.3 14.8L18.76 16.26C19.54 15.03 20 13.57 20 12C20 7.58 16.42 4 12 4ZM12 18C8.69 18 6 15.31 6 12C6 10.99 6.25 10.03 6.7 9.2L5.24 7.74C4.46 8.97 4 10.43 4 12C4 16.42 7.58 20 12 20V23L16 19L12 15V18Z" fill="#9B59B6"/>
</svg>',
    'map' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
  <path d="M20.5 3L20.34 3.03L15 5.1L9 3L3.36 4.9C3.15 4.97 3 5.15 3 5.38V20.5C3 20.78 3.22 21 3.5 21L3.66 20.97L9 18.9L15 21L20.64 19.1C20.85 19.03 21 18.85 21 18.62V3.5C21 3.22 20.78 3 20.5 3ZM15 19L9 16.89V5L15 7.11V19Z" fill="#16A085"/>
</svg>',
    'placeholder' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
  <rect width="24" height="24" fill="#EEEEEE"/>
  <path d="M19 5V19H5V5H19ZM19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3Z" fill="#7F8C8D"/>
  <path d="M14.14 11.86L12 9.72L9.86 11.86L11.29 13.29L9.71 14.86L8.29 13.44L6.71 15.02L10.03 18.33L17.03 11.33L14.14 8.44L12.71 9.86L14.14 11.29L12.56 12.86L14.14 11.86Z" fill="#7F8C8D"/>
</svg>'
];

echo "\n===== Création des SVG optimisés =====\n";

// Créer les fichiers SVG
foreach ($svg_files as $name => $content) {
    $file_path = $plugin_dir . '/assets/svg/' . $name . '.svg';
    
    if (!file_exists($file_path)) {
        if (file_put_contents($file_path, $content)) {
            echo "✓ SVG créé : $name.svg\n";
        } else {
            echo "❌ Erreur lors de la création du SVG : $name.svg\n";
        }
    } else {
        echo "✓ Le SVG existe déjà : $name.svg\n";
    }
}

// Créer le sprite SVG
echo "\n===== Création du sprite SVG =====\n";

$sprite_content = '<svg xmlns="http://www.w3.org/2000/svg" style="display:none;">';

foreach ($svg_files as $name => $content) {
    // Extraire le contenu intérieur des balises SVG
    if (preg_match('/<svg[^>]*>(.*?)<\/svg>/is', $content, $matches)) {
        $inner_content = $matches[1];
        $sprite_content .= '<symbol id="icon-' . $name . '">' . $inner_content . '</symbol>';
    }
}

$sprite_content .= '</svg>';

$sprite_path = $plugin_dir . '/assets/sprite.svg';

if (file_put_contents($sprite_path, $sprite_content)) {
    echo "✓ Sprite SVG créé avec succès !\n";
} else {
    echo "❌ Erreur lors de la création du sprite SVG\n";
}

echo "\n===== Création terminée =====\n";
echo "La structure d'assets a été créée avec succès.\n";
echo "Pour utiliser les icônes SVG dans votre code, référez-vous au pont images-bridge.php.\n";
