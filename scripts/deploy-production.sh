#!/usr/bin/env bash

set -Eeuo pipefail

if [[ $# -lt 1 || $# -gt 2 || ! $1 =~ ^[0-9a-f]{40}$ ]]; then
    echo "Usage: $0 <full-git-sha> [compose-file]" >&2
    exit 64
fi

deploy_sha=$1
if [[ -n ${DEPLOY_PROJECT_DIR:-} ]]; then
    project_dir=$DEPLOY_PROJECT_DIR
elif [[ ${BASH_SOURCE[0]} == /dev/stdin ]]; then
    project_dir=$PWD
else
    project_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
fi

cd "$project_dir"

if [[ ! -f .env.prod.local ]]; then
    echo "Missing $project_dir/.env.prod.local" >&2
    exit 66
fi
if [[ ! -f $project_dir/compose.prod.yaml ]]; then
    echo "Missing current production manifest $project_dir/compose.prod.yaml" >&2
    exit 66
fi

release_compose_file=${2:-$project_dir/compose.prod.yaml}
if [[ ! -f $release_compose_file ]]; then
    echo "Missing deployment manifest $release_compose_file" >&2
    exit 66
fi
release_dir=$(cd "$(dirname "$release_compose_file")" && pwd)
for browser_file in Dockerfile package.json server.mjs; do
    if [[ ! -f $release_dir/docker/browser/$browser_file ]]; then
        echo "Missing browser deployment file $release_dir/docker/browser/$browser_file" >&2
        exit 66
    fi
done

mkdir -p .deploy/backups
chmod 700 .deploy .deploy/backups

exec 9>.deploy/deploy.lock
if ! flock -n 9; then
    echo "Another production deployment is already running." >&2
    exit 75
fi

compose=(docker compose --project-directory "$project_dir" --env-file .env.prod.local -f "$release_compose_file")
rollback_compose=(docker compose --project-directory "$project_dir" --env-file .env.prod.local -f "$project_dir/compose.prod.yaml")
previous_php_image=$(sed -n 's/^PHP_IMAGE=//p' .env.prod.local | tail -n 1)
previous_nginx_image=$(sed -n 's/^NGINX_IMAGE=//p' .env.prod.local | tail -n 1)
previous_browser_image=$(sed -n 's/^BROWSER_IMAGE=//p' .env.prod.local | tail -n 1)
browser_image_was_configured=1
if [[ -z $previous_browser_image ]]; then
    previous_browser_image=job-matcher-browser:prod
    browser_image_was_configured=0
fi
app_http_port=$(sed -n 's/^APP_HTTP_PORT=//p' .env.prod.local | tail -n 1)
deployment_started=0

if [[ -z $previous_php_image || -z $previous_nginx_image ]]; then
    echo "PHP_IMAGE and NGINX_IMAGE must be set in .env.prod.local." >&2
    exit 65
fi
if [[ ! $app_http_port =~ ^[0-9]{1,5}$ ]]; then
    echo "APP_HTTP_PORT must be a valid port in .env.prod.local." >&2
    exit 65
fi

healthcheck_url="http://127.0.0.1:$app_http_port/health"

set_env_image() {
    local key=$1
    local value=$2

    if grep -q "^${key}=" .env.prod.local; then
        sed -i "s|^${key}=.*$|${key}=${value}|" .env.prod.local
    else
        printf '\n%s=%s\n' "$key" "$value" >>.env.prod.local
    fi
}

wait_for_application() {
    local max_attempts=${1:-30}
    local attempt
    local http_code=""

    echo "Checking application health at $healthcheck_url (max $max_attempts attempts)..." >&2
    for ((attempt = 1; attempt <= max_attempts; attempt++)); do
        http_code=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 2 --max-time 5 "$healthcheck_url" 2>/dev/null || true)
        if [[ "$http_code" == "200" ]]; then
            echo "Health check succeeded on attempt $attempt (HTTP 200)." >&2
            return 0
        fi

        if ((attempt < max_attempts)); then
            sleep 3
        fi
    done

    echo "The local application health check failed after $max_attempts attempts (Last HTTP code: ${http_code:-none})." >&2
    echo "Raw response from $healthcheck_url:" >&2
    curl -v --connect-timeout 2 --max-time 5 "$healthcheck_url" >&2 || true
    return 1
}

print_security_service_diagnostics() {
    local service
    local container_id

    echo "=== Service states before rollback ===" >&2
    "${compose[@]}" ps -a >&2 || true
    echo "=== Host memory ===" >&2
    free -m >&2 || true

    for service in php nginx worker browser clamav extractor database redis; do
        container_id=$("${compose[@]}" ps -a -q "$service" 2>/dev/null || true)
        if [[ -z $container_id ]]; then
            continue
        fi

        echo "--- State for $service ---" >&2
        docker inspect \
            --format 'status={{.State.Status}} exit={{.State.ExitCode}} oom={{.State.OOMKilled}} restarts={{.RestartCount}}{{if .State.Health}} health={{.State.Health.Status}}{{end}} error={{.State.Error}}' \
            "$container_id" >&2 || true
        echo "--- Last logs for $service ---" >&2
        "${compose[@]}" logs --no-color --tail=50 "$service" >&2 || true
    done
}

restore_previous_release() {
    local failed_status=$?

    trap - ERR
    if [[ $deployment_started -eq 1 ]]; then
        echo "Deployment failed; restoring the previous application images." >&2
        set +e
        print_security_service_diagnostics
        sed -i "s|^PHP_IMAGE=.*$|PHP_IMAGE=$previous_php_image|" .env.prod.local
        sed -i "s|^NGINX_IMAGE=.*$|NGINX_IMAGE=$previous_nginx_image|" .env.prod.local
        if [[ $browser_image_was_configured -eq 1 ]]; then
            set_env_image BROWSER_IMAGE "$previous_browser_image"
        else
            sed -i '/^BROWSER_IMAGE=/d' .env.prod.local
        fi
        "${rollback_compose[@]}" pull php worker nginx
        "${rollback_compose[@]}" up -d --no-deps browser
        "${rollback_compose[@]}" up -d --force-recreate --no-deps --remove-orphans php worker
        "${rollback_compose[@]}" up -d --force-recreate --no-deps nginx
        wait_for_application 20 || true
        set -e
    fi

    if [[ -f ${backup_file:-} ]]; then
        echo "The database backup created before the migration is kept in .deploy/backups/." >&2
    fi
    exit "$failed_status"
}

trap restore_previous_release ERR

backup_file=".deploy/backups/database-$(date -u +%Y%m%dT%H%M%SZ)-${deploy_sha:0:12}.dump"
echo "Creating database backup $backup_file."
"${compose[@]}" exec -T database sh -c \
    'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom' >"$backup_file"
chmod 600 "$backup_file"

deployment_started=1
sed -i "s|^PHP_IMAGE=.*$|PHP_IMAGE=ghcr.io/sauvank/job-matcher-php:sha-$deploy_sha|" .env.prod.local
sed -i "s|^NGINX_IMAGE=.*$|NGINX_IMAGE=ghcr.io/sauvank/job-matcher-nginx:sha-$deploy_sha|" .env.prod.local
set_env_image BROWSER_IMAGE "job-matcher-browser:sha-$deploy_sha"

if [[ $release_dir != "$project_dir" ]]; then
    install -d -m 755 "$project_dir/docker/browser"
    install -m 644 "$release_dir/docker/browser/Dockerfile" "$project_dir/docker/browser/Dockerfile"
    install -m 644 "$release_dir/docker/browser/package.json" "$project_dir/docker/browser/package.json"
    install -m 644 "$release_dir/docker/browser/server.mjs" "$project_dir/docker/browser/server.mjs"
fi

"${compose[@]}" config --quiet
"${compose[@]}" pull php worker nginx clamav extractor
"${compose[@]}" build --pull browser

# Start background security services
"${compose[@]}" up -d clamav extractor browser
"${compose[@]}" run --rm php \
    php bin/console doctrine:migrations:migrate --no-interaction
"${compose[@]}" up -d --wait --wait-timeout 600 clamav extractor browser
"${compose[@]}" up -d --force-recreate --no-deps php worker
"${compose[@]}" up -d --force-recreate --no-deps nginx

echo "Waiting for the local application health check."
wait_for_application 30

"${compose[@]}" ps
if [[ $release_compose_file != "$project_dir/compose.prod.yaml" ]]; then
    install -m 644 "$release_compose_file" "$project_dir/compose.prod.yaml"
fi

echo "Cleaning up old production artifacts and unused Docker images."
if [[ -d "$project_dir/.deploy/backups" ]]; then
    find "$project_dir/.deploy/backups" -maxdepth 1 -type f -name 'database-*.dump' | sort -r | tail -n +11 | xargs -r rm -f
fi
if [[ -d "$project_dir/.deploy/releases" ]]; then
    find "$project_dir/.deploy/releases" -mindepth 1 -maxdepth 1 -type d | sort -r | tail -n +6 | xargs -r rm -rf
fi
docker image prune -a --filter "until=24h" -f >/dev/null 2>&1 || true
docker builder prune --filter "until=24h" -f >/dev/null 2>&1 || true

trap - ERR
echo "Production deployment completed for $deploy_sha."

