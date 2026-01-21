<?php
// web/src/pages/config/mail_ai_assistant.php

// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}


?>

<div class="ai-assistant-container">
    <div class="page-header assistant-header">
        <h1 class="assistant-title">
            <span>🤖</span> Assistant Rédaction IA
        </h1>
        <div class="assistant-controls">
            <div class="mode-toggle-wrapper">
                <button class="mode-btn active" onclick="switchMode('draft')" id="btn-mode-draft" title="Mode Rédaction">
                    <span class="colored-emoji">✍️</span> Rédaction
                </button>
                <button class="mode-btn" onclick="switchMode('chat')" id="btn-mode-chat" title="Mode Chat">
                    <span class="colored-emoji">💬</span> Chat
                </button>
            </div>
            <div class="rule-selector-wrapper">
                <span class="rule-icon">📋</span>
                <select class="form-select rule-select" id="ai_rule">
                    <option value="">Ton / Règle (Défaut)</option>
                    <!-- Chargé via JS -->
                </select>
            </div>
        </div>
    </div>

    <!-- Mode RÉDACTION (Défaut) -->
    <div id="workspace-draft" class="assistant-workspace">
        <!-- Zone Prompt (Gauche) -->
        <div class="workspace-panel prompt-panel">
            <div class="panel-header">
                <h5>✍️ Votre demande</h5>
            </div>
            <div class="panel-body">
                <textarea class="form-control assistant-textarea" id="ai_prompt" 
                    placeholder="Examinez ce texte...&#10;Rédigez un mail pour...&#10;Traduisez en anglais..."
                    spellcheck="false"></textarea>
                
                <div class="action-bar">
                    <button type="button" class="btn-generate" id="btn-generate-ai" onclick="generateAiText()">
                        ✨ Générer le texte
                    </button>
                    <small class="text-muted hint-text">Ctrl + Enter</small>
                </div>
            </div>
        </div>

        <!-- Zone Résultat (Droite) -->
        <div class="workspace-panel result-panel">
            <div class="panel-header">
                <h5>📄 Résultat</h5>
                <button type="button" class="btn-copy-icon" onclick="copyAiText()" id="btn-copy" title="Copier le résultat">
                    <span class="icon">📋</span>
                    <span class="label">Copier</span>
                </button>
            </div>
            <div class="panel-body">
                <textarea class="form-control assistant-textarea result-textarea" id="ai_result" 
                    placeholder="Le résultat apparaîtra ici..."
                    readonly></textarea>
            </div>
        </div>
    </div>

    <!-- Mode CHAT -->
    <div id="workspace-chat" class="assistant-workspace" style="display: none;">
        <!-- Chat Main Area (Left) -->
        <div class="workspace-panel chat-panel">
            <div class="chat-history" id="chat-history">
                <div class="chat-message ai">
                    <div class="bubble">Bonjour ! Je suis votre assistant. Comment puis-je vous aider aujourd'hui ? 👋</div>
                </div>
            </div>
            <div class="chat-input-area">
                <textarea class="form-control chat-textarea" id="chat_input" 
                    placeholder="Posez votre question..." rows="1"
                    onkeydown="handleChatKey(event)"></textarea>
                <button class="btn-send-chat" onclick="sendChatMessage()">
                    🚀
                </button>
            </div>
        </div>

        <!-- History Sidebar (Right) -->
        <div class="workspace-panel history-sidebar">
            <div class="panel-header">
                <h5>📁 Historique</h5>
                <button class="btn-new-chat" onclick="startNewChat()" title="Nouvelle conversation">
                    ➕
                </button>
            </div>
            <div class="history-list" id="history-list">
                <!-- Chargé via JS -->
            </div>
        </div>
    </div>
</div>

<style>
/* Layout Global */
.ai-assistant-container {
    height: calc(100vh - 140px);
    display: flex;
    flex-direction: column;
    padding: 0 10px 10px 10px;
    gap: 10px;
}

