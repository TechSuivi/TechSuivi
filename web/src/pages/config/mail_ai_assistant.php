<?php
// web/src/pages/config/mail_ai_assistant.php

// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}


?>


<div class="flex-between-center mb-20">

    <h1 class="m-0 text-xl font-bold text-color flex items-center gap-10">
        🤖 Assistant Rédaction IA
    </h1>
    <div class="flex gap-10">

        <div class="btn-group">
            <button class="btn btn-primary active" onclick="switchMode('draft')" id="btn-mode-draft" title="Mode Rédaction">
                ✍️ Rédaction
            </button>
            <button class="btn btn-secondary" onclick="switchMode('chat')" id="btn-mode-chat" title="Mode Chat">
                💬 Chat
            </button>
        </div>
        
        <select class="form-select w-auto" id="ai_rule">
            <option value="">📋 Ton / Règle (Défaut)</option>
            <!-- Chargé via JS -->
        </select>
    </div>
</div>

<div class="row">
    <!-- Mode RÉDACTION (Défaut) -->
    <div id="workspace-draft" class="col-12 flex gap-20">
        <!-- Zone Prompt (Gauche) -->
        <div class="card p-0 flex-1 flex flex-col h-full" style="min-height: 600px;">
            <div class="p-15 border-b flex-between-center bg-light-gray rounded-t-lg">
                <h5 class="m-0 font-bold">✍️ Votre demande</h5>
            </div>
            <div class="p-0 flex-1 flex flex-col relative">
                <textarea class="form-control border-0 h-full p-20 resize-none focus-visible-none" id="ai_prompt" 
                    placeholder="Examinez ce texte...&#10;Rédigez un mail pour...&#10;Traduisez en anglais..."
                    spellcheck="false" style="min-height: 400px;"></textarea>
            </div>
            <div class="p-15 border-t bg-light-gray rounded-b-lg flex-between-center">
                <small class="text-muted">Ctrl + Enter pour générer</small>
                <button type="button" class="btn btn-primary" id="btn-generate-ai" onclick="generateAiText()">
                    ✨ Générer le texte
                </button>
            </div>
        </div>

        <!-- Zone Résultat (Droite) -->
        <div class="card p-0 flex-1 flex flex-col h-full" style="min-height: 600px;">
            <div class="p-15 border-b flex-between-center bg-light-gray rounded-t-lg">
                <h5 class="m-0 font-bold">📄 Résultat</h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyAiText()" id="btn-copy" title="Copier le résultat">
                    📋 Copier
                </button>
            </div>
            <div class="p-0 flex-1 flex flex-col">
                <textarea class="form-control border-0 h-full p-20 resize-none bg-light" id="ai_result" 
                    placeholder="Le résultat apparaîtra ici..."
                    readonly style="min-height: 400px;"></textarea>
            </div>
        </div>
    </div>

    <!-- Mode CHAT -->
    <div id="workspace-chat" class="col-12 flex gap-20" style="display: none;">
        <!-- Chat Main Area (Left) -->
        <div class="card p-0 flex-1 flex flex-col" style="height: 600px;">
            <div class="flex-1 overflow-y-auto p-20 flex flex-col gap-15" id="chat-history">
                <div class="chat-message ai">
                    <div class="bubble">Bonjour ! Je suis votre assistant. Comment puis-je vous aider ? 👋</div>
                </div>
            </div>
            <div class="p-15 border-t bg-white rounded-b-lg flex gap-10">
                <textarea class="form-control flex-1 resize-none" id="chat_input" 
                    placeholder="Posez votre question..." rows="1"
                    onkeydown="handleChatKey(event)"></textarea>
                <button class="btn btn-primary w-50 flex-center" onclick="sendChatMessage()">
                    🚀
                </button>
            </div>
        </div>

        <!-- History Sidebar (Right) -->
        <div class="card p-0 w-300 flex flex-col" style="height: 600px;">
            <div class="p-15 border-b flex-between-center bg-light-gray">
                <h5 class="m-0 font-bold">📁 Historique</h5>
                <button class="btn btn-sm btn-primary" onclick="startNewChat()" title="Nouvelle conversation">
                    ➕
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-10" id="history-list">
                <!-- Chargé via JS -->
            </div>
        </div>
    </div>
</div>

<style>
/* Chat Messages Layout */
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

