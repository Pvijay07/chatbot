<!doctype html>
<html lang="<?= $locale ?>">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= lang('Chat.title') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --surface: #1e293b;
            --surface-hover: #334155;
            --user: #3b82f6;
            --assistant: #1e293b;
            --text: #f8fafc;
            --text-dim: #94a3b8;
            --accent: #6366f1;
            --border: #334155;
            --glass: rgba(30, 41, 59, 0.7);
        }

        * {
            box-sizing: border-box;
            scrollbar-width: thin;
            scrollbar-color: var(--border) transparent;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            margin: 0;
            background: var(--bg);
            color: var(--text);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* --- Header --- */
        .chat-header {
            padding: 1rem 2rem;
            background: var(--glass);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 10;
        }

        .chat-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-brand .logo {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--user), var(--accent));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #fff;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .chat-title {
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        /* --- Main Chat --- */
        .chat-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        #messages {
            width: 100%;
            max-width: 800px;
            padding: 2rem 1rem;
            overflow-y: auto;
            scroll-behavior: smooth;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* --- Message Bubbles --- */
        .message {
            display: flex;
            gap: 16px;
            max-width: 85%;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.user {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            flex-shrink: 0;
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
        }

        .message.user .avatar {
            background: var(--user);
            border: none;
        }

        .bubble-container {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .bubble {
            padding: 12px 16px;
            border-radius: 16px;
            line-height: 1.6;
            font-size: 0.95rem;
            background: var(--assistant);
            border: 1px solid var(--border);
            color: var(--text);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .message.user .bubble {
            background: var(--user);
            border: none;
            color: #fff;
        }

        .meta {
            font-size: 11px;
            color: var(--text-dim);
            padding: 0 4px;
        }

        .message.user .meta {
            text-align: right;
        }

        .system-msg {
            align-self: center;
            background: var(--surface);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            color: var(--text-dim);
            border: 1px solid var(--border);
        }

        /* --- Input Bar --- */
        .input-outer {
            padding: 1.5rem 2rem;
            background: linear-gradient(0deg, var(--bg) 0%, transparent 100%);
            display: flex;
            justify-content: center;
        }

        .input-container {
            width: 100%;
            max-width: 800px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 8px 12px 8px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .input-container:focus-within {
            border-color: var(--user);
            box-shadow: 0 8px 32px rgba(59, 130, 246, 0.15);
        }

        textarea {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--text);
            font-family: inherit;
            font-size: 0.95rem;
            resize: none;
            outline: none;
            padding: 8px 0;
            height: 24px;
            max-height: 150px;
        }

        .input-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .icon-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: transparent;
            color: var(--text-dim);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .icon-btn:hover {
            background: var(--surface-hover);
            color: var(--text);
        }

        .send-btn {
            background: var(--user);
            color: #fff;
        }

        .send-btn:hover {
            background: #2563eb;
            transform: scale(1.05);
        }

        .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* --- Markdown Tweaks --- */
        .bubble pre {
            background: #000 !important;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            overflow-x: auto;
        }

        .bubble code {
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 0.85rem;
        }

        .bubble p:first-child {
            margin-top: 0;
        }

        .bubble p:last-child {
            margin-bottom: 0;
        }

        /* --- Scrollbar --- */
        #messages::-webkit-scrollbar {
            width: 6px;
        }

        #messages::-webkit-scrollbar-track {
            background: transparent;
        }

        #messages::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 10px;
        }

        #messages::-webkit-scrollbar-thumb:hover {
            background: var(--text-dim);
        }

        @media (max-width: 640px) {
            .chat-header {
                padding: 0.8rem 1rem;
            }

            .input-outer {
                padding: 1rem;
            }

            .message {
                max-width: 92%;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@2.4.0/dist/purify.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism-tomorrow.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js"></script>
</head>

<body>
    <header class="chat-header">
        <div class="chat-brand">
            <div class="logo">P</div>
            <div class="chat-title">Petsfolio AI</div>
        </div>
        <div class="lang-selector">
            <form method="post" action="<?= ('http://localhost/chatbot/public/language') ?>" style="display:inline">
                <?= csrf_field() ?>
                <select name="locale" id="locale-selector" onchange="this.form.submit()"
                    style="background:var(--surface);color:var(--text);border:1px solid var(--border);padding:6px 12px;border-radius:8px;font-size:13px;outline:none;">
                    <option value="en" <?= ($locale === 'en') ? 'selected' : '' ?>>English</option>
                    <option value="es" <?= ($locale === 'es') ? 'selected' : '' ?>>Español</option>
                    <option value="de" <?= ($locale === 'de') ? 'selected' : '' ?>>Deutsch</option>
                    <option value="fr" <?= ($locale === 'fr') ? 'selected' : '' ?>>Français</option>
                    <option value="zh" <?= ($locale === 'zh') ? 'selected' : '' ?>>中文</option>
                    <option value="ja" <?= ($locale === 'ja') ? 'selected' : '' ?>>日本語</option>
                    <option value="hi" <?= ($locale === 'hi') ? 'selected' : '' ?>>हिन्दी</option>
                    <option value="pt" <?= ($locale === 'pt') ? 'selected' : '' ?>>Português</option>
                </select>
            </form>
        </div>
    </header>

    <main class="chat-wrapper">
        <div id="messages"></div>
    </main>

    <div class="input-outer">
        <div class="input-container">
            <textarea id="input" placeholder="Ask about pet insurance..." rows="1"></textarea>
            <div class="input-actions">
                <button id="upload-doc" class="icon-btn" title="Upload Document">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="17 8 12 3 7 8" />
                        <line x1="12" y1="3" x2="12" y2="15" />
                    </svg>
                </button>
                <input type="file" id="file-input" style="display:none;" accept=".txt,.pdf,.docx,.json">
                <button id="clear" class="icon-btn" title="Clear Chat">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                        <line x1="10" y1="11" x2="10" y2="17" />
                        <line x1="14" y1="11" x2="14" y2="17" />
                    </svg>
                </button>
                <button id="send" class="icon-btn send-btn" title="Send (Enter)">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="22" y1="2" x2="11" y2="13" />
                        <polyline points="22 2 15 22 11 13 2 9 22 2" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <script>
        const wsUrl = (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.hostname + ':8080';
        let conn;
        try {
            conn = new WebSocket(wsUrl);
        } catch (e) {
            console.error('WebSocket init failed', e);
        }

        const messagesEl = document.getElementById('messages');
        const inputEl = document.getElementById('input');
        const sendBtn = document.getElementById('send');

        // --- Word-by-Word Animation Logic ---
        const playbackQueues = {}; // id -> string
        const activeTimers = {}; // id -> intervalId

        function processPlayback(id) {
            if (!playbackQueues[id] || playbackQueues[id].length === 0) {
                // Check if the stream is marked as 'done' and queue is empty
                if (window._isDone && window._isDone[id]) {
                    clearInterval(activeTimers[id]);
                    delete activeTimers[id];
                }
                return;
            }

            const bubble = document.querySelector(`[data-response-id="${id}"] .bubble`);
            if (!bubble) return;

            // Take a chunk of characters or just one
            // Higher number = faster "typing"
            const chunkSize = Math.max(1, Math.ceil(playbackQueues[id].length / 10));
            const toAdd = playbackQueues[id].substring(0, chunkSize);
            playbackQueues[id] = playbackQueues[id].substring(chunkSize);

            const currentFullText = (bubble.dataset.playingText || '') + toAdd;
            bubble.dataset.playingText = currentFullText;

            // Render markdown on the partial text
            bubble.innerHTML = renderMarkdown(currentFullText);

            // Scroll to bottom
            messagesEl.scrollTop = messagesEl.scrollHeight;

            // Optional: Highlight code if present
            if (currentFullText.includes('```')) {
                bubble.querySelectorAll('pre code').forEach((c) => {
                    if (window.Prism) Prism.highlightElement(c);
                });
            }
        }

        function startPlayback(id) {
            if (activeTimers[id]) return;
            activeTimers[id] = setInterval(() => processPlayback(id), 30); // 30ms for smooth feel
        }

        function ts() {
            return new Date().toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function renderMarkdown(md) {
            try {
                const html = marked.parse(md || '');
                return DOMPurify.sanitize(html);
            } catch (e) {
                return escapeHtml(md);
            }
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": "&#39;"
            } [c]));
        }

        function addMessage(obj) {
            if (typeof obj === 'string') {
                const el = document.createElement('div');
                el.className = 'system-msg';
                el.textContent = obj;
                messagesEl.appendChild(el);
                messagesEl.scrollTop = messagesEl.scrollHeight;
                return;
            }

            const who = obj.who || 'assistant';
            const text = obj.text || '';
            const id = obj.id || null;

            if (who === 'user') {
                const el = document.createElement('div');
                el.className = 'message user';
                el.innerHTML = `
                    <div class="avatar">U</div>
                    <div class="bubble-container">
                        <div class="bubble">${escapeHtml(text)}</div>
                        <div class="meta">${ts()}</div>
                    </div>
                `;
                messagesEl.appendChild(el);
                messagesEl.scrollTop = messagesEl.scrollHeight;
                return;
            }

            // Assistant with ID means streaming source
            if (id) {
                if (!playbackQueues[id]) playbackQueues[id] = '';
                playbackQueues[id] += text;

                let el = messagesEl.querySelector(`[data-response-id="${id}"]`);
                if (!el) {
                    el = document.createElement('div');
                    el.className = 'message assistant';
                    el.setAttribute('data-response-id', id);
                    el.innerHTML = `
                        <div class="avatar">AI</div>
                        <div class="bubble-container">
                            <div class="bubble" data-playing-text=""></div>
                            <div class="meta">${ts()}</div>
                        </div>
                    `;
                    messagesEl.appendChild(el);
                    startPlayback(id);
                }
                return;
            }

            // Fallback for static non-stream assistant messages
            const el = document.createElement('div');
            el.className = 'message assistant';
            el.innerHTML = `
                <div class="avatar">AI</div>
                <div class="bubble-container">
                    <div class="bubble">${renderMarkdown(text)}</div>
                    <div class="meta">${ts()}</div>
                </div>
            `;
            messagesEl.appendChild(el);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        if (conn) {
            conn.onopen = () => addMessage('Connected to AI server');
            conn.onclose = () => addMessage('Disconnected from server');
            conn.onmessage = (e) => {
                let d = e.data;
                try {
                    d = JSON.parse(e.data);
                } catch (err) {}

                if (d && d.type === 'partial') {
                    addMessage({
                        id: d.id,
                        text: d.text
                    });
                    return;
                }

                if (d && d.type === 'done') {
                    if (d.id) {
                        if (!window._isDone) window._isDone = {};
                        window._isDone[d.id] = true;

                        // Final cleanup check: if the content was JSON, we might need a jump update
                        // after the playback queue finishes. ThePlayback loop handles it naturally,
                        // but let's ensure the final text is clean.
                        setTimeout(() => {
                            const bubble = document.querySelector(`[data-response-id="${d.id}"] .bubble`);
                            if (bubble) {
                                let txt = bubble.dataset.playingText || '';
                                try {
                                    const p = JSON.parse(txt.trim());
                                    if (p.message || p.response) {
                                        const clean = p.message || p.response;
                                        bubble.dataset.playingText = clean;
                                        bubble.innerHTML = renderMarkdown(clean);
                                    }
                                } catch (e) {}
                            }
                        }, 500);
                    }
                    return;
                }

                if (d && d.type === 'broadcast') {
                    addMessage(d.message);
                    return;
                }

                if (d && typeof d === 'object') {
                    const txt = d.message || d.response || JSON.stringify(d);
                    addMessage({
                        who: 'assistant',
                        text: txt
                    });
                } else {
                    addMessage({
                        who: 'assistant',
                        text: String(d)
                    });
                }
            };
        }

        // --- Event Handlers ---
        inputEl.addEventListener('input', () => {
            inputEl.style.height = '24px';
            inputEl.style.height = (inputEl.scrollHeight) + 'px';
        });

        function doSend() {
            const text = inputEl.value.trim();
            if (!text) return;
            const id = Date.now().toString(36);
            addMessage({
                who: 'user',
                text: text
            });

            const locale = document.getElementById('locale-selector').value;
            const payload = JSON.stringify({
                type: 'ask',
                id: id,
                prompt: text,
                locale: locale
            });

            if (conn && conn.readyState === WebSocket.OPEN) {
                conn.send(payload);
            } else {
                addMessage('Error: Server connection lost');
            }

            inputEl.value = '';
            inputEl.style.height = '24px';
        }

        sendBtn.addEventListener('click', doSend);
        inputEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                doSend();
            }
        });

        document.getElementById('clear').addEventListener('click', () => {
            messagesEl.innerHTML = '';
            // Stop all active typing animations
            for (let id in activeTimers) {
                clearInterval(activeTimers[id]);
                delete activeTimers[id];
            }
            // Clear all queues
            for (let id in playbackQueues) {
                delete playbackQueues[id];
            }
        });

        const fileInput = document.getElementById('file-input');
        document.getElementById('upload-doc').addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', async (e) => {
            const file = e.target.files?.[0];
            if (!file) return;
            addMessage(`Uploading document: ${file.name}...`);

            const formData = new FormData();
            formData.append('file', file);
            const question = prompt('Ask a question about the document (or leave empty):') || '';
            if (question) formData.append('question', question);

            try {
                const res = await fetch('<?= ('http://localhost/chatbot/public/api/document/upload') ?>', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    addMessage(`Document uploaded: ${data.filename}`);
                    if (data.text) addMessage(`Extracted text preview: \n\n${data.text.substring(0, 300)}...`);
                    if (data.analysis) {
                        const id = Date.now().toString(36);
                        addMessage({
                            who: 'assistant',
                            text: data.analysis,
                            id: id
                        });
                    }
                } else {
                    addMessage(`Upload error: ${data.message || 'Unknown error'}`);
                }
            } catch (err) {
                addMessage(`Upload failed: ${err.message}`);
            }
            fileInput.value = '';
        });
    </script>
</body>

</html>