/* Header Spécifique */
.assistant-header {
    background: linear-gradient(135deg, #a855f7 0%, #d8b4fe 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(168, 85, 247, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.assistant-title {
    margin: 0;
    font-size: 1.25em;
    display: flex;
    align-items: center;
    gap: 10px;
}

.assistant-controls {
    display: flex;
    align-items: center;
    gap: 15px;
}

/* Toggle Mode */
.mode-toggle-wrapper {
    padding: 3px;
    border-radius: 30px;
    display: flex;
    border: 1px solid rgba(255,255,255,0.2);
}

/* High Specificity Overrides to kill the Blue */
#btn-mode-draft, #btn-mode-chat {
    background-color: transparent !important;
    background-image: none !important;
    background: transparent !important;
    border: none !important;
    color: rgba(255,255,255,0.8) !important;
    padding: 6px 15px;
    border-radius: 25px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9em;
    display: flex;
    align-items: center;
    gap: 6px;
    box-shadow: none !important;
}

#btn-mode-draft:hover, #btn-mode-chat:hover {
    background-color: rgba(255,255,255,0.1) !important;
    color: white !important;
}

#btn-mode-draft.active, #btn-mode-chat.active {
    background-color: white !important;
    background: white !important;
    color: #a855f7 !important;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
}

/* Hack to colorize Emojis */
.colored-emoji {
    color: transparent;
    text-shadow: 0 0 0 white; /* Default white for inactive */
    transition: text-shadow 0.2s;
}

.mode-btn.active .colored-emoji {
    text-shadow: 0 0 0 #a855f7; /* Purple for active */
}

