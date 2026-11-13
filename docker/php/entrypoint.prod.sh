#!/bin/sh
set -e

# Configuration, event and view caches depend on the final environment, so they are
# built when the container starts. Routes are not cached (some use closures).
#
# Migrations are NEVER run here: they are an explicit, documented command so that
# several containers cannot race each other or change the schema unexpectedly.
php artisan config:cache --no-interaction
php artisan event:cache --no-interaction
php artisan view:cache --no-interaction

exec "$@"
