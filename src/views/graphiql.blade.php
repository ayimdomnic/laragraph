<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title ?? 'Laragraph — GraphiQL' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; height: 100vh; display: flex; flex-direction: column; }
        #header {
            background: #1a1a2e; color: #e0e0e0;
            padding: 8px 16px;
            display: flex; align-items: center; gap: 12px;
            font-size: 14px; flex-shrink: 0;
        }
        #header strong { color: #a78bfa; font-size: 16px; letter-spacing: .5px; }
        #header span { color: #9ca3af; font-size: 12px; }
        #graphiql { flex: 1; overflow: hidden; }
    </style>
    <link
        rel="stylesheet"
        href="https://unpkg.com/graphiql@3/graphiql.min.css"
        crossorigin="anonymous"
    />
</head>
<body>
    <div id="header">
        <strong>Laragraph</strong>
        <span>GraphiQL — {{ $endpoint }}</span>
    </div>
    <div id="graphiql"></div>

    <script
        src="https://unpkg.com/react@18/umd/react.production.min.js"
        crossorigin="anonymous"
    ></script>
    <script
        src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"
        crossorigin="anonymous"
    ></script>
    <script
        src="https://unpkg.com/graphiql@3/graphiql.min.js"
        crossorigin="anonymous"
    ></script>

    <script>
        const endpoint = @json($endpoint);

        const fetcher = GraphiQL.createFetcher({
            url: endpoint,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const root = ReactDOM.createRoot(document.getElementById('graphiql'));
        root.render(
            React.createElement(GraphiQL, {
                fetcher,
                defaultEditorToolsVisibility: true,
                isHeadersEditorEnabled: true,
            })
        );
    </script>
</body>
</html>
