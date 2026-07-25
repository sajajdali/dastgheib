# Project Run Notes

When the user asks to run this project, keep it isolated from the other Laravel project that may already be running on:

- Laravel backend: `0.0.0.0:8000`
- Laravel Reverb: `0.0.0.0:8080`

Always use these ports for this project unless the user explicitly asks otherwise:

- Backend Laravel server: `0.0.0.0:8001`
- Frontend Vite server: `0.0.0.0:5175`
- Reverb server: `0.0.0.0:8081`

Run commands from these directories:

- Backend: `/Applications/XAMPP/xamppfiles/htdocs/dastgheib/backend`
- Frontend: `/Applications/XAMPP/xamppfiles/htdocs/dastgheib/Frontend`

Backend command:

```sh
APP_URL=http://127.0.0.1:8001 FRONTEND_URL=http://127.0.0.1:5175 FRONTEND_URLS=http://127.0.0.1:5175,http://localhost:5175 SANCTUM_STATEFUL_DOMAINS=127.0.0.1:5175,localhost:5175 REVERB_HOST=127.0.0.1 REVERB_PORT=8081 REVERB_SERVER_HOST=0.0.0.0 REVERB_SERVER_PORT=8081 VITE_REVERB_HOST=127.0.0.1 VITE_REVERB_PORT=8081 /opt/homebrew/bin/php artisan serve --host=0.0.0.0 --port=8001
```

Reverb command:

```sh
APP_URL=http://127.0.0.1:8001 FRONTEND_URL=http://127.0.0.1:5175 FRONTEND_URLS=http://127.0.0.1:5175,http://localhost:5175 SANCTUM_STATEFUL_DOMAINS=127.0.0.1:5175,localhost:5175 REVERB_HOST=127.0.0.1 REVERB_PORT=8081 REVERB_SERVER_HOST=0.0.0.0 REVERB_SERVER_PORT=8081 /opt/homebrew/bin/php artisan reverb:start --host=0.0.0.0 --port=8081
```

Frontend command:

```sh
VITE_API_TARGET=http://127.0.0.1:8001 VITE_REVERB_APP_KEY=i4gbd4eco2euscrabetm VITE_REVERB_HOST=127.0.0.1 VITE_REVERB_PORT=8081 VITE_REVERB_SCHEME=http npm run dev -- --host 0.0.0.0 --port 5175
```

Open the project at:

```text
http://localhost:5175/
```

Before running, check whether the chosen ports are already in use:

```sh
lsof -nP -iTCP:8001 -iTCP:5175 -iTCP:8081 -sTCP:LISTEN
```

The frontend Vite proxy supports `VITE_API_TARGET`; use it so `/api`, `/sanctum`, `/login`, `/logout`, and `/broadcasting/auth` point to this project's backend on port `8001` instead of the other Laravel project on port `8000`.
