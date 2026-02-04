<?php
/**
 * PERSONNALY - Popup Template
 * Affiche les popups actifs selon leurs conditions de déclenchement
 */

// Charger le modèle Popup si pas déjà fait
if (!class_exists('Popup')) {
    require_once __DIR__ . '/../models/Popup.php';
}

// Récupérer le popup actif
$popupModel = new Popup();
$activePopup = $popupModel->getActivePopup();

// Pas de popup à afficher
if (!$activePopup) {
    return;
}

$popupId = $activePopup['id'];
$trigger = $activePopup['trigger_type'] ?? 'delay';
$triggerValue = $activePopup['trigger_value'] ?? 3;
?>
<!-- Popup Overlay -->
<div class="popup-overlay" id="popup-<?= $popupId ?>" data-popup-id="<?= $popupId ?>" data-trigger="<?= htmlspecialchars($trigger) ?>" data-trigger-value="<?= (int)$triggerValue ?>">
    <div class="popup-content">
        <button class="popup-close" aria-label="Fermer">&times;</button>
        <?php if (!empty($activePopup['image_url'])): ?>
        <img src="<?= htmlspecialchars($activePopup['image_url']) ?>" alt="<?= htmlspecialchars($activePopup['title']) ?>" class="popup-image">
        <?php endif; ?>
        <div class="popup-body">
            <?php if (!empty($activePopup['title'])): ?>
            <h3 class="popup-title"><?= htmlspecialchars($activePopup['title']) ?></h3>
            <?php endif; ?>
            <?php if (!empty($activePopup['content'])): ?>
            <div class="popup-text"><?= nl2br(htmlspecialchars($activePopup['content'])) ?></div>
            <?php endif; ?>
            <?php if (!empty($activePopup['cta_text'])): ?>
            <a href="<?= htmlspecialchars($activePopup['cta_url'] ?? '#') ?>" class="btn btn-primary popup-cta"><?= htmlspecialchars($activePopup['cta_text']) ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    const popup = document.getElementById('popup-<?= $popupId ?>');
    if (!popup) return;

    const popupId = popup.dataset.popupId;
    const trigger = popup.dataset.trigger;
    const triggerValue = parseInt(popup.dataset.triggerValue) || 3;
    const storageKey = 'popup_shown_' + popupId;

    // Vérifier si déjà affiché pendant cette session
    if (sessionStorage.getItem(storageKey)) {
        popup.remove();
        return;
    }

    function showPopup() {
        popup.classList.add('active');
        sessionStorage.setItem(storageKey, '1');
    }

    function closePopup() {
        popup.classList.remove('active');
    }

    // Fermer le popup
    popup.querySelector('.popup-close').addEventListener('click', closePopup);
    popup.addEventListener('click', function(e) {
        if (e.target === popup) closePopup();
    });

    // Touche Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && popup.classList.contains('active')) {
            closePopup();
        }
    });

    // Déclencher selon le type
    switch (trigger) {
        case 'delay':
            setTimeout(showPopup, triggerValue * 1000);
            break;

        case 'scroll':
            const scrollThreshold = triggerValue; // pourcentage
            let triggered = false;
            window.addEventListener('scroll', function() {
                if (triggered) return;
                const scrollPercent = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;
                if (scrollPercent >= scrollThreshold) {
                    triggered = true;
                    showPopup();
                }
            });
            break;

        case 'exit_intent':
            let exitTriggered = false;
            document.addEventListener('mouseout', function(e) {
                if (exitTriggered) return;
                if (e.clientY < 10 && e.relatedTarget === null) {
                    exitTriggered = true;
                    showPopup();
                }
            });
            break;

        case 'immediate':
            // Petit délai pour éviter le flash
            setTimeout(showPopup, 500);
            break;

        default:
            setTimeout(showPopup, 3000);
    }
})();
</script>
