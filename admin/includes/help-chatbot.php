<?php
/**
 * PERSONNALY - Chatbot d'aide contextuel
 * Widget d'aide réutilisable pour chaque section admin
 */

// $helpContent doit être défini avant d'inclure ce fichier
if (!isset($helpContent)) {
    $helpContent = [];
}
?>

<!-- Bouton d'aide flottant -->
<div class="help-chatbot-trigger" id="helpChatbotTrigger" title="Besoin d'aide ?">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
    </svg>
</div>

<!-- Panel du chatbot -->
<div class="help-chatbot-panel" id="helpChatbotPanel">
    <div class="help-chatbot-header">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div class="help-chatbot-avatar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </div>
            <div>
                <div style="font-weight: 700; font-size: 1rem;">Assistant Personnaly</div>
                <div style="font-size: 0.75rem; opacity: 0.8;">Je suis là pour t'aider ! 👋</div>
            </div>
        </div>
        <button class="help-chatbot-close" id="helpChatbotClose">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>

    <div class="help-chatbot-content">
        <?php if (!empty($helpContent)): ?>
            <?php foreach ($helpContent as $section): ?>
                <div class="help-message">
                    <?php if (!empty($section['icon'])): ?>
                        <div class="help-message-icon"><?= $section['icon'] ?></div>
                    <?php endif; ?>

                    <?php if (!empty($section['title'])): ?>
                        <div class="help-message-title"><?= h($section['title']) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($section['description'])): ?>
                        <div class="help-message-description"><?= $section['description'] ?></div>
                    <?php endif; ?>

                    <?php if (!empty($section['items'])): ?>
                        <ul class="help-message-list">
                            <?php foreach ($section['items'] as $item): ?>
                                <li><?= $item ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($section['note'])): ?>
                        <div class="help-message-note"><?= $section['note'] ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="help-message">
                <div class="help-message-title">Aucune aide disponible</div>
                <div class="help-message-description">Le contenu d'aide n'a pas été chargé pour cette section.</div>
            </div>
        <?php endif; ?>
    </div>

    <div class="help-chatbot-footer">
        <small style="color: rgba(255,255,255,0.7); font-size: 0.75rem;">
            💡 Conseil : Teste toujours tes changements sur le site avant de valider !
        </small>
    </div>
</div>

<style>
/* Trigger Button */
.help-chatbot-trigger {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #6366F1, #8B5CF6);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
    transition: all 0.3s ease;
    z-index: 9999;
    animation: helpPulse 2s infinite;
}

.help-chatbot-trigger:hover {
    transform: scale(1.1);
    box-shadow: 0 12px 32px rgba(99, 102, 241, 0.6);
}

.help-chatbot-trigger svg {
    stroke: white;
}

@keyframes helpPulse {
    0%, 100% { box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4); }
    50% { box-shadow: 0 8px 24px rgba(99, 102, 241, 0.6), 0 0 0 8px rgba(99, 102, 241, 0.1); }
}

/* Panel */
.help-chatbot-panel {
    position: fixed;
    bottom: 110px;
    right: 30px;
    width: 420px;
    max-height: 600px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    display: none;
    flex-direction: column;
    z-index: 10000;
    overflow: hidden;
    animation: slideUp 0.3s ease;
}

.help-chatbot-panel.active {
    display: flex;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Header */
.help-chatbot-header {
    background: linear-gradient(135deg, #6366F1, #8B5CF6);
    color: white;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.help-chatbot-avatar {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.help-chatbot-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.help-chatbot-close:hover {
    background: rgba(255, 255, 255, 0.3);
}

.help-chatbot-close svg {
    stroke: white;
}

/* Content */
.help-chatbot-content {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #f9fafb;
}

.help-message {
    background: white;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.help-message:last-child {
    margin-bottom: 0;
}

.help-message-icon {
    font-size: 2rem;
    margin-bottom: 8px;
}

.help-message-title {
    font-weight: 700;
    font-size: 1.1rem;
    color: #1f2937;
    margin-bottom: 8px;
}

.help-message-description {
    color: #6b7280;
    font-size: 0.9rem;
    line-height: 1.6;
    margin-bottom: 12px;
}

.help-message-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.help-message-list li {
    padding: 8px 0 8px 28px;
    position: relative;
    color: #374151;
    font-size: 0.9rem;
    line-height: 1.5;
}

.help-message-list li:before {
    content: "✓";
    position: absolute;
    left: 0;
    color: #10b981;
    font-weight: 700;
}

.help-message-note {
    margin-top: 12px;
    padding: 12px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border-left: 3px solid #6366F1;
    border-radius: 6px;
    font-size: 0.85rem;
    color: #4338ca;
    font-style: italic;
}

/* Footer */
.help-chatbot-footer {
    padding: 16px 20px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.05));
    border-top: 1px solid #e5e7eb;
    text-align: center;
}

/* Responsive */
@media (max-width: 768px) {
    .help-chatbot-panel {
        width: calc(100vw - 40px);
        right: 20px;
        left: 20px;
        bottom: 100px;
    }

    .help-chatbot-trigger {
        right: 20px;
        bottom: 20px;
    }
}

/* Scrollbar styling */
.help-chatbot-content::-webkit-scrollbar {
    width: 6px;
}

.help-chatbot-content::-webkit-scrollbar-track {
    background: #f3f4f6;
}

.help-chatbot-content::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}

.help-chatbot-content::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const trigger = document.getElementById('helpChatbotTrigger');
    const panel = document.getElementById('helpChatbotPanel');
    const closeBtn = document.getElementById('helpChatbotClose');

    if (trigger && panel) {
        trigger.addEventListener('click', function() {
            panel.classList.toggle('active');
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                panel.classList.remove('active');
            });
        }

        // Fermer si clic en dehors
        document.addEventListener('click', function(e) {
            if (!trigger.contains(e.target) && !panel.contains(e.target)) {
                panel.classList.remove('active');
            }
        });
    }
});
</script>
