<?php
/**
 * PERSONNALY - BrandingService
 * Generation des CSS variables depuis la config branding
 *
 * Usage:
 *   $service = new BrandingService();
 *   echo $service->getCSSVariables();        // Global
 *   echo $service->getCSSVariables($clientId); // Client specifique
 */

require_once __DIR__ . '/../models/Branding.php';

class BrandingService
{
    private Branding $brandingModel;

    public function __construct()
    {
        $this->brandingModel = new Branding();
    }

    /**
     * Genere les CSS custom properties depuis la config branding
     *
     * @param int|null $clientId ID client ou null pour global
     * @return string CSS :root { ... } block
     */
    public function getCSSVariables(?int $clientId = null): string
    {
        $config = $this->brandingModel->resolveBranding($clientId);

        $radiusMap = Branding::getBorderRadiusOptions();
        $shadowMap = Branding::getShadowOptions();

        $vars = [];

        // Polices
        $vars['--font-primary'] = $config['font_primary'] . ', sans-serif';
        $vars['--font-secondary'] = $config['font_secondary'] . ', sans-serif';

        // Couleurs
        $vars['--color-primary'] = $config['color_primary'];
        $vars['--color-secondary'] = $config['color_secondary'];
        $vars['--color-accent'] = $config['color_accent'];
        $vars['--color-text'] = $config['color_text'];
        $vars['--color-text-light'] = $config['color_text_light'];
        $vars['--color-background'] = $config['color_background'];
        $vars['--color-surface'] = $config['color_surface'];
        $vars['--color-button'] = $config['color_button'];
        $vars['--color-button-text'] = $config['color_button_text'];

        // UI Style
        $vars['--border-radius'] = $radiusMap[$config['border_radius']] ?? '8px';
        $vars['--shadow'] = $shadowMap[$config['shadow_intensity']] ?? '0 1px 3px rgba(0,0,0,0.1)';

        // Construire le CSS
        $css = ":root {\n";
        foreach ($vars as $name => $value) {
            $css .= "  {$name}: {$value};\n";
        }
        $css .= "}\n";

        return $css;
    }

    /**
     * Genere les <link> tags pour charger les Google Fonts
     *
     * @param int|null $clientId ID client ou null pour global
     * @return string HTML link tags
     */
    public function getFontLinks(?int $clientId = null): string
    {
        $config = $this->brandingModel->resolveBranding($clientId);
        $links = '';

        if (!empty($config['font_primary_url'])) {
            $links .= '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
            $links .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
            $links .= '<link rel="stylesheet" href="' . htmlspecialchars($config['font_primary_url']) . '">' . "\n";
        }

        if (!empty($config['font_secondary_url']) && $config['font_secondary_url'] !== $config['font_primary_url']) {
            $links .= '<link rel="stylesheet" href="' . htmlspecialchars($config['font_secondary_url']) . '">' . "\n";
        }

        return $links;
    }

    /**
     * Genere le CSS pour une section de homepage specifique
     *
     * @param string $sectionKey Cle de la section (hero, featured_products, etc.)
     * @param int|null $clientId ID client ou null pour global
     * @return string CSS pour cette section
     */
    public function getSectionCSS(string $sectionKey, ?int $clientId = null): string
    {
        $style = $this->brandingModel->getSectionStyle($sectionKey, $clientId);
        $paddingMap = Branding::getPaddingOptions();

        $css = ".section-{$sectionKey} {\n";

        // Background
        if (!empty($style['background_color'])) {
            $css .= "  background-color: {$style['background_color']};\n";
        }

        if (!empty($style['background_image'])) {
            $css .= "  background-image: url('{$style['background_image']}');\n";
            $css .= "  background-size: cover;\n";
            $css .= "  background-position: center;\n";

            // Overlay
            if (!empty($style['background_overlay']) && !empty($style['background_overlay_opacity'])) {
                $css .= "  position: relative;\n";
            }
        }

        // Text color override
        if (!empty($style['text_color_override'])) {
            $css .= "  color: {$style['text_color_override']};\n";
        }

        // Padding
        $padding = $paddingMap[$style['padding_y']] ?? '4rem';
        $css .= "  padding-top: {$padding};\n";
        $css .= "  padding-bottom: {$padding};\n";

        $css .= "}\n";

        // Overlay pseudo-element si necessaire
        if (!empty($style['background_image']) && !empty($style['background_overlay'])) {
            $opacity = $style['background_overlay_opacity'] ?? 0.5;
            $css .= ".section-{$sectionKey}::before {\n";
            $css .= "  content: '';\n";
            $css .= "  position: absolute;\n";
            $css .= "  inset: 0;\n";
            $css .= "  background-color: {$style['background_overlay']};\n";
            $css .= "  opacity: {$opacity};\n";
            $css .= "  pointer-events: none;\n";
            $css .= "}\n";
        }

        return $css;
    }

