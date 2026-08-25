<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>No internet · {{ setting('business_name') }}</title>
    <style>
        /* No build assets here on purpose - this page has to work when nothing
           else loaded. */
        body {
            margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center;
            background: #F6F7F9; color: #16181D; padding: 24px;
            font-family: "DM Sans", system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        .card {
            background: #FFFFFF; border: 1px solid #E8EAED; border-radius: 14px;
            padding: 24px; max-width: 400px; width: 100%; text-align: center;
        }
        .mark {
            width: 56px; height: 56px; border-radius: 10px; background: #E9EDF9;
            display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;
        }
        h1 { font-size: 24px; font-weight: 700; margin: 0 0 8px; }
        p { font-size: 16px; line-height: 24px; color: #6B7280; margin: 0 0 20px; }
        button {
            min-height: 52px; width: 100%; border: 0; border-radius: 10px;
            background: #1E3A8A; color: #FFFFFF; font-size: 16px; font-weight: 500;
            font-family: inherit; cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="card">
        <span class="mark">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#1E3A8A"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 2l20 20"/>
                <path d="M8.5 16.4a5 5 0 0 1 7 0"/>
                <path d="M5 12.9a10 10 0 0 1 5.2-2.7"/>
                <path d="M19 12.9a10 10 0 0 0-4-2.5"/>
                <path d="M2 8.8a15 15 0 0 1 4.2-2.5"/>
                <path d="M22 8.8a15 15 0 0 0-9.8-3.7"/>
                <path d="M12 20h.01"/>
            </svg>
        </span>

        <h1>No internet</h1>
        <p>
            You are not connected right now. Anything you saved is still on this phone and will
            send by itself when the signal is back.
        </p>

        <button type="button" onclick="window.location.reload()">Try again</button>
    </div>
</body>
</html>
