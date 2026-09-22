#!/usr/bin/env bash
# Starts, lists and removes throwaway client demos. Each demo is a `demo/<slug>`
# branch of THIS repo, never a clone: .github/workflows/docker.yml builds every
# push to demo/** into ghcr.io/<repo>:demo-<slug>, and a pre-made Dokploy
# "slot" (demo1..demoN, each with its own domain and full environment) is
# pointed at it by changing two variables.
#
# Never merge a demo branch into main. Anything every future demo should have
# belongs on main first; bring it into a live demo with `git rebase main`.
#
# A demo that wins the job graduates to its own repo:
#   git push git@github.com:<you>/<project>.git demo/<slug>:main
#
# Usage:
#   ./docker/new-demo.sh <slug>            # branch from origin/main, push, print Dokploy values
#   ./docker/new-demo.sh --list            # demo branches on origin, newest first
#   ./docker/new-demo.sh --delete <slug>   # drop the branch locally and on origin
#
# <slug> is lowercase letters, digits and hyphens (e.g. acme-crm). It becomes
# the image tag demo-<slug> and the database name demo_<slug>.

set -euo pipefail

usage() {
    sed -n '/^# Usage:/,/^$/p' "$0" | sed 's/^# \{0,1\}//'
    exit "${1:-1}"
}

validate_slug() {
    if [[ ! "$1" =~ ^[a-z0-9]+(-[a-z0-9]+)*$ ]] || [ "${#1}" -gt 50 ]; then
        echo "Slug must be lowercase letters, digits and single hyphens (max 50 chars): '$1'" >&2
        exit 1
    fi
}

cd "$(git rev-parse --show-toplevel)"

case "${1:-}" in
    "")
        usage
        ;;

    -h|--help)
        usage 0
        ;;

    --list)
        git fetch --quiet --prune origin
        git for-each-ref --sort=-committerdate \
            --format='%(committerdate:short)  %(refname:lstrip=3)' \
            'refs/remotes/origin/demo/'
        exit 0
        ;;

    --delete)
        slug="${2:-}"
        validate_slug "$slug"
        if [ "$(git branch --show-current)" = "demo/$slug" ]; then
            git switch main
        fi
        git branch -D "demo/$slug" 2>/dev/null || true
        git push origin --delete "demo/$slug"
        echo "Deleted demo/$slug. Its GHCR image (demo-$slug) and database (demo_$slug) are left for you to prune."
        exit 0
        ;;
esac

slug="$1"
validate_slug "$slug"
branch="demo/$slug"

if [ -n "$(git status --porcelain)" ]; then
    echo "Working tree is not clean; commit or stash first." >&2
    exit 1
fi

git fetch --quiet origin main
# Demos branch from origin/main, so work that only exists on the local main --
# including a change to the demo/* trigger itself -- would silently be missing.
if ! git merge-base --is-ancestor main origin/main; then
    echo "WARNING: local main has commits origin/main does not; push main first if this demo needs them." >&2
fi
if git show-ref --quiet "refs/heads/$branch" || git ls-remote --exit-code --heads origin "$branch" >/dev/null; then
    echo "$branch already exists." >&2
    exit 1
fi

git switch --create "$branch" origin/main
# The branch starts identical to main, so this first push builds an image that
# is ready to deploy before any client work lands.
git push --set-upstream origin "$branch"

cat <<EOF

Created $branch (image build started in GitHub Actions).

Once the build is green, on the oldest free Dokploy slot set:

  IMAGE_TAG=demo-$slug
  DB_DATABASE=demo_${slug//-/_}

and redeploy. The database is created on first boot (migrate --force); slots
connect to the shared MariaDB as root, so no grant is needed.

After that, each push to $branch only needs a redeploy of that slot.
EOF