/* Sélecteur de Règle */
.rule-selector-wrapper {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(5px);
    border-radius: 50px;
    padding: 4px 15px 4px 10px;
    display: flex;
    align-items: center;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.rule-icon {
    font-size: 1.1em;
    margin-right: 8px;
}

.rule-select {
    background: transparent;
    border: none;
    color: white;
    font-weight: 600;
    padding: 5px;
    cursor: pointer;
    min-width: 200px;
}

.rule-select:focus {
    background: #a855f7;
    box-shadow: none;
    color: white;
}

.rule-select option {
    background: white;
    color: #333;
}

/* Workspace Split View */
.assistant-workspace {
    display: flex;
    flex: 1;
    gap: 20px;
    min-height: 0;
}

.workspace-panel {
    flex: 1;
    background: var(--card-bg, #fff);
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(0,0,0,0.05);
}

/* Headers */
.panel-header {
    height: 55px;
    padding: 0 20px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    background: rgba(0,0,0,0.02);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.panel-header h5 {
    margin: 0;
    font-weight: 600;
    color: var(--accent-color, #a855f7);
}

.panel-body {
    padding: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    position: relative;
    background: transparent; 
}

.result-textarea, .assistant-textarea {
    flex: 1;
    border: none;
    padding: 20px;
    resize: none;
    font-size: 1.1em;
    line-height: 1.6;
    background: transparent;
    color: inherit;
    width: 100%;
}

.assistant-textarea:focus {
    box-shadow: none;
    outline: none;
}

.result-textarea {
    background: #fafafa;
    font-family: 'Segoe UI', system-ui, sans-serif;
}

/* Barre d'action */
.action-bar {
    padding: 15px 20px;
    background: white;
    border-top: 1px solid rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

/* Dark Mode Overrides */
body.dark .workspace-panel {
    background: #2c2c2c;
    color: #e0e0e0;
}
body.dark .panel-header {
    border-bottom-color: rgba(255,255,255,0.05);
    background: rgba(255,255,255,0.02);
}
body.dark .panel-header h5 {
    color: #d8b4fe;
}
body.dark .result-textarea {
    background: #333;
    color: #e0e0e0;
}
body.dark .action-bar {
    background: #2c2c2c;
    border-top-color: rgba(255,255,255,0.05);
}
body.dark .assistant-textarea:focus {
    background: rgba(255,255,255,0.02);
}

/* Boutons */
.btn-generate {
    background: linear-gradient(135deg, #a855f7 0%, #c084fc 100%);
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 1.05em;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(168, 85, 247, 0.3);
    transition: transform 0.2s, box-shadow 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-generate:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(168, 85, 247, 0.4);
}

.btn-copy-icon {
    background: transparent;
    border: 1px solid #e0e0e0;
    color: #666;
    padding: 6px 15px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 0.9em;
    font-weight: 500;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-copy-icon:hover {
    background: #f0f0f0;
    color: #333;
    border-color: #ccc;
}
.btn-copy-icon.success {
    background: #dcfce7;
    color: #166534;
    border-color: #bbf7d0;
}

/* Chat UI */
.chat-panel {
    background: #f4f6f8;
}
.chat-history {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.chat-message {
    display: flex;
    max-width: 80%;
}
.chat-message.user {
    align-self: flex-end;
}
.chat-message.ai {
    align-self: flex-start;
}
.chat-message .bubble {
    padding: 12px 18px;
    border-radius: 18px;
    font-size: 1em;
    line-height: 1.5;
    position: relative;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
.chat-message.user .bubble {
    background: #a855f7;
    color: white;
    border-bottom-right-radius: 4px;
}
.chat-message.ai .bubble {
    background: white;
    color: #333;
    border-bottom-left-radius: 4px;
}
.chat-input-area {
    padding: 15px;
    background: white;
    border-top: 1px solid #e0e0e0;
    display: flex;
    gap: 10px;
    align-items: flex-end;
}
.chat-textarea {
    border-radius: 20px;
    border: 1px solid #e0e0e0;
    padding: 12px 15px;
    resize: none;
    max-height: 150px;
}
.chat-textarea:focus {
    box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.1);
    border-color: #a855f7;
}
.btn-send-chat {
    background: #a855f7;
    color: white;
    border: none;
    width: 45px;
    height: 45px;
    border-radius: 50%;
    font-size: 1.2em;
    cursor: pointer;
    transition: transform 0.2s;
    flex-shrink: 0;
}
.btn-send-chat:hover {
    transform: scale(1.1);
}

/* Dark Mode Chat */
body.dark .chat-panel {
    background: #1f1f1f;
}
body.dark .chat-message.ai .bubble {
    background: #2c2c2c;
    color: #e0e0e0;
}
body.dark .chat-input-area {
    background: #2c2c2c;
    border-top-color: #444;
}
body.dark .chat-textarea {
    background: #333;
    border-color: #444;
    color: white;
}
/* Side Bar History */
.history-sidebar {
    flex: 1; /* Sidebar width */
    max-width: 300px;
    background: white;
    display: flex;
    flex-direction: column;
}

.history-list {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
}

.history-item {
    padding: 10px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s;
    margin-bottom: 5px;
    border: 1px solid transparent;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9em;
    color: #555;
}

.history-item:hover {
    background: #f3f4f6;
}

.history-item.active {
    background: #f3e8ff;
    color: #a855f7;
    border-color: #d8b4fe;
    font-weight: 600;
}

.history-item .title {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
}

.btn-delete-chat {
    background: transparent;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    margin-left: 5px;
    opacity: 0;
    transition: opacity 0.2s;
}

.history-item:hover .btn-delete-chat {
    opacity: 1;
}

.btn-delete-chat:hover {
    color: #ef4444;
    background: #fee2e2;
}

.btn-new-chat {
    background: #a855f7;
    color: white;
    border: none;
    border-radius: 5px;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-new-chat:hover {
    background: #9333ea;
}

/* Dark Mode Sidebar */
body.dark .history-sidebar {
    background: #2c2c2c;
    border-color: #444;
}
body.dark .history-item {
    color: #d1d5db;
}
body.dark .history-item:hover {
    background: #374151;
}
body.dark .history-item.active {
    background: #4c1d95; /* Darker purple */
    color: #e9d5ff;
    border-color: #6d28d9;
}
body.dark .btn-delete-chat:hover {
    background: #7f1d1d;
}
</style>

<script>
// Chat History Memory
let currentConversationId = 0;
let chatHistory = []; // Only for local display purpose now


document.addEventListener('DOMContentLoaded', () => {
    loadRules();
    
    // Restore Mode
    const savedMode = localStorage.getItem('ts_ai_mode');
    if (savedMode === 'chat') {
        switchMode('chat');
        // Restore Conversation
        const savedConvId = localStorage.getItem('ts_ai_conversation_id');
        if (savedConvId) {
            loadConversation(savedConvId);
        }
    }
});

// Mode Switching
function switchMode(mode) {
    // Save state
    localStorage.setItem('ts_ai_mode', mode);

    // Buttons
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('btn-mode-' + mode).classList.add('active');
    
    // Workspaces
    if (mode === 'chat') {
        document.getElementById('workspace-draft').style.display = 'none';
        document.getElementById('workspace-chat').style.display = 'flex';
        // Only load list if we are not about to load a specific conversation (optimization optional, but let's just load it)
        // If loadConversation is called immediately after, it will reload the list with active state
        if (!localStorage.getItem('ts_ai_conversation_id')) {
            loadChatHistoryList(); 
        }
        // Focus logic
        setTimeout(() => document.getElementById('chat_input').focus(), 100);
    } else {
        document.getElementById('workspace-chat').style.display = 'none';
        document.getElementById('workspace-draft').style.display = 'flex';
    }
}

// History Management
function loadChatHistoryList() {
    const listDiv = document.getElementById('history-list');
    const formData = new FormData();
    formData.append('action', 'list_conversations');
    
    fetch('api/ai_actions.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            listDiv.innerHTML = '';
            data.conversations.forEach(conv => {
                const item = document.createElement('div');
                item.className = 'history-item ' + (conv.id == currentConversationId ? 'active' : '');
                item.onclick = () => loadConversation(conv.id);
                
                item.innerHTML = `
                    <span class="title">${conv.title}</span>
                    <button class="btn-delete-chat" onclick="deleteConversation(event, ${conv.id})" title="Supprimer">
                        🗑️
                    </button>
                `;
                listDiv.appendChild(item);
            });
        }
    });
}


function loadConversation(id) {
    currentConversationId = id;
    localStorage.setItem('ts_ai_conversation_id', id); // Persist
    
    loadChatHistoryList(); // Update active class
    
    const historyDiv = document.getElementById('chat-history');
    historyDiv.innerHTML = '<div class="chat-message ai"><div class="bubble"><i class="fas fa-spinner fa-spin"></i> Chargement...</div></div>';
    
    const formData = new FormData();
    formData.append('action', 'load_conversation');
    formData.append('id', id);
    
    fetch('api/ai_actions.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            historyDiv.innerHTML = '';
            chatHistory = []; // Reset local array
            
            // Restore rule/tone in UI
            const ruleSelect = document.getElementById('ai_rule');
            if (ruleSelect) {
                // If the rule exists in the list, select it. If not (custom or deleted), it might defaupt to empty.
                // Or we can try to set it value.
                ruleSelect.value = data.rule || ""; 
            }

            if (data.messages.length === 0) {
                 historyDiv.innerHTML = '<div class="chat-message ai"><div class="bubble">Conversation vide.</div></div>';
            }
            
            data.messages.forEach(msg => {
                addChatBubble(msg.text, msg.role === 'model' ? 'ai' : 'user');
                chatHistory.push({ role: msg.role === 'model' ? 'model' : 'user', text: msg.text });
            });
            historyDiv.scrollTop = historyDiv.scrollHeight;
        }
    });
}

function startNewChat() {
    currentConversationId = 0;
    localStorage.removeItem('ts_ai_conversation_id'); // Clear persistence
    
    // Reset Rule to default for new chat
    const ruleSelect = document.getElementById('ai_rule');
    if (ruleSelect) ruleSelect.value = "";
    
    chatHistory = [];
    document.getElementById('chat-history').innerHTML = `
        <div class="chat-message ai">
            <div class="bubble">Nouvelle conversation démarrée ! Comment puis-je vous aider ? 👋</div>
        </div>
    `;
    loadChatHistoryList(); // Clear active selection
    document.getElementById('chat_input').focus();
}

function deleteConversation(e, id) {
    e.stopPropagation(); // Prevent click on parent
    if (!confirm('Supprimer cette conversation ?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_conversation');
    formData.append('id', id);
    
    fetch('api/ai_actions.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (currentConversationId === id) {
                startNewChat();
            } else {
                loadChatHistoryList();
            }
        }
    });
}

// Chat Logic
function handleChatKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendChatMessage();
    }
    // Auto-resize textarea
    e.target.style.height = 'auto';
    e.target.style.height = (e.target.scrollHeight) + 'px';
}

function sendChatMessage() {
    const input = document.getElementById('chat_input');
    const message = input.value.trim();
    if (!message) return;
    
    // 1. Add User Message
    addChatBubble(message, 'user');
    input.value = '';
    input.style.height = 'auto'; // Reset height
    
    // 2. Loading state
    const loadingId = addChatBubble('...', 'ai', true);
    
    // 3. API Call
    const formData = new FormData();
    formData.append('prompt', message);
    if (currentConversationId > 0) {
        formData.append('conversation_id', currentConversationId);
    }
    
    // Send rule only if it's the start (locally checked) or if we are starting new
    // The backend now handles history so we don't send 'history' JSON anymore
    // But we might want to send rule_content for the context
    const ruleVal = document.getElementById('ai_rule').value;
    if (ruleVal) formData.append('rule_content', ruleVal);
    
    fetch('api/ai_actions.php', { method: 'POST', body: formData })
    .then(async r => {
        const isJson = r.headers.get('content-type')?.includes('application/json');
        const data = isJson ? await r.json() : null;
        
        // If we have an ID even in error/success, grab it
        if (data && data.conversation_id && currentConversationId === 0) {
            currentConversationId = data.conversation_id;
            localStorage.setItem('ts_ai_conversation_id', currentConversationId);
            loadChatHistoryList(); // Refresh list to show the new conv
        }

        if (!r.ok) {
            const errorMsg = (data && data.message) || r.statusText || "Erreur serveur " + r.status;
            throw new Error(errorMsg);
        }
        if (!data) throw new Error("Réponse vide ou invalide du serveur");
        return data;
    })
    .then(data => {
        document.getElementById(loadingId).remove();
        
        if (data.success) {
            addChatBubble(data.text, 'ai');
            loadChatHistoryList(); // Refresh timestamp
        } else {
            addChatBubble("Erreur: " + data.message, 'ai');
        }
    })
    .catch(e => {
        document.getElementById(loadingId).remove();
        addChatBubble("Erreur : " + e.message, 'ai');
    });
}

function addChatBubble(text, role, isLoading = false) {
    const historyDiv = document.getElementById('chat-history');
    const id = 'msg-' + Date.now();
    
    const msgDiv = document.createElement('div');
    msgDiv.className = `chat-message ${role}`;
    if (isLoading) msgDiv.id = id;
    
    // Convert newlines to breaks for display
    const formattedText = text.replace(/\n/g, '<br>');
    
    msgDiv.innerHTML = `<div class="bubble">${isLoading ? '<i class="fas fa-spinner fa-spin"></i>' : formattedText}</div>`;
    
    historyDiv.appendChild(msgDiv);
    historyDiv.scrollTop = historyDiv.scrollHeight;
    
    return id;
}

// Basic Functions
function loadRules() {
    const select = document.getElementById('ai_rule');
    const formData = new FormData();
    formData.append('action', 'list_rules');
    
    fetch('api/ai_actions.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.rules) {
            data.rules.forEach(rule => {
                const opt = document.createElement('option');
                opt.value = rule.content;
                opt.textContent = rule.name;
                select.appendChild(opt);
            });
        }
    });
}

