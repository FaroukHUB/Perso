<?php
/**
 * PERSONNALY - Helper pour le traitement des images
 * Conversion WebP, redimensionnement, optimisation
 */

class ImageHelper
{
    /**
     * Convertit une image en WebP
     *
     * @param string $sourcePath Chemin absolu de l'image source
     * @param int $quality Qualité WebP (0-100, défaut 85)
     * @return string|false Chemin du fichier WebP créé, ou false si échec
     */
    public static function convertToWebP(string $sourcePath, int $quality = 85)
    {
        // Vérifier que le fichier existe
        if (!file_exists($sourcePath)) {
            return false;
        }

        // Vérifier que GD est disponible avec support WebP
        if (!function_exists('imagewebp')) {
            error_log('ImageHelper: imagewebp() non disponible - extension GD manquante ou sans support WebP');
            return false;
        }

        // Obtenir les informations sur l'image
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        $mimeType = $imageInfo['mime'];

        // Créer l'image source selon le type
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = @imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = @imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $sourceImage = @imagecreatefromgif($sourcePath);
                break;
            case 'image/webp':
                // Déjà en WebP, copier simplement
                return $sourcePath;
            default:
                return false;
        }

        if (!$sourceImage) {
            return false;
        }

        // Préserver la transparence pour PNG
        if ($mimeType === 'image/png') {
            imagepalettetotruecolor($sourceImage);
            imagealphablending($sourceImage, true);
            imagesavealpha($sourceImage, true);
        }

        // Générer le chemin de sortie WebP
        $pathInfo = pathinfo($sourcePath);
        $webpPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';

        // Convertir en WebP
        $success = imagewebp($sourceImage, $webpPath, $quality);

        // Libérer la mémoire
        imagedestroy($sourceImage);

        return $success ? $webpPath : false;
    }

    /**
     * Traite une image uploadée : sauvegarde original + génère WebP
     *
     * @param string $tmpPath Chemin temporaire du fichier uploadé
     * @param string $destDir Dossier de destination (sans slash final)
     * @param string $filename Nom du fichier (sans extension)
     * @param string $originalExt Extension originale
     * @return array|false ['original' => path, 'webp' => path] ou false si échec
     */
    public static function processUpload(string $tmpPath, string $destDir, string $filename, string $originalExt)
    {
        // Créer le dossier si nécessaire
        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0755, true)) {
                return false;
            }
        }

        // Chemin de l'original
        $originalPath = $destDir . '/' . $filename . '.' . $originalExt;

        // Déplacer/copier l'original
        if (!move_uploaded_file($tmpPath, $originalPath)) {
            // Essayer copy si move échoue (cas des tests)
            if (!copy($tmpPath, $originalPath)) {
                return false;
            }
        }

        // Générer la version WebP
        $webpPath = self::convertToWebP($originalPath);

        return [
            'original' => $originalPath,
            'webp' => $webpPath
        ];
    }

    /**
     * Retourne l'URL WebP si elle existe, sinon l'URL originale
     *
     * @param string $originalUrl URL de l'image originale (ex: /uploads/products/image.jpg)
     * @return array ['webp' => url|null, 'original' => url]
     */
    public static function getImageUrls(string $originalUrl): array
    {
        $pathInfo = pathinfo($originalUrl);
        $webpUrl = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';

        // Construire le chemin absolu pour vérifier l'existence
        $basePath = dirname(__DIR__, 2) . '/public';
        $webpAbsPath = $basePath . $webpUrl;

        return [
            'webp' => file_exists($webpAbsPath) ? $webpUrl : null,
            'original' => $originalUrl
        ];
    }

    /**
     * Génère le HTML pour une image avec fallback WebP
     *
     * @param string $originalUrl URL de l'image originale
     * @param string $alt Texte alternatif
     * @param string $class Classes CSS (optionnel)
     * @param array $attrs Attributs supplémentaires (optionnel)
     * @return string HTML de la balise <picture>
     */
    public static function pictureTag(string $originalUrl, string $alt = '', string $class = '', array $attrs = []): string
    {
        $urls = self::getImageUrls($originalUrl);

        $attrsStr = '';
        foreach ($attrs as $key => $value) {
            $attrsStr .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        $classAttr = $class ? ' class="' . htmlspecialchars($class) . '"' : '';
        $altAttr = ' alt="' . htmlspecialchars($alt) . '"';

        if ($urls['webp']) {
            return '<picture>' .
                   '<source type="image/webp" srcset="/public' . htmlspecialchars($urls['webp']) . '">' .
                   '<img src="/public' . htmlspecialchars($urls['original']) . '"' . $altAttr . $classAttr . $attrsStr . '>' .
                   '</picture>';
        }

        return '<img src="/public' . htmlspecialchars($urls['original']) . '"' . $altAttr . $classAttr . $attrsStr . '>';
    }

    /**
     * Supprime une image et sa version WebP associée
     *
     * @param string $imagePath Chemin de l'image (original ou webp)
     * @return bool
     */
    public static function deleteImage(string $imagePath): bool
    {
        $deleted = false;

        if (file_exists($imagePath)) {
            $deleted = unlink($imagePath);
        }

        // Essayer de supprimer la version WebP associée
        $pathInfo = pathinfo($imagePath);
        $webpPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';

        if (file_exists($webpPath)) {
            unlink($webpPath);
        }

        // Essayer de supprimer l'original si on a passé le WebP
        if ($pathInfo['extension'] === 'webp') {
            foreach (['jpg', 'jpeg', 'png', 'gif'] as $ext) {
                $originalPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.' . $ext;
                if (file_exists($originalPath)) {
                    unlink($originalPath);
                    break;
                }
            }
        }

        return $deleted;
    }
}
