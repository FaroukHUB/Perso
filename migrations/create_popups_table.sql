-- Migration: Système de popups administrables
-- Permet de créer des popups avec règles d'affichage et tracking analytics

CREATE TABLE IF NOT EXISTS popups (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Contenu
    title VARCHAR(255) NOT NULL COMMENT 'Titre de la popup',
    content TEXT COMMENT 'Contenu/description (HTML autorisé)',
    image_url VARCHAR(500) COMMENT 'URL de l\'image (optionnelle)',

    -- Call To Action
    cta_text VARCHAR(100) COMMENT 'Texte du bouton CTA',
    cta_url VARCHAR(500) COMMENT 'URL du bouton CTA',
    cta_new_tab BOOLEAN DEFAULT FALSE COMMENT 'Ouvrir dans nouvel onglet',
    promo_code VARCHAR(50) COMMENT 'Code promo à afficher (optionnel)',

    -- Règles de déclenchement
    trigger_type ENUM('immediate', 'delay', 'scroll', 'exit') DEFAULT 'immediate' COMMENT 'Type de déclenchement',
    trigger_value INT COMMENT 'Valeur du trigger (secondes pour delay, % pour scroll)',

    -- Fréquence d'affichage
    frequency ENUM('every_visit', 'per_session', 'daily', 'weekly', 'monthly', 'until_click') DEFAULT 'per_session' COMMENT 'Fréquence d\'affichage',

    -- Ciblage pages
    target_pages ENUM('all', 'home', 'products', 'cart', 'checkout', 'specific') DEFAULT 'all' COMMENT 'Pages ciblées',
    target_urls TEXT COMMENT 'URLs spécifiques (JSON array)',

    -- Ciblage visiteurs
    target_visitors ENUM('all', 'new', 'returning') DEFAULT 'all' COMMENT 'Type de visiteurs ciblés',

    -- Options de fermeture
    show_close_button BOOLEAN DEFAULT TRUE COMMENT 'Afficher le bouton X',
    click_outside_to_close BOOLEAN DEFAULT TRUE COMMENT 'Fermer en cliquant en dehors',
    show_never_show_again BOOLEAN DEFAULT FALSE COMMENT 'Afficher "Ne plus afficher"',
    auto_close_after INT COMMENT 'Auto-fermeture après X secondes (NULL = jamais)',

    -- Apparence
    template_type ENUM('modal', 'banner_top', 'banner_bottom', 'corner', 'fullscreen', 'side_panel') DEFAULT 'modal' COMMENT 'Type de template',
    size ENUM('small', 'medium', 'large') DEFAULT 'medium' COMMENT 'Taille de la popup',
    animation ENUM('fade', 'slide_up', 'slide_down', 'scale', 'slide_right') DEFAULT 'fade' COMMENT 'Animation d\'entrée',

    -- Analytics
    total_views INT DEFAULT 0 COMMENT 'Nombre total d\'affichages',
    total_clicks INT DEFAULT 0 COMMENT 'Nombre de clics sur CTA',
    total_closes INT DEFAULT 0 COMMENT 'Nombre de fermetures',

    -- Statut
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Popup active/inactive',
    priority INT DEFAULT 0 COMMENT 'Priorité d\'affichage (plus haut = prioritaire)',

    -- Métadonnées
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_active (is_active),
    INDEX idx_target_pages (target_pages),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour tracker les affichages par utilisateur (analytics détaillés)
CREATE TABLE IF NOT EXISTS popup_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    popup_id INT NOT NULL,
    session_id VARCHAR(100) COMMENT 'ID de session du visiteur',
    action ENUM('view', 'click', 'close', 'never_show') DEFAULT 'view' COMMENT 'Type d\'action',
    ip_address VARCHAR(45) COMMENT 'Adresse IP du visiteur',
    user_agent TEXT COMMENT 'User agent du navigateur',
    page_url VARCHAR(500) COMMENT 'URL de la page où la popup a été affichée',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (popup_id) REFERENCES popups(id) ON DELETE CASCADE,
    INDEX idx_popup_id (popup_id),
    INDEX idx_session (session_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
