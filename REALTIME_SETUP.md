Real-time Chat & Multi-language Setup

1) Install WebSocket server dependencies (in project root):

```bash
composer require cboden/ratchet
```

2) Start the WebSocket server (runs on port 8080):

```bash
php scripts/ws-server.php
```

3) Open the HTTP app and visit `/chat` to use the chat UI.

Notes:
- The chat UI connects to `ws://<host>:8080` and broadcasts messages to all connected clients.
- Language selection uses CodeIgniter language files in `app/Language/en/Chat.php` and `app/Language/es/Chat.php` and stores the choice in session.
- If you run the site over HTTPS, consider using a reverse proxy or configure `wss://` accordingly and open port 8080 for secure connections.
