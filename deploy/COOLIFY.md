# Deploying Quizly on Coolify

Quizly deploys as one **Dockerfile** application, so Coolify's metrics work.
The container runs every part of the app under supervisord:

| Part | What it does | Port |
| --- | --- | --- |
| Octane (Swoole) | the site | 8000 |
| Reverb | live updates (websockets) | 8080 |
| Game clock | moves games along on time | n/a |
| Scheduler | expires rooms, prunes old games | n/a |

Postgres and Redis are separate Coolify databases.

On every start, the container runs the migrations. It also imports the
categories and questions, but only when they've changed since the last import.

## 1. DNS

Point two hostnames at the Coolify server, for example:

- `quizly.example.com`: the site
- `ws.quizly.example.com`: websockets

## 2. Databases

In the same project and environment (on the same server):

1. **New Resource → Database → PostgreSQL** (version 18). Start it, then copy
   its **Postgres URL (internal)**.
2. **New Resource → Database → Redis**. Start it, then copy its
   **Redis URL (internal)**.

Redis holds sessions and the cache. It also lets Reverb pass live updates
between the old and new container during a deploy.

## 3. The application

**New Resource → Application**, from this Git repository, then:

- **Build Pack:** Dockerfile (location `/Dockerfile`)
- **Ports Exposes:** `8000,8080`
- **Domains:** two entries, each with the container port it goes to:
  - `https://quizly.example.com:8000`
  - `https://ws.quizly.example.com:8080`

The port suffix only tells Coolify's proxy which container port to use.
Visitors still use plain HTTPS on 443.

**Healthcheck:** the image defines one: the site answers on `/up` and the
websocket port is open. The first start allows up to 3 minutes for the
migrations and the first question import.

## 4. Environment variables

Set these as normal (runtime) variables, not build variables. The front-end
reads its websocket address at run time, so changing a domain doesn't need a
rebuild.

| Variable | Value |
| --- | --- |
| `APP_KEY` | `base64:` followed by 32 random bytes, e.g. from `echo "base64:$(openssl rand -base64 32)"` |
| `APP_URL` | `https://quizly.example.com` |
| `DB_URL` | the Postgres URL (internal) from step 2 |
| `REDIS_URL` | the Redis URL (internal) from step 2 |
| `REVERB_APP_KEY` | random, e.g. `openssl rand -hex 16` |
| `REVERB_APP_SECRET` | random, e.g. `openssl rand -hex 32` |
| `REVERB_PUBLIC_HOST` | `ws.quizly.example.com` |
| `REVERB_ALLOWED_ORIGINS` | `quizly.example.com` |

If any of the first six is missing, the container stops and says which.

The rest have defaults in the Dockerfile and are optional:

| Variable | Default | |
| --- | --- | --- |
| `LOG_LEVEL` | `warning` | |
| `OCTANE_WORKERS` | `auto` | one per CPU |
| `RUN_MIGRATIONS` | `true` | |
| `SEED_CONTENT` | `true` | import changed questions on start |

Keep `APP_KEY` the same across deploys. It signs sessions and cookies, so
changing it signs everyone out of their rooms.

## 5. Deploy

The first build takes several minutes, mostly compiling Swoole. Later builds
reuse Docker's cache.

Server size: 1 GB of RAM is comfortable for the app container. The databases
come on top.

## Day to day

- **Logs:** every part logs to the container's output, which Coolify shows
  under Logs.
- **Artisan:** open the app's **Terminal** in Coolify and run e.g.
  `php artisan about`.
- **New questions:** commit the regenerated files in
  `database/data/questions/` and deploy. The next start imports them. To
  import again without changes, run `php artisan quizly:seed-content --force`.
- **During a deploy:** Coolify starts the new container and waits until it's
  healthy before stopping the old one. Games carry on: the clocks in the two
  containers share the work safely, and phones reconnect and catch up.

## Running the parts separately

To scale or restart one part on its own, deploy the same Dockerfile more than
once with a different start command: `web`, `reverb`, `clock` or `scheduler`
(see `docker/entrypoint.sh`). In that setup, set `RUN_MIGRATIONS=false` and
`SEED_CONTENT=false` on every app except `web`, and point `REVERB_HOST` at the
Reverb container.
