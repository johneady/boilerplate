#!/usr/bin/env bash
# Runs the Unit and Feature suites against real MySQL, MariaDB and PostgreSQL
# servers, as a stand-in for the `database` job in .github/workflows/ci.yml
# when Actions minutes are unavailable.
#
# The everyday suite runs on in-memory sqlite, which hides the differences
# that matter most: MySQL and MariaDB commit DDL implicitly (ending a test's
# transaction), PostgreSQL stores uuid natively, compares text
# case-sensitively and aborts a whole transaction on one failed statement.
# Each of those has caused a test that is green on sqlite to fail on a server.
#
# Each database runs in a throwaway container on a tmpfs, published on a port
# unlikely to clash with anything local, and is removed when the script exits.
# The images and settings match the CI matrix, so a pass here means the same
# as a pass there.
#
# Usage:
#   ./docker/test-databases.sh                  # all three
#   ./docker/test-databases.sh pgsql            # one or more of: mysql mariadb pgsql
#   ./docker/test-databases.sh mysql -- --filter=Payments
#                                               # anything after -- goes to Pest
#
# Needs docker, and pdo_mysql / pdo_pgsql in the local PHP.

set -euo pipefail

cd "$(dirname "$0")/.."

DATABASES=()
PEST_ARGS=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        --) shift; PEST_ARGS=("$@"); break ;;
        mysql|mariadb|pgsql) DATABASES+=("$1"); shift ;;
        *) echo "Unknown database '$1' (expected mysql, mariadb or pgsql)." >&2; exit 2 ;;
    esac
done

[[ ${#DATABASES[@]} -eq 0 ]] && DATABASES=(mysql mariadb pgsql)

CONTAINERS=()
cleanup() {
    for container in "${CONTAINERS[@]}"; do
        docker rm -f "$container" >/dev/null 2>&1 || true
    done
}
trap cleanup EXIT

start() {
    local database="$1" container="boilerplate-test-$1"

    docker rm -f "$container" >/dev/null 2>&1 || true
    CONTAINERS+=("$container")

    case "$database" in
        mysql)
            docker run -d --name "$container" -p 53306:3306 --tmpfs /var/lib/mysql \
                -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=testing mysql:8.4 >/dev/null || return 1
            PORT=53306 USERNAME=root
            READY=(docker exec "$container" mysql -h127.0.0.1 -uroot -proot -e 'SELECT 1' testing)
            ;;
        mariadb)
            docker run -d --name "$container" -p 53307:3306 --tmpfs /var/lib/mysql \
                -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=testing mariadb:11 >/dev/null || return 1
            PORT=53307 USERNAME=root
            READY=(docker exec "$container" mariadb -h127.0.0.1 -uroot -proot -e 'SELECT 1' testing)
            ;;
        pgsql)
            docker run -d --name "$container" -p 55432:5432 --tmpfs /var/lib/postgresql/data \
                -e POSTGRES_USER=root -e POSTGRES_PASSWORD=root -e POSTGRES_DB=testing postgres:17-alpine >/dev/null || return 1
            PORT=55432 USERNAME=root
            READY=(docker exec "$container" pg_isready -h 127.0.0.1 -U root -d testing -q)
            ;;
    esac

    # Probed over TCP, not the socket: both images first run a temporary
    # server that listens on the socket only while they initialise, and a
    # socket probe that reaches it reports ready before the real one starts.
    for _ in $(seq 1 60); do
        "${READY[@]}" >/dev/null 2>&1 && return 0
        sleep 2
    done

    echo "$database did not become ready within two minutes." >&2
    return 1
}

FAILED=()

for database in "${DATABASES[@]}"; do
    echo "==> $database"

    if ! start "$database"; then
        FAILED+=("$database")
        continue
    fi

    # phpunit.xml sets DB_CONNECTION and friends without force="true", so the
    # environment wins -- the same mechanism the CI job relies on.
    if ! DB_CONNECTION="$database" DB_HOST=127.0.0.1 DB_PORT="$PORT" DB_DATABASE=testing \
        DB_USERNAME="$USERNAME" DB_PASSWORD=root DB_URL='' \
        vendor/bin/pest --ci --parallel --testsuite=Unit,Feature "${PEST_ARGS[@]}"; then
        FAILED+=("$database")
    fi
done

if [[ ${#FAILED[@]} -gt 0 ]]; then
    echo "Failed on: ${FAILED[*]}" >&2
    exit 1
fi

echo "Passed on: ${DATABASES[*]}"