/* Chat Bubbles Styling */
.chat-message .bubble {
    padding: 12px 18px;
    font-size: 1em;
    line-height: 1.5;
    position: relative;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.chat-message.user .bubble {
    background: var(--primary-color, #0d6efd); 
    color: white;
    border-radius: 15px 15px 0 15px;
}

.chat-message.ai .bubble {
    background: #f8f9fa;
    color: #212529;
    border: 1px solid #dee2e6;
    border-radius: 15px 15px 15px 0;
}

body.dark .chat-message.ai .bubble {
    background: #343a40;
    color: #f8f9fa;
    border-color: #495057;
}

/* History Sidebar Items */
.history-item {
    padding: 10px;
    border-radius: 5px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
    transition: background-color 0.2s;
}
.history-item:hover {
    background-color: rgba(0,0,0,0.05);
}
.history-item.active {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary-color, #0d6efd);
    font-weight: bold;
}
body.dark .history-item:hover {
    background-color: rgba(255,255,255,0.05);
}

.btn-delete-chat {
    border: none;
    background: none;
    color: #adb5bd;
    cursor: pointer;
    opacity: 0.5;
    transition: opacity 0.2s, color 0.2s;
}
.history-item:hover .btn-delete-chat {
    opacity: 1;
}
.btn-delete-chat:hover {
    color: #dc3545;
}
</style>

<script>
// Chat History Memory
let currentConversationId = 0;
let chatHistory = []; 

document.addEventListener('DOMContentLoaded', () => {
    loadRules();
    
    // Restore Mode
    const savedMode = localStorage.getItem('ts_ai_mode');
    if (savedMode === 'chat') {
        switchMode('chat');
        const savedConvId = localStorage.getItem('ts_ai_conversation_id');
        if (savedConvId) {
            loadConversation(savedConvId);
        }
    }
});

// Mode Switching
function switchMode(mode) {
    localStorage.setItem('ts_ai_mode', mode);

    const btnDraft = document.getElementById('btn-mode-draft');
    const btnChat = document.getElementById('btn-mode-chat');
    
    if (mode === 'chat') {
        btnDraft.classList.remove('btn-primary', 'active');
        btnDraft.classList.add('btn-secondary');
        
        btnChat.classList.remove('btn-secondary');
        btnChat.classList.add('btn-primary', 'active');

        document.getElementById('workspace-draft').style.display = 'none';
        document.getElementById('workspace-chat').style.display = 'flex';
        
        if (!localStorage.getItem('ts_ai_conversation_id')) {
            loadChatHistoryList(); 
        }
        setTimeout(() => document.getElementById('chat_input').focus(), 100);
    } else {
        btnChat.classList.remove('btn-primary', 'active');
        btnChat.classList.add('btn-secondary');
        
        btnDraft.classList.remove('btn-secondary');
        btnDraft.classList.add('btn-primary', 'active');

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
                    <span class="text-truncate flex-grow-1">${conv.title}</span>
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
    localStorage.setItem('ts_ai_conversation_id', id);
    
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
            chatHistory = [];
            
            const ruleSelect = document.getElementById('ai_rule');
            if (ruleSelect) {
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
    localStorage.removeItem('ts_ai_conversation_id');
    
    const ruleSelect = document.getElementById('ai_rule');
    if (ruleSelect) ruleSelect.value = "";
    
    chatHistory = [];
    document.getElementById('chat-history').innerHTML = `
        <div class="chat-message ai">
            <div class="bubble">Nouvelle conversation démarrée ! Comment puis-je vous aider ? 👋</div>
        </div>
    `;
    loadChatHistoryList();
    document.getElementById('chat_input').focus();
}

function deleteConversation(e, id) {
    e.stopPropagation();
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
    e.target.style.height = 'auto';
    e.target.style.height = (e.target.scrollHeight) + 'px';
}

function sendChatMessage() {
    const input = document.getElementById('chat_input');
    const message = input.value.trim();
    if (!message) return;
    
    addChatBubble(message, 'user');
    input.value = '';
    input.style.height = 'auto';
    
    const loadingId = addChatBubble('...', 'ai', true);
    
    const formData = new FormData();
    formData.append('prompt', message);
    if (currentConversationId > 0) {
        formData.append('conversation_id', currentConversationId);
    }
    
    const ruleVal = document.getElementById('ai_rule').value;
    if (ruleVal) formData.append('rule_content', ruleVal);
    
    fetch('api/ai_actions.php', { method: 'POST', body: formData })
    .then(async r => {
        const isJson = r.headers.get('content-type')?.includes('application/json');
        const data = isJson ? await r.json() : null;
        
        if (data && data.conversation_id && currentConversationId === 0) {
            currentConversationId = data.conversation_id;
            localStorage.setItem('ts_ai_conversation_id', currentConversationId);
            loadChatHistoryList();
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
            loadChatHistoryList();
        } else {
            addChatBubble("Erreur: " + data.message, 'ai');
        }
    })
    .catch(e => {
        const loader = document.getElementById(loadingId);
        if(loader) loader.remove();
        addChatBubble("Erreur : " + e.message, 'ai');
    });
}

function addChatBubble(text, role, isLoading = false) {
    const historyDiv = document.getElementById('chat-history');
    const id = 'msg-' + Date.now();
    
    const msgDiv = document.createElement('div');
    msgDiv.className = `chat-message ${role}`;
    if (isLoading) msgDiv.id = id;
    
    // Convert newlines
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
    const btn = document.getElementById('btn-copy');
    
    if (!resultOutput.value) return;
    
    const originalText = btn.innerHTML;
    
    const successFeedback = () => {
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-success');
        btn.innerHTML = '✅ Copié !';
        
        setTimeout(() => { 
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
            btn.innerHTML = originalText;
        }, 2000);
    };

    if (navigator.clipboard) {
        navigator.clipboard.writeText(resultOutput.value).then(successFeedback);
    } else {
        resultOutput.select();
        document.execCommand('copy');
        successFeedback();
    }
}
</script>