    /**
     * Genere le CSS complet pour toutes les sections actives
     *
     * @param int|null $clientId ID client ou null pour global
     * @return string CSS pour toutes les sections
     */
    public function getAllSectionsCSS(?int $clientId = null): string
    {
        $sections = $this->brandingModel->getSectionStyles($clientId);
        $css = '';

        foreach ($sections as $section) {
            $css .= $this->getSectionCSS($section['section_key'], $clientId);
        }

        return $css;
    }

    /**
     * Genere le block <style> complet a injecter dans le <head>
     *
     * @param int|null $clientId ID client ou null pour global
     * @return string HTML style tag avec toutes les variables et sections
     */
    public function getStyleBlock(?int $clientId = null): string
    {
        $css = $this->getCSSVariables($clientId);
        $css .= "\n" . $this->getAllSectionsCSS($clientId);

        return "<style>\n{$css}</style>";
    }

    /**
     * Recupere la config branding complete (pour API/JS)
     *
     * @param int|null $clientId ID client ou null pour global
     * @return array Configuration complete
     */
    public function getBrandingConfig(?int $clientId = null): array
    {
        $config = $this->brandingModel->resolveBranding($clientId);
        $sections = $this->brandingModel->getSectionStyles($clientId);

        return [
            'fonts' => [
                'primary' => [
                    'family' => $config['font_primary'],
                    'url' => $config['font_primary_url'],
                ],
                'secondary' => [
                    'family' => $config['font_secondary'],
                    'url' => $config['font_secondary_url'],
                ],
            ],
            'colors' => [
                'primary' => $config['color_primary'],
                'secondary' => $config['color_secondary'],
                'accent' => $config['color_accent'],
                'text' => $config['color_text'],
                'textLight' => $config['color_text_light'],
                'background' => $config['color_background'],
                'surface' => $config['color_surface'],
                'button' => $config['color_button'],
                'buttonText' => $config['color_button_text'],
            ],
            'ui' => [
                'borderRadius' => $config['border_radius'],
                'shadowIntensity' => $config['shadow_intensity'],
            ],
            'logos' => [
                'main' => $config['logo_url'],
                'light' => $config['logo_light_url'],
                'favicon' => $config['favicon_url'],
            ],
            'sections' => array_map(function ($section) {
                return [
                    'key' => $section['section_key'],
                    'backgroundColor' => $section['background_color'],
                    'backgroundImage' => $section['background_image'],
                    'textColor' => $section['text_color_override'],
                    'paddingY' => $section['padding_y'],
                ];
            }, $sections),
        ];
    }

    /**
     * Recupere le favicon URL
     *
     * @param int|null $clientId ID client ou null pour global
     * @return string|null URL du favicon
     */
    public function getFavicon(?int $clientId = null): ?string
    {
        $config = $this->brandingModel->resolveBranding($clientId);
        return $config['favicon_url'];
    }

    /**
     * Recupere le logo URL (choisit automatiquement selon le contexte)
     *
     * @param int|null $clientId ID client ou null pour global
     * @param bool $lightBackground True si fond clair, false si fond sombre
     * @return string|null URL du logo
     */
    public function getLogo(?int $clientId = null, bool $lightBackground = true): ?string
    {
        $config = $this->brandingModel->resolveBranding($clientId);

        if ($lightBackground) {
            return $config['logo_url'] ?: $config['logo_light_url'];
        }

        return $config['logo_light_url'] ?: $config['logo_url'];
    }
}
