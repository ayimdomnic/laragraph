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
        #header .spacer { flex: 1; }
        #env-badge {
            font-size: 11px; font-weight: 600; letter-spacing: .5px;
            padding: 2px 8px; border-radius: 10px; text-transform: uppercase;
            background: #2e2e48; color: #a78bfa;
        }
        #env-badge.prod { background: #7f1d1d; color: #fecaca; }
        #prod-banner {
            background: #b91c1c; color: #fef2f2; text-align: center;
            padding: 6px 16px; font-size: 13px; font-weight: 600; flex-shrink: 0;
        }
        #graphiql { flex: 1; overflow: hidden; }
    </style>
    <link
        rel="stylesheet"
        href="https://unpkg.com/graphiql@3/graphiql.min.css"
        crossorigin="anonymous"
    />
</head>
<body>
    @if ($prodWarning)
        <div id="prod-banner">
            ⚠ Production environment — GraphiQL exposes your schema. Set
            <code>laragraph.graphiql.enabled =&nbsp;false</code> (or leave it <code>null</code> with
            <code>APP_DEBUG=false</code>) to close it.
        </div>
    @endif
    <div id="header">
        <strong>Laragraph</strong>
        <span>GraphiQL — {{ $endpoint }}</span>
        <div class="spacer"></div>
        <span id="env-badge" class="{{ $prodWarning ? 'prod' : '' }}">{{ $environment }}</span>
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
        const config = {
            endpoint: @json($endpoint),
            environment: @json($environment),
            authPicker: @json($authPicker),
            tokensUrl: @json($tokensUrl),
            // Server-rendered label of the logged-in user (session guard),
            // so the picker can show *who* "current session" means without
            // a `me { id }` round-trip.
            sessionUser: @json($sessionUser),
        };

        const { createElement, useEffect, useMemo, useRef, useState } = React;

        // Same-origin fetches ride the framework's auth automatically: the
        // session cookie (and XSRF-TOKEN, mirrored into X-XSRF-TOKEN for
        // stateful cookie auth) is sent by the browser, so a developer who
        // is logged into the app needs no header paste at all.
        function baseHeaders() {
            const headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            };

            const csrf = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

            if (csrf) {
                headers['X-XSRF-TOKEN'] = decodeURIComponent(csrf[1]);
            }

            return headers;
        }

        // The bearer token a picked role carries, or nothing for "current
        // session" (which rides the cookie) — merged over the base headers.
        function authHeaderFor(mode, tokens) {
            if (!mode || mode === '__session__') {
                return null;
            }

            const entry = (tokens ?? []).find((t) => t.role === mode);

            return entry?.token ? 'Bearer ' + entry.token : null;
        }

        // Parse the header editor's text into a plain { string: string } map,
        // or null when it isn't valid JSON yet (mid-edit).
        function parseHeaders(text) {
            try {
                const parsed = JSON.parse(text || '{}');

                return Object.fromEntries(
                    Object.entries(parsed).filter(([, value]) => typeof value === 'string'),
                );
            } catch {
                return null;
            }
        }

        function sessionLabel() {
            return config.sessionUser
                ? 'Current session — ' + config.sessionUser
                : 'Current session (cookie)';
        }

        function readStoredMode() {
            try {
                return localStorage.getItem('laragraph.graphiql.authMode') || '__session__';
            } catch {
                return '__session__';
            }
        }

        function storeMode(mode) {
            try {
                localStorage.setItem('laragraph.graphiql.authMode', mode);
            } catch {
                // private mode / storage disabled — the selection just won't persist
            }
        }

        function ideText(headers) {
            return JSON.stringify(headers, null, 2) + '\n';
        }

        function Ide() {
            const [mode, setMode] = useState(readStoredMode);
            const [tokens, setTokens] = useState([]);
            const [headerText, setHeaderText] = useState(() => ideText(baseHeaders()));

            // The editor text is the single source of truth for what is sent.
            // While it's valid JSON, headers flow straight from it; a half-typed
            // edit keeps sending the last valid set rather than breaking requests.
            const lastValid = useRef(parseHeaders(headerText) ?? baseHeaders());

            const headers = useMemo(() => {
                const parsed = parseHeaders(headerText);

                if (parsed) {
                    lastValid.current = parsed;
                }

                return lastValid.current;
            }, [headerText]);

            // Rebuild the fetcher whenever the effective headers change:
            // switching identity re-points every request without remounting the
            // IDE, so the open query, variables and history all survive it.
            const fetcher = useMemo(
                () => GraphiQL.createFetcher({ url: config.endpoint, headers }),
                [headers],
            );

            useEffect(() => {
                if (!config.authPicker || !config.tokensUrl) {
                    return;
                }

                let cancelled = false;

                fetch(config.tokensUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then((response) => (response.ok ? response.json() : { tokens: [], note: 'dev-tokens answered ' + response.status }))
                    .then((body) => {
                        if (cancelled) {
                            return;
                        }

                        const entries = body.tokens ?? [];

                        if (body.note) {
                            console.warn('[laragraph] graphiql auth picker:', body.note);
                        }

                        setTokens(entries);

                        // The stored mode may name a role whose token has only
                        // now arrived — apply it to the current header set.
                        const auth = authHeaderFor(readStoredMode(), entries);

                        if (auth) {
                            setHeaderText((text) => ideText({ ...(parseHeaders(text) ?? {}), Authorization: auth }));
                        }
                    })
                    .catch(() => { if (!cancelled) setTokens([]); });

                return () => { cancelled = true; };
            }, []);

            // Switching identity rewrites only the Authorization line, leaving
            // any other header the developer added by hand intact.
            function switchMode(nextMode) {
                storeMode(nextMode);
                setMode(nextMode);

                setHeaderText((text) => {
                    const merged = { ...(parseHeaders(text) ?? {}) };
                    const auth = authHeaderFor(nextMode, tokens);

                    if (auth) {
                        merged.Authorization = auth;
                    } else {
                        delete merged.Authorization;
                    }

                    return ideText(merged);
                });
            }

            const options = [['__session__', sessionLabel()]];

            for (const entry of tokens) {
                options.push([
                    entry.role,
                    entry.token
                        ? entry.role + ' — ' + entry.identifier
                        : entry.role + ' — ' + entry.identifier + ' (no token: needs Sanctum)',
                ]);
            }

            const known = options.some(([value]) => value === mode);

            return createElement(
                'div',
                { style: { display: 'flex', flexDirection: 'column', height: '100vh' } },
                config.authPicker
                    ? createElement(
                        'div',
                        { style: { display: 'flex', gap: '8px', padding: '6px 16px', background: '#16162a', fontSize: '12px', alignItems: 'center' } },
                        createElement('label', { htmlFor: 'laragraph-as', style: { color: '#9ca3af' } }, 'Act as'),
                        createElement(
                            'select',
                            {
                                id: 'laragraph-as',
                                value: known ? mode : '__session__',
                                onChange: (event) => switchMode(event.target.value),
                                style: { background: '#2e2e48', color: '#e0e0e0', border: '1px solid #3f3f61', borderRadius: '6px', padding: '3px 8px', fontSize: '12px' },
                            },
                            ...(config.tokensUrl && tokens.length === 0
                                ? [createElement('option', { key: 'loading', value: '__loading__' }, 'Loading tokens…')]
                                : options.map(([value, label]) => createElement('option', { key: value, value }, label))),
                        ),
                    )
                    : null,
                createElement(
                    'div',
                    { style: { flex: 1, minHeight: 0 } },
                    createElement(GraphiQL, {
                        fetcher,
                        defaultEditorToolsVisibility: true,
                        isHeadersEditorEnabled: true,
                        headers: headerText,
                        onEditHeaderChange: setHeaderText,
                    }),
                ),
            );
        }

        ReactDOM.createRoot(document.getElementById('graphiql')).render(createElement(Ide));
    </script>
</body>
</html>