function generateAiText() {
    const promptInput = document.getElementById('ai_prompt');
    const resultOutput = document.getElementById('ai_result');
    const btn = document.getElementById('btn-generate-ai');
    const ruleSelect = document.getElementById('ai_rule');
    
    const prompt = promptInput.value.trim();
    if (!prompt) {
        alert('Veuillez entrer une demande pour l\'IA');
        promptInput.focus();
        return;
    }
    
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Génération en cours...';
    btn.disabled = true;
    resultOutput.value = ''; 
    
    const formData = new FormData();
    formData.append('prompt', prompt);
    
    if (ruleSelect.value) {
        formData.append('rule_content', ruleSelect.value);
    }
    
    fetch('api/ai_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(async r => {
        const isJson = r.headers.get('content-type')?.includes('application/json');
        const data = isJson ? await r.json() : null;
        
        if (!r.ok) {
             const errorMsg = (data && data.message) || r.statusText || "Erreur serveur " + r.status;
             throw new Error(errorMsg);
        }
        if (!data) throw new Error("Réponse vide ou invalide");
        return data;
    })
    .then(data => {
        if (data.success) {
            resultOutput.value = data.text;
        } else {
            resultOutput.value = "Erreur : " + data.message;
        }
    })
    .catch(error => {
        resultOutput.value = "Erreur : " + error.message;
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function copyAiText() {
    const resultOutput = document.getElementById('ai_result');
    if (!resultOutput.value) return;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(resultOutput.value).then(() => {
            const btn = document.getElementById('btn-copy');
            const iconSpan = btn.querySelector('.icon');
            const labelSpan = btn.querySelector('.label');
            
            const originalIcon = iconSpan.innerHTML;
            const originalLabel = labelSpan.innerHTML;
            
            btn.classList.add('success');
            iconSpan.innerHTML = '✅';
            labelSpan.innerHTML = 'Copié !';
            
            setTimeout(() => { 
                btn.classList.remove('success');
                iconSpan.innerHTML = originalIcon;
                labelSpan.innerHTML = originalLabel;
            }, 2000);
        });
    } else {
        resultOutput.select();
        document.execCommand('copy');
        alert('Texte copié !');
    }
}
</script>
