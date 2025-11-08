#!/bin/bash
set -e

# First arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
    set -- php "$@"
fi

# If command starts with an option, prepend php
if [ "${1:0:1}" = '-' ]; then
    set -- php "$@"
fi

# Check for Composer install
if [ "$1" = 'composer' ]; then
    exec "$@"
fi

# Check for Castor command
if [ "$1" = 'castor' ]; then
    exec "$@"
fi

# Execute command
exec "$@"