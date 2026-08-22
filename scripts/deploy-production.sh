#!/usr/bin/env bash

set -Eeuo pipefail

if [[ $# -ne 1 || ! $1 =~ ^[0-9a-f]{40}$ ]]; then
    echo "Usage: $0 <full-git-sha>" >&2
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

mkdir -p .deploy/backups
chmod 700 .deploy .deploy/backups

exec 9>.deploy/deploy.lock
if ! flock -n 9; then
    echo "Another production deployment is already running." >&2
    exit 75
fi

compose=(docker compose --env-file .env.prod.local -f compose.prod.yaml)
previous_revision=$(git rev-parse HEAD)
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

restore_previous_release() {
    local failed_status=$?

    trap - ERR
    if [[ $deployment_started -eq 1 ]]; then
        echo "Deployment failed; restoring the previous application images." >&2
        set +e
        git checkout --quiet --detach "$previous_revision"
        sed -i "s|^PHP_IMAGE=.*$|PHP_IMAGE=$previous_php_image|" .env.prod.local
        sed -i "s|^NGINX_IMAGE=.*$|NGINX_IMAGE=$previous_nginx_image|" .env.prod.local
        "${compose[@]}" pull php worker nginx
        "${compose[@]}" up -d --force-recreate php worker nginx
        curl --fail --silent --show-error --retry 10 --retry-connrefused --retry-delay 3 \
            "$healthcheck_url" >/dev/null
        set -e
    fi

    if [[ -f ${backup_file:-} ]]; then
        echo "The database backup created before the migration is kept in .deploy/backups/." >&2
    fi
    exit "$failed_status"
}

trap restore_previous_release ERR

echo "Fetching production revision $deploy_sha."
git fetch --quiet origin main:refs/remotes/origin/main
if ! git cat-file -e "${deploy_sha}^{commit}"; then
    echo "The requested revision was not fetched from origin/main." >&2
    exit 65
fi
if ! git merge-base --is-ancestor "$deploy_sha" origin/main; then
    echo "Refusing to deploy a revision that is not part of origin/main." >&2
    exit 65
fi

backup_file=".deploy/backups/database-$(date -u +%Y%m%dT%H%M%SZ)-${deploy_sha:0:12}.dump"
echo "Creating database backup $backup_file."
"${compose[@]}" exec -T database sh -c \
    'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom' >"$backup_file"
chmod 600 "$backup_file"

deployment_started=1
git checkout --quiet --detach "$deploy_sha"

sed -i "s|^PHP_IMAGE=.*$|PHP_IMAGE=ghcr.io/sauvank/job-matcher-php:sha-$deploy_sha|" .env.prod.local
sed -i "s|^NGINX_IMAGE=.*$|NGINX_IMAGE=ghcr.io/sauvank/job-matcher-nginx:sha-$deploy_sha|" .env.prod.local

"${compose[@]}" config --quiet
"${compose[@]}" pull php worker nginx
"${compose[@]}" run --rm php \
    php bin/console doctrine:migrations:migrate --no-interaction
"${compose[@]}" up -d --force-recreate php worker nginx

echo "Waiting for the local application health check."
curl --fail --silent --show-error --retry 20 --retry-connrefused --retry-delay 3 \
    "$healthcheck_url" >/dev/null

"${compose[@]}" ps
trap - ERR
echo "Production deployment completed for $deploy_sha."
