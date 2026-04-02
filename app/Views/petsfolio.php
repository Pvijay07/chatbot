<?php $config = ['apiBase' => $apiBase, 'defaultLocale' => $defaultLocale, 'viewerUserId' => $viewerUserId ?? 0]; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Petsfolio Insurance Assistant v3</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f3f4ef;
            --bg-soft: #f7f7f3;
            --card: #ffffff;
            --ink: #161816;
            --muted: #6a7068;
            --line: rgba(22, 24, 22, 0.09);
            --accent: #202421;
            --accent-dark: #111311;
            --teal: #202421;
            --chip: rgba(22, 24, 22, 0.04);
            --shadow: 0 10px 26px rgba(22, 24, 22, 0.04);
            --shadow-soft: 0 4px 12px rgba(22, 24, 22, 0.03);
            --r: 18px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--ink);
            font: 16px/1.6 Manrope, sans-serif;
        }

        button, input, select, textarea { font: inherit; }
        button { cursor: pointer; }
        .hidden { display: none !important; }
        .mobile-only { display: none; }
        .screen { padding: 20px; }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--r);
            box-shadow: var(--shadow);
        }

        .auth {
            max-width: 1160px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.08fr 0.92fr;
            gap: 20px;
        }

        .hero,
        .authbox,
        .sidebar,
        .chat,
        .panel {
            padding: 24px;
        }

        .hero {
            display: grid;
            gap: 16px;
            align-content: start;
            min-height: auto;
        }

        h1, h2, h3 {
            margin: 0;
            font-family: Manrope, sans-serif;
            line-height: 1.12;
            letter-spacing: -0.03em;
        }

        .hero h1 {
            font-size: clamp(2.2rem, 3vw, 3.4rem);
            max-width: 12ch;
        }
        .muted { color: var(--muted); }

        .row,
        .top {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .chips,
        .tabs,
        .prompts {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .chip,
        .soft {
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--chip);
            color: var(--ink);
            font-weight: 600;
            font-size: 0.86rem;
        }

        .brand {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: #fff;
            border: 1px solid var(--line);
            display: grid;
            place-items: center;
            color: var(--ink);
            font: 700 1rem Manrope, sans-serif;
        }

        .grid3 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .tile,
        .box,
        .usercard,
        .chatitem,
        .msg {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: none;
        }

        .tile {
            padding: 16px;
            display: grid;
            gap: 6px;
        }

        .tile strong { display: block; }

        .field,
        .area,
        .select {
            width: 100%;
            padding: 13px 14px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            outline: none;
        }

        .area {
            min-height: 112px;
            resize: vertical;
        }

        .btn,
        .tab,
        .prompt {
            border: none;
            border-radius: 12px;
            padding: 11px 14px;
            font-weight: 600;
        }

        .btn.primary,
        .tab.active {
            background: var(--accent);
            color: #fff;
        }

        .btn.primary:hover,
        .tab.active:hover {
            background: var(--accent-dark);
        }

        .btn.ghost,
        .tab,
        .prompt {
            background: #fff;
            border: 1px solid var(--line);
            color: var(--ink);
        }

        .prompt { padding: 9px 12px; }

        .stack { display: grid; gap: 12px; }

        .shell {
            min-height: 100vh;
            padding: 16px;
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 16px;
            position: relative;
            max-width: 1440px;
            margin: 0 auto;
        }

        main {
            min-width: 0;
        }

        .sidebar {
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            gap: 16px;
            min-height: calc(100vh - 32px);
            position: sticky;
            top: 16px;
        }

        .panelWrap {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 16px;
            min-height: calc(100vh - 128px);
        }

        .chat,
        .panel {
            display: grid;
            gap: 14px;
            min-height: 0;
        }

        .panel {
            display: none;
        }

        .chat {
            grid-template-rows: auto minmax(0, 1fr) auto auto;
        }

        .panel {
            grid-template-rows: auto minmax(0, 1fr);
        }

        .history,
        .messages,
        .docs,
        .forms {
            display: grid;
            gap: 12px;
            align-content: start;
            overflow: auto;
            min-height: 0;
        }

        .box,
        .usercard,
        .chatitem,
        .msg {
            padding: 14px;
        }

        .chatitem {
            text-align: left;
            transition: transform 0.18s ease, border-color 0.18s ease;
        }

        .chatitem:hover {
            transform: none;
            background: var(--bg-soft);
        }

        .chatitem.active {
            border-color: rgba(22, 24, 22, 0.26);
            background: var(--bg-soft);
        }

        .msg.user {
            justify-self: end;
            background: var(--bg-soft);
            max-width: 78%;
        }

        .msg.assistant { max-width: 88%; }

        .meta {
            display: flex;
            gap: 10px;
            color: var(--muted);
            font-size: 0.84rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .body { white-space: pre-wrap; }

        .sources {
            display: grid;
            gap: 8px;
            margin-top: 10px;
        }

        .src {
            padding: 10px 12px;
            border-radius: 12px;
            background: var(--bg-soft);
            color: var(--ink);
            font-size: 0.86rem;
        }

        .composer {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            padding: 10px;
            display: grid;
            gap: 12px;
            position: sticky;
            bottom: 0;
        }

        .composerRow,
        .grid2 {
            display: grid;
            gap: 12px;
        }

        .composerRow { grid-template-columns: 1fr auto; }
        .grid2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }

        .plans {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }

        .plans .box {
            border-top: 1px solid var(--line);
        }

        .dots {
            display: inline-flex;
            gap: 5px;
        }

        .dots span {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--ink);
            animation: bounce 1s infinite ease-in-out;
        }

        .dots span:nth-child(2) { animation-delay: 0.15s; }
        .dots span:nth-child(3) { animation-delay: 0.3s; }

        @keyframes bounce {
            0%, 80%, 100% { transform: translateY(0); opacity: 0.45; }
            40% { transform: translateY(-5px); opacity: 1; }
        }

        .toast {
            position: fixed;
            right: 20px;
            bottom: 20px;
            background: #20382f;
            color: #fff;
            padding: 14px 16px;
            border-radius: 14px;
            opacity: 0;
            transform: translateY(8px);
            pointer-events: none;
            transition: 0.18s;
            z-index: 50;
        }

        .toast.show {
            opacity: 1;
            transform: none;
        }

        .sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 17, 15, 0.16);
            border: 0;
            padding: 0;
            z-index: 20;
        }

        .hero .chips,
        #prompts,
        #statusBadge,
        #mBadge,
        #tBadge,
        #logoutBtn,
        #guardBox {
            display: none !important;
        }

        @media (max-width: 1080px) {
            .auth,
            .panelWrap {
                grid-template-columns: 1fr;
            }

            .shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: min(360px, 88vw);
                min-height: 100vh;
                border-radius: 0 22px 22px 0;
                z-index: 30;
                transform: translateX(-104%);
                transition: transform 0.22s ease;
                overflow: auto;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .mobile-only {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .panelWrap {
                min-height: auto;
            }
        }

        @media (max-width: 720px) {
            .screen,
            .shell {
                padding: 12px;
            }

            .hero,
            .authbox,
            .sidebar,
            .chat,
            .panel {
                padding: 16px;
            }

            .hero {
                min-height: auto;
            }

            .grid3,
            .grid2,
            .composerRow {
                grid-template-columns: 1fr;
            }

            .top {
                flex-direction: column;
                align-items: flex-start;
            }

            .msg.user,
            .msg.assistant {
                max-width: 100%;
            }

            .prompts,
            .tabs,
            .chips {
                gap: 8px;
            }
        }
    </style>
</head>
<body>
<div id="authScreen" class="screen hidden">
    <div class="auth">
        <section class="hero card">
            <div class="stack">
                <div class="brand">P</div>
                <span class="soft" id="heroBadge">Petsfolio Insurance Assistant</span>
                <h1 id="heroTitle">Pet insurance answers grounded in policy data.</h1>
                <p class="muted" id="heroCopy">Plans, pricing, claim guidance, multilingual chat, memory, and strict RAG guardrails in one production-ready assistant.</p>
                <div class="grid3">
                    <div class="tile"><strong id="f1">Strict RAG</strong><span class="muted" id="f1c">Only answers from seeded plans and insurance documents.</span></div>
                    <div class="tile"><strong id="f2">Fast Memory</strong><span class="muted" id="f2c">Last 5 messages are reused for context.</span></div>
                    <div class="tile"><strong id="f3">Admin Ready</strong><span class="muted" id="f3c">Update plans and upload insurance documents.</span></div>
                </div>
            </div>
            <div class="chips"><span class="chip" id="c1">Insurance-only</span><span class="chip" id="c2">English + Hindi</span><span class="chip" id="c3">JWT auth</span></div>
        </section>
        <section class="authbox card">
            <div class="top"><div><h2 id="authTitle">Sign in to continue</h2><p class="muted" id="authCopy">Use the seeded demo users or create a new account.</p></div><select id="authLocale" class="select" style="max-width:140px"><option value="en">English</option><option value="hi">हिंदी</option><option value="te">తెలుగు</option><option value="kn">ಕನ್ನಡ</option><option value="ta">தமிழ்</option></select></div>
            <div class="tabs" style="margin:18px 0"><button id="loginTab" class="tab active" type="button">Login</button><button id="registerTab" class="tab" type="button">Register</button></div>
            <form id="loginForm" class="stack"><input class="field" name="email" type="email" placeholder="Email"><input class="field" name="password" type="password" placeholder="Password"><button class="btn primary" type="submit" id="loginBtn">Login</button><p class="muted" id="demoText">Admin: admin@petsfolio.local / Password123! | User: user@petsfolio.local / Password123!</p></form>
            <form id="registerForm" class="stack hidden"><input class="field" name="name" type="text" placeholder="Full name"><input class="field" name="email" type="email" placeholder="Email"><input class="field" name="password" type="password" placeholder="Password"><button class="btn primary" type="submit" id="registerBtn">Create account</button></form>
        </section>
    </div>
</div>

<button id="sidebarBackdrop" class="sidebar-backdrop hidden" type="button" aria-label="Close navigation"></button>
<div id="shell" class="shell hidden">
    <aside id="sidebar" class="sidebar card">
        <div class="top"><div class="row"><div class="brand" style="width:46px;height:46px;font-size:1.2rem">P</div><div><strong>Petsfolio</strong><div class="muted" id="shellSub">Insurance Assistant</div></div></div><div class="row"><button id="sidebarClose" class="btn ghost mobile-only" type="button">Close</button><button id="logoutBtn" class="btn ghost" type="button">Logout</button></div></div>
        <div class="usercard"><div class="top"><div><strong id="userName">User</strong><div class="muted" id="userRole">Policyholder</div></div></div><div class="stack" style="margin-top:12px"><button id="newChatBtn" class="btn primary" type="button">New chat</button><select id="appLocale" class="select"><option value="en">English</option><option value="hi">हिंदी</option><option value="te">తెలుగు</option><option value="kn">ಕನ್ನಡ</option><option value="ta">தமிழ்</option></select></div></div>
        <div><div class="top" style="margin-bottom:10px"><strong id="historyTitle">Conversation History</strong><span class="muted" id="historyHint">Memory on</span></div><div id="chatList" class="history"></div></div>
        <div id="guardBox" class="box"><strong id="guardTitle">Guardrails</strong><p class="muted" id="guardCopy">Answers stay inside the provided Petsfolio plan and document data.</p></div>
    </aside>
    <main class="stack">
        <div class="box top"><div><h2 id="workTitle">Ask about coverage, claims, and pricing</h2></div><div class="chips"><button id="sidebarToggle" class="btn ghost mobile-only" type="button" aria-controls="sidebar" aria-expanded="false">Menu</button><span class="chip" id="mBadge">Last 5 messages as memory</span><span class="chip" id="tBadge">Typing indicator enabled</span></div></div>
        <div class="panelWrap">
            <section class="chat card">
                <div id="prompts" class="prompts"></div>
                <div id="messages" class="messages"></div>
                <div id="typing" class="muted hidden"><span id="typingText">Assistant is thinking</span> <span class="dots"><span></span><span></span><span></span></span></div>
                <form id="composer" class="composer">
                    <textarea id="messageInput" class="area" rows="3" placeholder="Ask about coverage, pricing, or claims..."></textarea>
                    <div class="composerRow">
                        <div class="row" style="gap:8px">
                            <input type="file" id="fileInput" class="hidden" accept=".txt,.pdf,.docx,.json">
                            <button id="uploadDocBtn" class="btn ghost" type="button" title="Upload document for analysis" style="padding: 8px 12px">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                            </button>
                            <span class="muted" id="composerHint" style="font-size:0.85rem">Only pet insurance questions are supported.</span>
                        </div>
                        <button class="btn primary" type="submit" id="sendBtn">Send</button>
                    </div>
                </form>
            </section>
            <aside class="panel card">
                <div class="tabs"><button class="tab active" type="button" data-panel="catalog" id="catalogTab">Plan catalog</button><button class="tab hidden" type="button" data-panel="admin" id="adminTab">Admin panel</button></div>
                <div id="catalogPanel" class="stack"><div class="box"><strong id="catalogTitle">Current Petsfolio plans</strong><p class="muted" id="catalogCopy">These are the grounded records used for price, reimbursement, deductible, and waiting period answers.</p></div><div id="planList" class="plans"></div></div>
                <div id="adminPanel" class="stack hidden">
                    <div class="tabs"><button class="tab active" type="button" data-admin="plans" id="plansTab">Manage plans</button><button class="tab" type="button" data-admin="docs" id="docsTab">Manage documents</button></div>
                    <div id="adminPlans" class="forms"></div>
                    <form id="newPlanForm" class="box stack"></form>
                    <div id="docsPanel" class="stack hidden"><form id="docForm" class="box stack"><strong id="uploadTitle">Upload insurance document</strong><input class="field" type="file" name="file" accept=".txt,.pdf,.docx,.json"><select class="select" name="language"><option value="en">English</option><option value="hi">हिंदी</option><option value="te">తెలుగు</option><option value="kn">ಕನ್ನಡ</option><option value="ta">தமிழ்</option></select><button class="btn primary" id="uploadBtn" type="submit">Upload document</button></form><div id="docList" class="docs"></div></div>
                </div>
            </aside>
        </div>
    </main>
</div>

<div id="toast" class="toast"></div>
<script>
const APP = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const L = {en:{heroTitle:'Pet insurance answers grounded in policy data.',heroCopy:'Plans, pricing, claim guidance, multilingual chat, memory, and strict RAG guardrails in one production-ready assistant.',f1:'Strict RAG',f1c:'Only answers from seeded plans and insurance documents.',f2:'Fast Memory',f2c:'Last 5 messages are reused for context.',f3:'Admin Ready',f3c:'Update plans and upload insurance documents.',c1:'Insurance-only',c2:'English + Hindi',c3:'JWT auth',authTitle:'Sign in to continue',authCopy:'Use the seeded demo users or create a new account.',demoText:'Admin: admin@petsfolio.local / Password123! | User: user@petsfolio.local / Password123!',login:'Login',register:'Register',create:'Create account',logout:'Logout',shellSub:'Insurance Assistant',roleAdmin:'Administrator',roleUser:'Policyholder',newChat:'New chat',historyTitle:'Conversation History',historyHint:'Memory on',guardTitle:'Guardrails',guardCopy:'Answers stay inside the provided Petsfolio plan and document data.',statusBadge:'Knowledge grounded',workTitle:'Ask about coverage, claims, and pricing',mBadge:'Last 5 messages as memory',tBadge:'Typing indicator enabled',typingText:'Assistant is thinking',placeholder:'Ask about coverage, pricing, or claims...',composerHint:'Only pet insurance questions are supported.',send:'Send',catalogTab:'Plan catalog',adminTab:'Admin panel',catalogTitle:'Current Petsfolio plans',catalogCopy:'These are the grounded records used for price, reimbursement, deductible, and waiting period answers.',plansTab:'Manage plans',docsTab:'Manage documents',uploadTitle:'Upload insurance document',uploadBtn:'Upload document',noChats:'No conversations yet. Start with a plan or claim question.',empty:'Ask about policy coverage, compare dog or cat plans, or get claim guidance.',sources:'Sources',price:'Price',limit:'Annual limit',reimb:'Reimbursement',ded:'Deductible',wait:'Waiting period',save:'Save',newPlan:'Create new plan',uploaded:'Document uploaded and indexed.',saved:'Plan saved.',logged:'Welcome back.',registered:'Account created.',created:'New chat ready.',generic:'Something went wrong.',p1:'Recommend the best dog plan',p2:'Compare cat plans',p3:'How do I file a claim?',p4:'Explain waiting periods'},hi:{heroTitle:'असल पॉलिसी डेटा पर आधारित पालतू बीमा जवाब।',heroCopy:'प्लान, कीमत, क्लेम सपोर्ट, बहुभाषी चैट, मेमोरी और सख्त RAG गार्डरेल्स के लिए तैयार असिस्टेंट।',f1:'सख्त RAG',f1c:'जवाब केवल seeded plans और insurance documents से आते हैं।',f2:'तेज़ मेमोरी',f2c:'संदर्भ के लिए अंतिम 5 messages दोबारा भेजे जाते हैं।',f3:'एडमिन तैयार',f3c:'प्लान अपडेट करें और insurance documents अपलोड करें।',c1:'सिर्फ बीमा जवाब',c2:'अंग्रेज़ी + हिंदी',c3:'JWT auth',authTitle:'जारी रखने के लिए साइन इन करें',authCopy:'Seeded demo users इस्तेमाल करें या नया खाता बनाएं।',demoText:'Admin: admin@petsfolio.local / Password123! | User: user@petsfolio.local / Password123!',login:'लॉगिन',register:'रजिस्टर',create:'खाता बनाएं',logout:'लॉगआउट',shellSub:'इंश्योरेंस असिस्टेंट',roleAdmin:'एडमिनिस्ट्रेटर',roleUser:'पॉलिसीहोल्डर',newChat:'नई चैट',historyTitle:'कन्वर्सेशन हिस्ट्री',historyHint:'मेमोरी चालू',guardTitle:'गार्डरेल्स',guardCopy:'जवाब केवल दिए गए Petsfolio plan और document data के अंदर रहते हैं।',statusBadge:'डेटा-आधारित जवाब',workTitle:'कवरेज, क्लेम और कीमत के बारे में पूछें',mBadge:'मेमोरी में अंतिम 5 संदेश',tBadge:'टाइपिंग इंडिकेटर चालू',typingText:'असिस्टेंट सोच रहा है',placeholder:'कवर, कीमत या क्लेम के बारे में पूछें...',composerHint:'केवल pet insurance सवाल समर्थित हैं।',send:'भेजें',catalogTab:'प्लान कैटलॉग',adminTab:'एडमिन पैनल',catalogTitle:'मौजूदा Petsfolio प्लान',catalogCopy:'यही grounded records कीमत, reimbursement, deductible और waiting period के जवाब देते हैं।',plansTab:'प्लान मैनेज करें',docsTab:'दस्तावेज़ मैनेज करें',uploadTitle:'बीमा दस्तावेज़ अपलोड करें',uploadBtn:'दस्तावेज़ अपलोड करें',noChats:'अभी कोई बातचीत नहीं है। प्लान या क्लेम सवाल से शुरू करें।',empty:'पॉलिसी कवर पूछें, dog/cat plans compare करें, या claim guidance लें।',sources:'स्रोत',price:'कीमत',limit:'वार्षिक सीमा',reimb:'रीइम्बर्समेंट',ded:'डिडक्टिबल',wait:'वेटिंग पीरियड',save:'सहेजें',newPlan:'नया प्लान बनाएं',uploaded:'दस्तावेज़ अपलोड होकर index हो गया।',saved:'प्लान सहेजा गया।',logged:'वापसी पर स्वागत है।',registered:'खाता बन गया।',created:'नई चैट तैयार है।',generic:'कुछ गलत हुआ।',p1:'सबसे अच्छा dog plan सुझाइए',p2:'cat plans की तुलना करें',p3:'क्लेम कैसे फाइल करूँ?',p4:'waiting period समझाइए'}};
L.te={workTitle:'కవరేజ్, క్లెయిమ్‌లు మరియు ధర గురించి అడగండి',historyTitle:'చాట్ చరిత్ర',historyHint:'మెమరీ ఆన్',guardTitle:'గార్డ్‌రైల్స్',guardCopy:'ఇచ్చిన బీమా డేటా లోపల ఉన్న సమాచారానికే సమాధానాలు పరిమితం అవుతాయి.',typingText:'అసిస్టెంట్ ఆలోచిస్తోంది',placeholder:'కవరేజ్, ధర లేదా క్లెయిమ్ గురించి అడగండి...',composerHint:'పెట్ ఇన్షూరెన్స్ ప్రశ్నలకే మద్దతు ఉంది.',send:'పంపు',newChat:'కొత్త చాట్',noChats:'ఇంకా సంభాషణలు లేవు. ఒక ప్లాన్ లేదా క్లెయిమ్ ప్రశ్నతో ప్రారంభించండి.',empty:'పాలసీ కవర్, ధర, క్లెయిమ్ లేదా సిఫారసు గురించి అడగండి.',sources:'మూలాలు',created:'కొత్త చాట్ సిద్ధంగా ఉంది.',generic:'ఏదో తప్పు జరిగింది.',p1:'ఉత్తమ డాగ్ ప్లాన్ సూచించండి',p2:'క్యాట్ ప్లాన్‌లను పోల్చండి',p3:'క్లెయిమ్ ఎలా దాఖలు చేయాలి?',p4:'వెయిటింగ్ పీరియడ్ వివరించండి'};
L.kn={workTitle:'ಕವರ್, ಕ್ಲೈಮ್ ಮತ್ತು ಬೆಲೆ ಬಗ್ಗೆ ಕೇಳಿ',historyTitle:'ಚಾಟ್ ಇತಿಹಾಸ',historyHint:'ಮೆಮೊರಿ ಆನ್',guardTitle:'ಗಾರ್ಡ್‌ರೇಲ್ಸ್',guardCopy:'ನೀಡಲಾದ ವಿಮಾ ಡೇಟಾದೊಳಗಿನ ಮಾಹಿತಿಗಷ್ಟೇ ಉತ್ತರಗಳು ಸೀಮಿತವಾಗಿರುತ್ತವೆ.',typingText:'ಅಸಿಸ್ಟೆಂಟ್ ಯೋಚಿಸುತ್ತಿದೆ',placeholder:'ಕವರ್, ಬೆಲೆ ಅಥವಾ ಕ್ಲೈಮ್ ಬಗ್ಗೆ ಕೇಳಿ...',composerHint:'ಪೆಟ್ ಇನ್ಶೂರೆನ್ಸ್ ಪ್ರಶ್ನೆಗಳಿಗಷ್ಟೇ ಬೆಂಬಲ ಇದೆ.',send:'ಕಳುಹಿಸಿ',newChat:'ಹೊಸ ಚಾಟ್',noChats:'ಇನ್ನೂ ಸಂಭಾಷಣೆಗಳಿಲ್ಲ. ಒಂದು ಯೋಜನೆ ಅಥವಾ ಕ್ಲೈಮ್ ಪ್ರಶ್ನೆಯಿಂದ ಆರಂಭಿಸಿ.',empty:'ಪಾಲಿಸಿ ಕವರ್, ಬೆಲೆ, ಕ್ಲೈಮ್ ಅಥವಾ ಶಿಫಾರಸಿನ ಬಗ್ಗೆ ಕೇಳಿ.',sources:'ಮೂಲಗಳು',created:'ಹೊಸ ಚಾಟ್ ಸಿದ್ಧವಾಗಿದೆ.',generic:'ಏನೋ ತಪ್ಪಾಗಿದೆ.',p1:'ಉತ್ತಮ ಡಾಗ್ ಪ್ಲಾನ್ ಸೂಚಿಸಿ',p2:'ಕ್ಯಾಟ್ ಪ್ಲಾನ್‌ಗಳನ್ನು ಹೋಲಿಸಿ',p3:'ಕ್ಲೈಮ್ ಹೇಗೆ ಸಲ್ಲಿಸಬೇಕು?',p4:'ವೇಟಿಂಗ್ ಪೀರಿಯಡ್ ವಿವರಿಸಿ'};
L.ta={workTitle:'கவர், க்ளெயிம் மற்றும் விலை பற்றி கேளுங்கள்',historyTitle:'அரட்டை வரலாறு',historyHint:'நினைவகம் இயக்கத்தில்',guardTitle:'கார்ட்ரெயில்ஸ்',guardCopy:'வழங்கப்பட்ட காப்பீட்டு தரவிலுள்ள தகவல்களுக்குள் மட்டுமே பதில்கள் இருக்கும்.',typingText:'அசிஸ்டென்ட் யோசிக்கிறது',placeholder:'கவர், விலை அல்லது க்ளெயிம் பற்றி கேளுங்கள்...',composerHint:'செல்லப்பிராணி காப்பீட்டு கேள்விகளுக்கே ஆதரவு உள்ளது.',send:'அனுப்பு',newChat:'புதிய அரட்டை',noChats:'இன்னும் உரையாடல்கள் இல்லை. ஒரு திட்டம் அல்லது க்ளெயிம் கேள்வியுடன் தொடங்குங்கள்.',empty:'பாலிசி கவர், விலை, க்ளெயிம் அல்லது பரிந்துரை பற்றி கேளுங்கள்.',sources:'ஆதாரங்கள்',created:'புதிய அரட்டை தயாராக உள்ளது.',generic:'ஏதோ தவறு ஏற்பட்டது.',p1:'சிறந்த நாய் திட்டத்தை பரிந்துரைக்கவும்',p2:'பூனை திட்டங்களை ஒப்பிடவும்',p3:'க்ளெயிம் எப்படி சமர்ப்பிப்பது?',p4:'காத்திருப்பு காலத்தை விளக்கவும்'};
const S = {
    token: '',
    user: {
        id: Number(APP.viewerUserId || 0),
        name: 'Petsfolio Guest',
        role: 'user',
        preferred_locale: localStorage.getItem('petsfolio_locale') || APP.defaultLocale || 'en'
    },
    chats: [],
    messages: [],
    plans: [],
    adminPlans: [],
    docs: [],
    chatId: null,
    locale: localStorage.getItem('petsfolio_locale') || APP.defaultLocale || 'en'
};
const STREAM = {
    controller: null
};
const $ = id => document.getElementById(id);
const T = k => (L[S.locale] && L[S.locale][k]) || L.en[k] || k;
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const isMobile = () => window.matchMedia('(max-width: 1080px)').matches;

function syncSidebar() {
    const open = $('sidebar').classList.contains('open') && isMobile();
    $('sidebarBackdrop').classList.toggle('hidden', !open);
    $('sidebarToggle').setAttribute('aria-expanded', open ? 'true' : 'false');
    document.body.style.overflow = open ? 'hidden' : '';
}

function openSidebar() {
    if (!isMobile()) return;
    $('sidebar').classList.add('open');
    syncSidebar();
}

function closeSidebar() {
    $('sidebar').classList.remove('open');
    syncSidebar();
}

function toast(msg) {
    $('toast').textContent = msg;
    $('toast').classList.add('show');
    clearTimeout(toast.t);
    toast.t = setTimeout(() => $('toast').classList.remove('show'), 2200);
}

async function api(path, opt = {}) {
    opt.headers = opt.headers || {};
    if (S.token) opt.headers.Authorization = `Bearer ${S.token}`;
    opt.headers['X-Locale'] = S.locale;
    if (!(opt.body instanceof FormData)) {
        opt.headers['Content-Type'] = 'application/json';
        if (opt.body && typeof opt.body !== 'string') opt.body = JSON.stringify(opt.body);
    }
    const r = await fetch(`${APP.apiBase}/${path}`, opt);
    const j = await r.json().catch(() => ({status:false, message:T('generic')}));
    if (!r.ok || j.status === false) throw new Error(j.message || T('generic'));
    return j.data;
}

function cancelActiveStream() {
    if (!STREAM.controller) {
        return;
    }

    STREAM.controller.abort();
    STREAM.controller = null;
}

function findStreamingMessageIndex(requestId) {
    return S.messages.findIndex(message => message.sender === 'assistant' && message.request_id === requestId);
}

function addStreamingPlaceholder(requestId) {
    S.messages = [...S.messages, {
        sender: 'assistant',
        message: '',
        created_at: new Date().toISOString(),
        request_id: requestId,
        streaming: true
    }];
    renderMessages();
}

function patchStreamingMessage(requestId) {
    const index = findStreamingMessageIndex(requestId);
    if (index === -1) {
        return;
    }

    const article = $('messages').querySelector(`[data-request-id="${requestId}"]`);
    if (!article) {
        renderMessages();
        return;
    }

    const message = S.messages[index];
    const body = article.querySelector('.body');
    if (!body) {
        renderMessages();
        return;
    }

    body.innerHTML = message.streaming && !message.message
        ? '<span class="dots"><span></span><span></span><span></span></span>'
        : esc(message.message);

    $('messages').scrollTop = $('messages').scrollHeight;
}

function appendStreamingChunk(requestId, text) {
    let index = findStreamingMessageIndex(requestId);
    if (index === -1) {
        addStreamingPlaceholder(requestId);
        index = findStreamingMessageIndex(requestId);
    }

    if (index === -1) {
        return;
    }

    S.messages[index].message += text;
    S.messages[index].streaming = true;
    $('typing').classList.add('hidden');
    patchStreamingMessage(requestId);
}

function finishStreamingMessage(requestId) {
    const index = findStreamingMessageIndex(requestId);
    if (index === -1) {
        return;
    }

    S.messages[index].streaming = false;
    patchStreamingMessage(requestId);
}

function failStreamingMessage(requestId, message) {
    const index = findStreamingMessageIndex(requestId);
    if (index === -1) {
        toast(message);
        return;
    }

    S.messages[index].message = S.messages[index].message || message;
    S.messages[index].streaming = false;
    patchStreamingMessage(requestId);
}

function latestMessageText() {
    for (let i = S.messages.length - 1; i >= 0; i -= 1) {
        const message = S.messages[i];
        if (typeof message.message === 'string' && message.message.trim() !== '') {
            return message.message;
        }
    }

    return '';
}

function syncStreamedChat(chat) {
    if (chat && chat.id) {
        S.chatId = Number(chat.id);
    }

    if (!S.chatId) {
        return;
    }

    const existing = S.chats.find(item => Number(item.id) === Number(S.chatId)) || {};
    const summary = {
        ...existing,
        id: Number(S.chatId),
        title: chat?.title || existing.title || T('newChat'),
        pet_type: chat?.pet_type ?? existing.pet_type ?? null,
        updated_at: chat?.updated_at || new Date().toISOString(),
        last_message: latestMessageText(),
        last_message_at: chat?.updated_at || new Date().toISOString()
    };

    S.chats = [summary, ...S.chats.filter(item => Number(item.id) !== Number(summary.id))];
    renderChats();
}

async function handleStreamEvent(eventName, payload) {
    if (!payload || typeof payload !== 'object') {
        return;
    }

    if (eventName === 'ready') {
        return;
    }

    if (eventName === 'partial') {
        appendStreamingChunk(String(payload.id || ''), String(payload.text || ''));
        return;
    }

    if (eventName === 'error') {
        $('typing').classList.add('hidden');
        $('sendBtn').disabled = false;
        STREAM.controller = null;
        failStreamingMessage(String(payload.id || ''), String(payload.message || T('generic')));
        return;
    }

    if (eventName === 'done') {
        $('typing').classList.add('hidden');
        $('sendBtn').disabled = false;
        STREAM.controller = null;
        finishStreamingMessage(String(payload.id || ''));
        syncStreamedChat(payload.chat || null);
    }
}

function readSseEvent(rawEvent) {
    const lines = rawEvent.replace(/\r/g, '').split('\n');
    let eventName = 'message';
    const dataLines = [];

    lines.forEach(line => {
        if (line.startsWith('event:')) {
            eventName = line.slice(6).trim();
            return;
        }

        if (line.startsWith('data:')) {
            dataLines.push(line.slice(5).trimStart());
        }
    });

    if (dataLines.length === 0) {
        return null;
    }

    try {
        return {
            event: eventName,
            payload: JSON.parse(dataLines.join('\n'))
        };
    } catch (_) {
        return null;
    }
}

async function streamChat(message, requestId) {
    cancelActiveStream();
    addStreamingPlaceholder(requestId);

    const controller = new AbortController();
    STREAM.controller = controller;
    let sawDone = false;

    const headers = {
        'Accept': 'text/event-stream',
        'Content-Type': 'application/json',
        'X-Locale': S.locale
    };

    if (S.token) {
        headers.Authorization = `Bearer ${S.token}`;
    }

    const response = await fetch(`${APP.apiBase}/chat/stream`, {
        method: 'POST',
        headers,
        body: JSON.stringify({
            chat_id: S.chatId,
            message,
            locale: S.locale,
            request_id: requestId
        }),
        signal: controller.signal
    });

    if (!response.ok) {
        STREAM.controller = null;
        let messageText = T('generic');

        try {
            const errorPayload = await response.json();
            messageText = errorPayload.message || messageText;
        } catch (_) {
            const rawText = await response.text().catch(() => '');
            if (rawText.trim() !== '') {
                messageText = rawText.trim();
            }
        }

        throw new Error(messageText);
    }

    if (!response.body) {
        STREAM.controller = null;
        throw new Error('Petsfolio streaming is not available in this browser.');
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
        const { value, done } = await reader.read();

        if (done) {
            break;
        }

        buffer += decoder.decode(value, { stream: true });
        let boundary = buffer.indexOf('\n\n');

        while (boundary !== -1) {
            const rawEvent = buffer.slice(0, boundary);
            buffer = buffer.slice(boundary + 2);

            const parsed = readSseEvent(rawEvent);
            if (parsed !== null) {
                if (parsed.event === 'done') {
                    sawDone = true;
                }
                await handleStreamEvent(parsed.event, parsed.payload);
            }

            boundary = buffer.indexOf('\n\n');
        }
    }

    const tail = decoder.decode();
    if (tail !== '') {
        buffer += tail;
    }

    if (buffer.trim() !== '') {
        const parsed = readSseEvent(buffer);
        if (parsed !== null) {
            if (parsed.event === 'done') {
                sawDone = true;
            }
            await handleStreamEvent(parsed.event, parsed.payload);
        }
    }

    if (!controller.signal.aborted && !sawDone) {
        STREAM.controller = null;
        throw new Error('Petsfolio streaming ended before the full reply was received.');
    }
}

function setCopy() {
    document.documentElement.lang = S.locale;
    if ($('authLocale')) $('authLocale').value = S.locale;
    if ($('appLocale')) $('appLocale').value = S.locale;
    [['heroTitle','heroTitle'],['heroCopy','heroCopy'],['f1','f1'],['f1c','f1c'],['f2','f2'],['f2c','f2c'],['f3','f3'],['f3c','f3c'],['c1','c1'],['c2','c2'],['c3','c3'],['authTitle','authTitle'],['authCopy','authCopy'],['demoText','demoText'],['shellSub','shellSub'],['historyTitle','historyTitle'],['historyHint','historyHint'],['guardTitle','guardTitle'],['guardCopy','guardCopy'],['statusBadge','statusBadge'],['workTitle','workTitle'],['mBadge','mBadge'],['tBadge','tBadge'],['typingText','typingText'],['catalogTab','catalogTab'],['adminTab','adminTab'],['catalogTitle','catalogTitle'],['catalogCopy','catalogCopy'],['plansTab','plansTab'],['docsTab','docsTab'],['uploadTitle','uploadTitle'],['uploadBtn','uploadBtn']].forEach(([id,k]) => {
        const el = $(id);
        if (el) el.textContent = T(k);
    });
    if ($('loginTab')) $('loginTab').textContent = T('login');
    if ($('registerTab')) $('registerTab').textContent = T('register');
    if ($('loginBtn')) $('loginBtn').textContent = T('login');
    if ($('registerBtn')) $('registerBtn').textContent = T('create');
    if ($('logoutBtn')) $('logoutBtn').textContent = T('logout');
    if ($('newChatBtn')) $('newChatBtn').textContent = T('newChat');
    if ($('messageInput')) $('messageInput').placeholder = T('placeholder');
    if ($('composerHint')) $('composerHint').textContent = T('composerHint');
    if ($('sendBtn')) $('sendBtn').textContent = T('send');
}

function promptButtons() {
    const list = [T('p1'), T('p2'), T('p3'), T('p4')];
    $('prompts').innerHTML = list.map(x => `<button class="prompt" type="button">${esc(x)}</button>`).join('');
    $('prompts').querySelectorAll('button').forEach(button => {
        button.onclick = () => {
            $('messageInput').value = button.textContent;
            $('messageInput').focus();
        };
    });
}

function renderChats() {
    if (!S.chats.length) {
        $('chatList').innerHTML = `<div class="box muted">${esc(T('noChats'))}</div>`;
        return;
    }

    $('chatList').innerHTML = S.chats.map(chat => `
        <button class="chatitem ${chat.id === S.chatId ? 'active' : ''}" data-id="${chat.id}" type="button">
            <strong>${esc(chat.title || T('newChat'))}</strong>
            <div class="muted">${esc((chat.last_message || '').slice(0, 90) || T('empty'))}</div>
        </button>
    `).join('');
    $('chatList').querySelectorAll('button').forEach(button => {
        button.onclick = () => {
            closeSidebar();
            loadChat(Number(button.dataset.id));
        };
    });
}

function renderMessages() {
    if (!S.messages.length) {
        $('messages').innerHTML = `<div class="box muted">${esc(T('empty'))}</div>`;
        return;
    }

    $('messages').innerHTML = S.messages.map(message => {
        const label = message.sender === 'user' ? 'You' : 'Petsfolio AI';
        const body = message.sender === 'assistant' && message.streaming && !message.message
            ? '<span class="dots"><span></span><span></span><span></span></span>'
            : esc(message.message);

        return `
            <article class="msg ${message.sender === 'user' ? 'user' : 'assistant'}" data-request-id="${esc(message.request_id || '')}">
                <div class="meta">
                    <strong>${label}</strong>
                    <span>${esc(message.created_at || '')}</span>
                </div>
                <div class="body">${body}</div>
            </article>
        `;
    }).join('');
    $('messages').scrollTop = $('messages').scrollHeight;
}

function renderPlans() {
    $('planList').innerHTML = S.plans.map(p => `
        <div class="box">
            <strong>${esc(S.locale === 'hi' ? p.name_hi : p.name_en)}</strong>
            <div class="muted" style="margin:8px 0 10px">${esc(S.locale === 'hi' ? p.summary_hi : p.summary_en)}</div>
            <div>${T('price')}: $${esc(Number(p.price_monthly).toFixed(2))}</div>
            <div>${T('limit')}: $${esc(p.annual_limit)}</div>
            <div>${T('reimb')}: ${esc(p.reimbursement_percent)}%</div>
            <div>${T('ded')}: $${esc(p.deductible)}</div>
            <div>${T('wait')}: ${esc(p.waiting_period_days)} days</div>
        </div>
    `).join('');
}

function planFields(p = {}) {
    return `<div class="grid2"><select class="select" name="pet_type"><option value="dog" ${p.pet_type==='dog'?'selected':''}>Dog</option><option value="cat" ${p.pet_type==='cat'?'selected':''}>Cat</option></select><input class="field" name="slug" placeholder="slug" value="${esc(p.slug||'')}"></div><div class="grid2"><input class="field" name="name_en" placeholder="Name (EN)" value="${esc(p.name_en||'')}"><input class="field" name="name_hi" placeholder="Name (HI)" value="${esc(p.name_hi||'')}"></div><textarea class="area" name="summary_en" placeholder="Summary EN">${esc(p.summary_en||'')}</textarea><textarea class="area" name="summary_hi" placeholder="Summary HI">${esc(p.summary_hi||'')}</textarea><div class="grid2"><input class="field" name="price_monthly" type="number" step="0.01" placeholder="Price" value="${esc(p.price_monthly||'')}"><input class="field" name="annual_limit" type="number" placeholder="Annual limit" value="${esc(p.annual_limit||'')}"></div><div class="grid2"><input class="field" name="deductible" type="number" placeholder="Deductible" value="${esc(p.deductible||'')}"><input class="field" name="reimbursement_percent" type="number" placeholder="Reimbursement %" value="${esc(p.reimbursement_percent||'')}"></div><div class="grid2"><input class="field" name="waiting_period_days" type="number" placeholder="Waiting days" value="${esc(p.waiting_period_days||'')}"><select class="select" name="is_active"><option value="1" ${(p.is_active??1)==1?'selected':''}>Active</option><option value="0" ${(p.is_active??1)==0?'selected':''}>Inactive</option></select></div><textarea class="area" name="claim_steps_en" placeholder="Claim steps EN">${esc(p.claim_steps_en||'')}</textarea><textarea class="area" name="claim_steps_hi" placeholder="Claim steps HI">${esc(p.claim_steps_hi||'')}</textarea><textarea class="area" name="exclusions_en" placeholder="Exclusions EN">${esc(p.exclusions_en||'')}</textarea><textarea class="area" name="exclusions_hi" placeholder="Exclusions HI">${esc(p.exclusions_hi||'')}</textarea>`;
}

function renderAdmin() {
    if (!S.user || S.user.role !== 'admin') return;
    $('adminPlans').innerHTML = S.adminPlans.map(plan => `
        <form class="box stack editPlan" data-id="${plan.id}">
            <strong>${esc(plan.name_en)}</strong>
            ${planFields(plan)}
            <button class="btn primary" type="submit">${esc(T('save'))}</button>
        </form>
    `).join('');
    $('newPlanForm').innerHTML = `<strong>${esc(T('newPlan'))}</strong>${planFields({pet_type:'dog',is_active:1})}<button class="btn primary" type="submit">${esc(T('newPlan'))}</button>`;
    $('adminPlans').querySelectorAll('.editPlan').forEach(form => {
        form.onsubmit = async event => {
            event.preventDefault();
            await api(`admin/plans/${form.dataset.id}`, {method:'PUT', body:Object.fromEntries(new FormData(form).entries())});
            toast(T('saved'));
            await loadAdmin();
        };
    });
}

function renderDocs() {
    $('docList').innerHTML = S.docs.map(doc => `
        <div class="box">
            <strong>${esc(doc.title)}</strong>
            <div class="muted">${esc(doc.language || 'en')}</div>
            <div class="muted">${esc(doc.created_at || '')}</div>
        </div>
    `).join('');
}

async function loadPlans() {
    S.plans = await api('plans');
    renderPlans();
}

async function loadAdmin() {
    if (!S.user || S.user.role !== 'admin') return;
    S.adminPlans = await api('admin/plans');
    S.docs = await api('admin/documents');
    renderAdmin();
    renderDocs();
}

async function loadChats() {
    S.chats = await api('chats');
    renderChats();
    if (!S.chatId && S.chats.length) {
        S.chatId = S.chats[0].id;
        await loadChat(S.chatId);
    }
}

async function loadChat(id) {
    cancelActiveStream();
    const data = await api(`chats/${id}`);
    S.chatId = data.chat.id;
    S.messages = data.messages;
    renderChats();
    renderMessages();
}

async function auth(path, form) {
    const body = Object.fromEntries(new FormData(form).entries());
    body.preferred_locale = S.locale;
    const data = await api(path, {method:'POST', body});
    S.token = data.token;
    S.user = data.user;
    localStorage.setItem('petsfolio_token', S.token);
    localStorage.setItem('petsfolio_locale', S.locale);
    toast(path === 'auth/login' ? T('logged') : T('registered'));
    await bootIn();
}

async function bootIn() {
    closeSidebar();
    $('authScreen').classList.add('hidden');
    $('shell').classList.remove('hidden');
    $('userName').textContent = 'Petsfolio';
    $('userRole').textContent = T('composerHint');
    $('adminTab').classList.add('hidden');
    $('adminPanel').classList.add('hidden');
    $('catalogPanel').classList.add('hidden');
    document.querySelectorAll('[data-panel]').forEach(node => node.classList.toggle('active', false));
    setCopy();
    promptButtons();
    renderMessages();
    await loadChats();
}

async function send(e) {
    e.preventDefault();
    const msg = $('messageInput').value.trim();
    if (!msg || $('sendBtn').disabled) return;

    $('sendBtn').disabled = true;
    $('messageInput').value = '';
    $('typing').classList.remove('hidden');

    S.messages = [...S.messages, {sender:'user', message:msg, created_at:new Date().toISOString(), sources:[]}];
    renderMessages();

    const requestId = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 8)}`;

    try {
        await streamChat(msg, requestId);
    } catch (err) {
        if (err && err.name === 'AbortError') {
            S.messages = S.messages.filter(message => message.request_id !== requestId);
            renderMessages();
            return;
        }

        S.messages = S.messages.filter(message => message.request_id !== requestId);
        renderMessages();

        try {
            const data = await api('chat', {method:'POST', body:{chat_id:S.chatId, message:msg, locale:S.locale}});
            S.chatId = data.chat.id;
            S.messages = [...S.messages, {
                sender: 'assistant',
                message: data.reply,
                created_at: new Date().toISOString(),
                sources: data.sources || []
            }];
            syncStreamedChat(data.chat || null);
            renderMessages();
        } catch (fallbackErr) {
            toast(fallbackErr.message || err.message);
        }
    } finally {
        if (!STREAM.controller) {
            $('typing').classList.add('hidden');
            $('sendBtn').disabled = false;
        }
    }
}
console.log('PETSFOLIO_LOG_V5');
function switchTab(login){$('loginForm').classList.toggle('hidden',!login);$('registerForm').classList.toggle('hidden',login);$('loginTab').classList.toggle('active',login);$('registerTab').classList.toggle('active',!login)}
async function boot(){setCopy();promptButtons();localStorage.setItem('petsfolio_locale',S.locale);await bootIn()}
$('loginTab').onclick=()=>switchTab(true);
$('registerTab').onclick=()=>switchTab(false);
$('authLocale').onchange=e=>{S.locale=e.target.value;localStorage.setItem('petsfolio_locale',S.locale);setCopy();promptButtons()};
$('appLocale').onchange=async e=>{S.locale=e.target.value;localStorage.setItem('petsfolio_locale',S.locale);setCopy();promptButtons();renderPlans();renderAdmin();renderDocs();renderMessages()};
$('logoutBtn').onclick=()=>{cancelActiveStream();location.reload();};
$('sidebarToggle').onclick=()=>openSidebar();
$('sidebarClose').onclick=()=>closeSidebar();
$('sidebarBackdrop').onclick=()=>closeSidebar();
window.addEventListener('resize',()=>{if(!isMobile())closeSidebar()});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeSidebar()});
$('newChatBtn').onclick=async()=>{try{cancelActiveStream();closeSidebar();const d=await api('chats',{method:'POST',body:{title:T('newChat')}});S.chatId=d.id;S.messages=[];renderMessages();await loadChats();toast(T('created'))}catch(err){toast(err.message)}};
$('composer').onsubmit=send;
$('loginForm').onsubmit=async e=>{e.preventDefault();try{await auth('auth/login',$('loginForm'))}catch(err){toast(err.message)}};
$('registerForm').onsubmit=async e=>{e.preventDefault();try{await auth('auth/register',$('registerForm'))}catch(err){toast(err.message)}};
$('newPlanForm').onsubmit=async e=>{e.preventDefault();try{await api('admin/plans',{method:'POST',body:Object.fromEntries(new FormData($('newPlanForm')).entries())});toast(T('saved'));$('newPlanForm').reset();await loadAdmin();await loadPlans()}catch(err){toast(err.message)}};
$('docForm').onsubmit=async e=>{e.preventDefault();try{await api('admin/documents/upload',{method:'POST',body:new FormData($('docForm'))});toast(T('uploaded'));$('docForm').reset();await loadAdmin()}catch(err){toast(err.message)}};
document.querySelectorAll('[data-panel]').forEach(b=>b.onclick=()=>{document.querySelectorAll('[data-panel]').forEach(x=>x.classList.toggle('active',x===b));const admin=b.dataset.panel==='admin';$('catalogPanel').classList.toggle('hidden',admin);$('adminPanel').classList.toggle('hidden',!admin||S.user?.role!=='admin')});
document.querySelectorAll('[data-admin]').forEach(b=>b.onclick=()=>{document.querySelectorAll('[data-admin]').forEach(x=>x.classList.toggle('active',x===b));const docs=b.dataset.admin==='docs';$('adminPlans').classList.toggle('hidden',docs);$('newPlanForm').classList.toggle('hidden',docs);$('docsPanel').classList.toggle('hidden',!docs)});

// New Integrated Upload Logic
$('uploadDocBtn').onclick = () => $('fileInput').click();
$('fileInput').onchange = async (e) => {
    const file = e.target.files?.[0];
    if(!file) return;

    const question = $('messageInput').value.trim() || 'Analyze this document';
    toast(`Uploading ${file.name}...`);
    $('sendBtn').disabled = true;
    $('typing').classList.remove('hidden');

    const fd = new FormData();
    fd.append('file', file);
    fd.append('question', question);

    try {
        const d = await api('document/upload', {method:'POST', body:fd});
        if(d.analysis) {
            S.messages = [...S.messages, {sender:'assistant', message: d.analysis, created_at: new Date().toISOString()}];
            renderMessages();
        } else {
            toast(T('uploaded'));
        }
        $('messageInput').value = '';
    } catch(err) {
        toast(err.message);
    } finally {
        $('fileInput').value = '';
        $('typing').classList.add('hidden');
        $('sendBtn').disabled = false;
    }
};

boot();
</script>
</body>
</html>
