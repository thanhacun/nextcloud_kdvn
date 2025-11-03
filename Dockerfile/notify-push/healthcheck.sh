#!/bin/bash

if ! nc -z "$NEXTCLOUD_HOST" 80; then
    exit 0
fi

nc -z 127.0.0.1 7867 || exit 1
