<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: #f7f7f5; color: #1b1b18; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        main { max-width: 32rem; padding: 2rem; }
        h1 { font-size: 1.25rem; margin-bottom: 0.5rem; }
        p { color: #6b6b64; }
    </style>
</head>
<body>
    <main>
        <h1>{{ config('app.name') }}</h1>
        <p>Screens are built starting Sprint 1a — see <code>docs/implementation-plan.md</code>. This placeholder carries no external asset references, on purpose (AD-16 offline deployment).</p>
    </main>
</body>
</html>
