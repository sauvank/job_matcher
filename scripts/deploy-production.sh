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

wait_for_application() {
    local max_attempts=${1:-30}
    local attempt

    for ((attempt = 1; attempt <= max_attempts; attempt++)); do
        if curl --fail --silent --connect-timeout 2 --max-time 5 "$healthcheck_url" >/dev/null 2>&1; then
            return 0
        fi

        if ((attempt < max_attempts)); then
            sleep 3
        fi
    done

    echo "The local application health check failed after $max_attempts attempts." >&2
    curl --fail --silent --show-error --connect-timeout 2 --max-time 5 \
        "$healthcheck_url" >/dev/null
}

print_security_service_diagnostics() {
    local service
    local container_id

    echo "Security service diagnostics before rollback:" >&2
    "${compose[@]}" ps -a clamav extractor >&2 || true
    echo "Host memory:" >&2
    free -m >&2 || true

    for service in clamav extractor; do
        container_id=$("${compose[@]}" ps -a -q "$service" 2>/dev/null || true)
        if [[ -z $container_id ]]; then
            continue
        fi

        echo "State for $service:" >&2
        docker inspect \
            --format 'status={{.State.Status}} exit={{.State.ExitCode}} oom={{.State.OOMKilled}} restarts={{.RestartCount}}{{if .State.Health}} health={{.State.Health.Status}}{{end}} error={{.State.Error}}' \
            "$container_id" >&2 || true
        echo "Last logs for $service:" >&2
        "${compose[@]}" logs --no-color --tail=150 "$service" >&2 || true
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
        "${rollback_compose[@]}" pull php worker nginx
        "${rollback_compose[@]}" up -d --force-recreate --no-deps --remove-orphans php worker
        "${rollback_compose[@]}" up -d --force-recreate --no-deps nginx
        wait_for_application 20
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

"${compose[@]}" config --quiet
"${compose[@]}" pull php worker nginx clamav extractor

# Start background security services
"${compose[@]}" up -d clamav extractor
"${compose[@]}" run --rm php \
    php bin/console doctrine:migrations:migrate --no-interaction
"${compose[@]}" up -d --wait --wait-timeout 60 extractor
"${compose[@]}" up -d --force-recreate --no-deps php worker
"${compose[@]}" up -d --force-recreate --no-deps nginx

echo "Waiting for the local application health check."
wait_for_application 30

"${compose[@]}" ps
if [[ $release_compose_file != "$project_dir/compose.prod.yaml" ]]; then
    install -m 644 "$release_compose_file" "$project_dir/compose.prod.yaml"
fi
trap - ERR
echo "Production deployment completed for $deploy_sha."
