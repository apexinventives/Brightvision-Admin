<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher's Dashboard - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --admin-bg: #f6f7f9;
            --admin-surface: #ffffff;
            --admin-border: #e5e7eb;
            --admin-text: #111827;
            --admin-muted: #6b7280;
            --admin-accent: #2563eb;
            --admin-sidebar: #111827;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            background: var(--admin-bg) !important;
            color: var(--admin-text);
            overflow-x: hidden;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .sidebar-transition {
            transition: all 0.3s ease;
        }
        .table-hover:hover {
            background-color: #f9fafb;
        }
        .admin-content .bg-white {
            border-color: var(--admin-border);
        }
        .admin-content .shadow-md,
        .admin-content .shadow-lg,
        .admin-content .shadow-xl {
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05), 0 6px 18px rgba(15, 23, 42, 0.04) !important;
        }
        .admin-content [class*="bg-gradient-to-r"] {
            background-image: none !important;
            background-color: #1f2937 !important;
        }
        .admin-content input,
        .admin-content select,
        .admin-content textarea {
            border-color: #d1d5db;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .admin-content input:focus,
        .admin-content select:focus,
        .admin-content textarea:focus {
            border-color: var(--admin-accent) !important;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .10) !important;
            outline: none;
        }
        .admin-content table { border-collapse: collapse; }
        .admin-content thead { background-color: #f9fafb !important; }
        .admin-content tbody tr { transition: background-color .15s ease; }
        .admin-content button,
        .admin-content a { -webkit-tap-highlight-color: transparent; }
        #settingsTabs { scrollbar-width: thin; }
        #adminSidebar nav a:hover,
        #adminSidebar nav a.bg-blue-700 { background-color: #1f2937 !important; }
        #adminSidebar nav a.bg-blue-700 { border-left-color: #3b82f6 !important; }
        #adminSidebar .border-blue-700 { border-color: #374151 !important; }
        .admin-content .overflow-x-auto {
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }

        @media (max-width: 1023px) {
            body.sidebar-open { overflow: hidden; }
            .admin-content .p-8 { padding: 1rem; }
            .admin-content .p-6 { padding: 1rem; }
            .admin-content h1.text-3xl { font-size: 1.6rem; line-height: 2rem; }
            .admin-content h2.text-2xl { font-size: 1.35rem; line-height: 1.75rem; }
            .admin-content .flex.justify-between { gap: .75rem; flex-wrap: wrap; }
            .admin-content .grid.grid-cols-2 { grid-template-columns: minmax(0, 1fr); }
            .admin-content input,
            .admin-content select,
            .admin-content textarea { max-width: 100%; }
            .admin-content .fixed.inset-0 { padding: .75rem; overflow-y: auto; }
            .admin-content .fixed.inset-0 > .bg-white { margin: auto; max-height: calc(100vh - 1.5rem); overflow-y: auto; }
            #settingsTabs { flex-wrap: nowrap; overflow-x: auto; justify-content: flex-start; }
            #settingsTabs li { flex: 0 0 auto; }
        }
        @media (max-width: 639px) {
            .admin-content .px-6 { padding-left: 1rem; padding-right: 1rem; }
            .admin-content .gap-6 { gap: 1rem; }
            .admin-content table th,
            .admin-content table td { white-space: nowrap; }
        }
    </style>
</head>
<body class="bg-gray-50">
