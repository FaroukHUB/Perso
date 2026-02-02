<?php
/**
 * PERSONNALY - FontLoader
 * Génère le CSS dynamique pour charger les polices
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Font.php';

class FontLoader
{
    private static ?array $cachedFonts = null;

    /**
     * Récupère les polices actives (avec cache)
     */
    private static function getActiveFonts(): array
    {
        if (self::$cachedFonts === null) {
            $fontModel = new Font();
            self::$cachedFonts = $fontModel->findActive();
        }
        return self::$cachedFonts;
    }

    /**
     * Génère les balises <link> pour Google Fonts
     * Retourne le HTML à insérer dans le <head>
     */
    public static function getGoogleFontsLinks(): string
    {
        $fonts = self::getActiveFonts();
        $googleFonts = array_filter($fonts, fn($f) => $f['source'] === 'google' && !empty($f['google_import_url']));

        if (empty($googleFonts)) {
            return '';
        }

        $html = "<!-- PERSONNALY - Google Fonts (dynamique) -->\n";
        $html .= '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        $html .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";

        foreach ($googleFonts as $font) {
            $html .= '<link href="' . htmlspecialchars($font['google_import_url']) . '" rel="stylesheet">' . "\n";
        }

        return $html;
    }

    /**
     * Génère le CSS @font-face pour les polices custom
     * Retourne le CSS à insérer dans une balise <style>
     * Supporte: OTF, TTF, WOFF, WOFF2
     */
    public static function getCustomFontsCss(): string
    {
        $fonts = self::getActiveFonts();
        $customFonts = array_filter($fonts, fn($f) => $f['source'] === 'custom');

        if (empty($customFonts)) {
            return '';
        }

        $css = "/* PERSONNALY - Custom Fonts (dynamique) */\n";

        foreach ($customFonts as $font) {
            $sources = [];

            // Fichier principal (peut être OTF, TTF, WOFF ou WOFF2)
            if (!empty($font['custom_woff2_url'])) {
                $url = $font['custom_woff2_url'];
                $format = self::detectFontFormat($url);
                $sources[] = "url('" . $url . "') format('" . $format . "')";
            }

            // Fichier fallback (optionnel)
            if (!empty($font['custom_woff_url'])) {
                $url = $font['custom_woff_url'];
                $format = self::detectFontFormat($url);
                $sources[] = "url('" . $url . "') format('" . $format . "')";
            }

            if (!empty($sources)) {
                $css .= "@font-face {\n";
                $css .= "  font-family: '" . htmlspecialchars($font['family']) . "';\n";
                $css .= "  src: " . implode(",\n       ", $sources) . ";\n";
                $css .= "  font-weight: 400;\n";
                $css .= "  font-style: normal;\n";
                $css .= "  font-display: swap;\n";
                $css .= "}\n\n";
            }
        }

        return $css;
    }

    /**
     * Détecte le format d'une police à partir de son URL/extension
     */
    private static function detectFontFormat(string $url): string
    {
        $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));

        return match ($ext) {
            'woff2' => 'woff2',
            'woff' => 'woff',
            'ttf' => 'truetype',
            'otf' => 'opentype',
            default => 'woff2'
        };
    }

    /**
     * Génère tout le HTML/CSS nécessaire pour charger les polices
     * À insérer dans le <head>
     */
    public static function renderHead(): string
    {
        $html = '';

        // Google Fonts links
        $googleLinks = self::getGoogleFontsLinks();
        if ($googleLinks) {
            $html .= $googleLinks;
        }

        // Custom fonts CSS
        $customCss = self::getCustomFontsCss();
        if ($customCss) {
            $html .= "<style>\n" . $customCss . "</style>\n";
        }

        return $html;
    }

    /**
     * Génère les classes CSS pour chaque police
     * Permet d'utiliser .font-poppins_400_600_700 { font-family: 'Poppins'; }
     */
    public static function getFontClasses(): string
    {
        $fonts = self::getActiveFonts();

        if (empty($fonts)) {
            return '';
        }

        $css = "/* PERSONNALY - Font Classes */\n";

        foreach ($fonts as $font) {
            $css .= ".font-" . htmlspecialchars($font['css_key']) . " {\n";
            $css .= "  font-family: '" . htmlspecialchars($font['family']) . "', " . htmlspecialchars($font['category']) . ";\n";
            $css .= "}\n";
        }

        return $css;
    }

    /**
     * Retourne un tableau des polices pour le frontend (JSON-ready)
     */
    public static function getFontsForFrontend(): array
    {
        $fonts = self::getActiveFonts();
        $result = [];

        foreach ($fonts as $font) {
            $result[] = [
                'id' => (int) $font['id'],
                'name' => $font['name'],
                'family' => $font['family'],
                'css_key' => $font['css_key'],
                'category' => $font['category']
            ];
        }

        return $result;
    }

    /**
     * Retourne le JSON des polices pour le frontend
     */
    public static function getFontsJson(): string
    {
        return json_encode(self::getFontsForFrontend(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Génère une URL Google Fonts à partir de family et weights
     * Méthode statique utilitaire
     */
    public static function generateGoogleUrl(string $family, string $weights): string
    {
        $familyEncoded = str_replace(' ', '+', $family);
        $weightsFormatted = str_replace(';', ';', $weights);
        return "https://fonts.googleapis.com/css2?family={$familyEncoded}:wght@{$weightsFormatted}&display=swap";
    }

    /**
     * Vide le cache des polices
     */
    public static function clearCache(): void
    {
        self::$cachedFonts = null;
    }
}
