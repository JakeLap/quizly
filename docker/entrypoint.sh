#!/bin/sh
# Starts Quizly (see the Dockerfile).
#
#   all        everything in this one container, under supervisord (the default)
#   web        only the site, on Octane, port 8000
#   reverb     only the websocket server, port 8080
#   clock      only the game clock
#   scheduler  only the scheduler
#
# Any other command runs as is, e.g. `php artisan tinker`.
set -e

role="${1:-all}"

case "$role" in
    all | web | reverb | clock | scheduler) ;;
    *) exec "$@" ;;
esac

# Inside `all`, supervisord starts each part after the setup below has run once.
if [ "$2" != "--no-setup" ]; then
    missing=""
    for name in APP_KEY APP_URL DB_URL REDIS_URL REVERB_APP_KEY REVERB_APP_SECRET; do
        eval "value=\${$name:-}"
        [ -z "$value" ] && missing="$missing $name"
    done

    if [ -n "$missing" ]; then
        echo "Missing settings:$missing (see deploy/COOLIFY.md)" >&2
        exit 1
    fi

    # Settings come from the environment, so cache them when the container starts.
    php artisan optimize --no-interaction

    # The site and the clock need the database as the migrations leave it.
    if [ "$role" = "all" ] || [ "$role" = "web" ]; then
        if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
            php artisan migrate --force --no-interaction
        fi

        if [ "${SEED_CONTENT:-true}" = "true" ]; then
            php artisan quizly:seed-content --no-interaction
        fi
    fi
fi

case "$role" in
    all)
        exec supervisord -c /var/www/html/docker/supervisord.conf
        ;;
    web)
        exec php artisan octane:start \
            --server=swoole \
            --host=0.0.0.0 \
            --port=8000 \
            --workers="${OCTANE_WORKERS:-auto}" \
            --task-workers="${OCTANE_TASK_WORKERS:-auto}" \
            --max-requests="${OCTANE_MAX_REQUESTS:-1000}"
        ;;
    reverb)
        exec php artisan reverb:start --host=0.0.0.0 --port=8080
        ;;
    clock)
        exec php artisan quizly:clock
        ;;
    scheduler)
        exec php artisan schedule:work
        ;;
esac
