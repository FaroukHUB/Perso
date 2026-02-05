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
require_once __DIR__ . '/../models/Font.php';
require_once __DIR__ . '/../helpers/FontLoader.php';

class BrandingService
{
    private Branding $brandingModel;

    public function __construct()
    {
        $this->brandingModel = new Branding();
    }

    /**
     * Genere les CSS custom properties depuis la config branding
     * Comprend : polices, couleurs, typographie, boutons, système de couleurs
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

        // ========================================
        // POLICES DE BASE
        // ========================================
        $fontPrimary = $this->brandingModel->getFontPrimary($clientId);
        $fontSecondary = $this->brandingModel->getFontSecondary($clientId);

        if ($fontPrimary) {
            $vars['--font-primary'] = $fontPrimary['family'] . ', sans-serif';
        } else {
            // Fallback rétrocompatibilité
            $vars['--font-primary'] = ($config['font_primary'] ?? 'sans-serif') . ', sans-serif';
        }

        if ($fontSecondary) {
            $vars['--font-secondary'] = $fontSecondary['family'] . ', sans-serif';
        } else {
            // Fallback rétrocompatibilité
            $vars['--font-secondary'] = ($config['font_secondary'] ?? 'sans-serif') . ', sans-serif';
        }

        // ========================================
        // ÉCHELLE TYPOGRAPHIQUE
        // ========================================
        $typographyScale = $config['typography_scale'] ?? [];

        foreach ($typographyScale as $element => $styles) {
            $fontVar = ($styles['font'] === 'primary') ? 'var(--font-primary)' : 'var(--font-secondary)';
            $vars["--font-{$element}"] = $fontVar;
            $vars["--font-size-{$element}"] = $styles['size'] ?? '1rem';
            $vars["--font-weight-{$element}"] = $styles['weight'] ?? '400';
            $vars["--line-height-{$element}"] = $styles['line_height'] ?? '1.5';
        }

        // ========================================
        // COULEURS DE BASE (rétrocompatibilité)
        // ========================================
        $vars['--color-primary'] = $config['color_primary'] ?? '#6366F1';
        $vars['--color-secondary'] = $config['color_secondary'] ?? '#8B5CF6';
        $vars['--color-accent'] = $config['color_accent'] ?? '#F59E0B';
        $vars['--color-text'] = $config['color_text'] ?? '#1F2937';
        $vars['--color-text-light'] = $config['color_text_light'] ?? '#6B7280';
        $vars['--color-background'] = $config['color_background'] ?? '#FFFFFF';
        $vars['--color-surface'] = $config['color_surface'] ?? '#F9FAFB';
        $vars['--color-button'] = $config['color_button'] ?? '#6366F1';
        $vars['--color-button-text'] = $config['color_button_text'] ?? '#FFFFFF';

        // ========================================
        // SYSTÈME DE COULEURS AVANCÉ
        // ========================================
        $colorSystem = $config['color_system'] ?? [];

        // Couleurs primary avec variants
        if (!empty($colorSystem['primary'])) {
            $vars['--color-primary-base'] = $colorSystem['primary']['base'] ?? $vars['--color-primary'];
            $vars['--color-primary-hover'] = $colorSystem['primary']['hover'] ?? $vars['--color-primary'];
            $vars['--color-primary-active'] = $colorSystem['primary']['active'] ?? $vars['--color-primary'];
            $vars['--color-primary-disabled'] = $colorSystem['primary']['disabled'] ?? '#D1D5DB';
            $vars['--color-primary-text'] = $colorSystem['primary']['text_on'] ?? '#FFFFFF';
        }

        // Couleurs secondary avec variants
        if (!empty($colorSystem['secondary'])) {
            $vars['--color-secondary-base'] = $colorSystem['secondary']['base'] ?? $vars['--color-secondary'];
            $vars['--color-secondary-hover'] = $colorSystem['secondary']['hover'] ?? $vars['--color-secondary'];
            $vars['--color-secondary-active'] = $colorSystem['secondary']['active'] ?? $vars['--color-secondary'];
            $vars['--color-secondary-disabled'] = $colorSystem['secondary']['disabled'] ?? '#D1D5DB';
            $vars['--color-secondary-text'] = $colorSystem['secondary']['text_on'] ?? '#FFFFFF';
        }

        // Couleurs accent avec variants
        if (!empty($colorSystem['accent'])) {
            $vars['--color-accent-base'] = $colorSystem['accent']['base'] ?? $vars['--color-accent'];
            $vars['--color-accent-hover'] = $colorSystem['accent']['hover'] ?? $vars['--color-accent'];
            $vars['--color-accent-active'] = $colorSystem['accent']['active'] ?? $vars['--color-accent'];
            $vars['--color-accent-disabled'] = $colorSystem['accent']['disabled'] ?? '#FCD34D';
            $vars['--color-accent-text'] = $colorSystem['accent']['text_on'] ?? '#FFFFFF';
        }

        // Couleurs de texte
        if (!empty($colorSystem['text'])) {
            $vars['--color-text-primary'] = $colorSystem['text']['primary'] ?? $vars['--color-text'];
            $vars['--color-text-secondary'] = $colorSystem['text']['secondary'] ?? $vars['--color-text-light'];
            $vars['--color-text-tertiary'] = $colorSystem['text']['tertiary'] ?? '#9CA3AF';
            $vars['--color-text-disabled'] = $colorSystem['text']['disabled'] ?? '#D1D5DB';
            $vars['--color-text-on-dark'] = $colorSystem['text']['on_dark'] ?? '#FFFFFF';
        }

        // Backgrounds
        if (!empty($colorSystem['background'])) {
            $vars['--color-bg-primary'] = $colorSystem['background']['primary'] ?? $vars['--color-background'];
            $vars['--color-bg-secondary'] = $colorSystem['background']['secondary'] ?? $vars['--color-surface'];
            $vars['--color-bg-tertiary'] = $colorSystem['background']['tertiary'] ?? '#F3F4F6';
            $vars['--color-bg-inverse'] = $colorSystem['background']['inverse'] ?? '#1F2937';
        }

        // Borders
        if (!empty($colorSystem['border'])) {
            $vars['--color-border-primary'] = $colorSystem['border']['primary'] ?? '#E5E7EB';
            $vars['--color-border-secondary'] = $colorSystem['border']['secondary'] ?? '#D1D5DB';
            $vars['--color-border-focus'] = $colorSystem['border']['focus'] ?? $vars['--color-primary'];
        }

        // Status colors
        if (!empty($colorSystem['status'])) {
            $vars['--color-success'] = $colorSystem['status']['success'] ?? '#10B981';
            $vars['--color-success-bg'] = $colorSystem['status']['success_bg'] ?? '#D1FAE5';
            $vars['--color-warning'] = $colorSystem['status']['warning'] ?? '#F59E0B';
            $vars['--color-warning-bg'] = $colorSystem['status']['warning_bg'] ?? '#FEF3C7';
            $vars['--color-error'] = $colorSystem['status']['error'] ?? '#EF4444';
            $vars['--color-error-bg'] = $colorSystem['status']['error_bg'] ?? '#FEE2E2';
            $vars['--color-info'] = $colorSystem['status']['info'] ?? '#3B82F6';
            $vars['--color-info-bg'] = $colorSystem['status']['info_bg'] ?? '#DBEAFE';
        }

        // ========================================
        // STYLES DE BOUTONS
        // ========================================
        $buttonStyles = $config['button_styles'] ?? [];

        foreach ($buttonStyles as $type => $styles) {
            $prefix = "--btn-{$type}";
            $vars["{$prefix}-bg"] = $styles['bg_color'] ?? 'transparent';
            $vars["{$prefix}-text"] = $styles['text_color'] ?? '#000000';
            $vars["{$prefix}-hover-bg"] = $styles['hover_bg'] ?? $styles['bg_color'] ?? 'transparent';
            $vars["{$prefix}-hover-text"] = $styles['hover_text'] ?? $styles['text_color'] ?? '#000000';
            $vars["{$prefix}-border"] = $styles['border_color'] ?? 'transparent';
            $vars["{$prefix}-border-width"] = $styles['border_width'] ?? '0px';
        }

        // ========================================
        // UI STYLE
        // ========================================
        $vars['--border-radius'] = $radiusMap[$config['border_radius'] ?? 'medium'] ?? '8px';
        $vars['--shadow'] = $shadowMap[$config['shadow_intensity'] ?? 'subtle'] ?? '0 1px 3px rgba(0,0,0,0.1)';

        // ========================================
        // CONSTRUIRE LE CSS
        // ========================================
        $css = ":root {\n";
        foreach ($vars as $name => $value) {
            $css .= "  {$name}: {$value};\n";
        }
        $css .= "}\n";

        return $css;
    }

    /**
     * Genere les <link> tags pour charger les fonts (Google ou Custom)
     * Utilise FontLoader pour générer automatiquement les imports
     *
     * @param int|null $clientId ID client ou null pour global
     * @return string HTML link/style tags
     */
    public function getFontLinks(?int $clientId = null): string
    {
        $fontPrimary = $this->brandingModel->getFontPrimary($clientId);
        $fontSecondary = $this->brandingModel->getFontSecondary($clientId);

        $fontIds = [];

        if ($fontPrimary) {
            $fontIds[] = $fontPrimary['id'];
        }

        if ($fontSecondary && $fontSecondary['id'] !== ($fontPrimary['id'] ?? null)) {
            $fontIds[] = $fontSecondary['id'];
        }

        if (empty($fontIds)) {
            // Fallback rétrocompatibilité avec les anciennes colonnes
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

        // Utiliser FontLoader pour générer les imports automatiquement
        return FontLoader::renderHead($fontIds);
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
     * Inclut : fonts, typography scale, colors, button styles, sections, logos
     *
     * @param int|null $clientId ID client ou null pour global
     * @return array Configuration complete
     */
    public function getBrandingConfig(?int $clientId = null): array
    {
        $config = $this->brandingModel->resolveBranding($clientId);
        $sections = $this->brandingModel->getSectionStyles($clientId);

        $fontPrimary = $this->brandingModel->getFontPrimary($clientId);
        $fontSecondary = $this->brandingModel->getFontSecondary($clientId);

        return [
            'fonts' => [
                'primary' => $fontPrimary ? [
                    'id' => $fontPrimary['id'],
                    'name' => $fontPrimary['name'],
                    'family' => $fontPrimary['family'],
                    'source' => $fontPrimary['source'],
                ] : [
                    'family' => $config['font_primary'] ?? 'sans-serif',
                    'url' => $config['font_primary_url'] ?? null,
                ],
                'secondary' => $fontSecondary ? [
                    'id' => $fontSecondary['id'],
                    'name' => $fontSecondary['name'],
                    'family' => $fontSecondary['family'],
                    'source' => $fontSecondary['source'],
                ] : [
                    'family' => $config['font_secondary'] ?? 'sans-serif',
                    'url' => $config['font_secondary_url'] ?? null,
                ],
            ],
            'typographyScale' => $config['typography_scale'] ?? [],
            'colors' => [
                'primary' => $config['color_primary'] ?? '#6366F1',
                'secondary' => $config['color_secondary'] ?? '#8B5CF6',
                'accent' => $config['color_accent'] ?? '#F59E0B',
                'text' => $config['color_text'] ?? '#1F2937',
                'textLight' => $config['color_text_light'] ?? '#6B7280',
                'background' => $config['color_background'] ?? '#FFFFFF',
                'surface' => $config['color_surface'] ?? '#F9FAFB',
                'button' => $config['color_button'] ?? '#6366F1',
                'buttonText' => $config['color_button_text'] ?? '#FFFFFF',
            ],
            'colorSystem' => $config['color_system'] ?? [],
            'buttonStyles' => $config['button_styles'] ?? [],
            'ui' => [
                'borderRadius' => $config['border_radius'] ?? 'medium',
                'shadowIntensity' => $config['shadow_intensity'] ?? 'subtle',
            ],
            'logos' => [
                'main' => $config['logo_url'] ?? null,
                'light' => $config['logo_light_url'] ?? null,
                'favicon' => $config['favicon_url'] ?? null,
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
