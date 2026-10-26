# Built through docker-compose.override.yml (image kdvn/nextcloud-app:34, NC_TAG passed there).
# Keep the default in line with the override; Nextcloud cannot downgrade.
ARG NC_TAG=34-apache
FROM nextcloud:${NC_TAG}

RUN set -ex; \
    sed -i 's|http://|https://|g' /etc/apt/sources.list.d/debian.sources; \
    echo 'Acquire::Retries "3";' > /etc/apt/apt.conf.d/80retries; \
    apt-get update; \
    apt-get install -y --no-install-recommends ffmpeg; \
    rm -rf /var/lib/apt/lists/*

RUN set -ex; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        smbclient libsmbclient-dev nano iputils-ping $PHPIZE_DEPS; \
    pecl install smbclient; \
    docker-php-ext-enable smbclient; \
    apt-get purge -y --auto-remove libsmbclient-dev $PHPIZE_DEPS; \
    apt-get install -y --no-install-recommends libsmbclient; \
    rm -rf /var/lib/apt/lists/*
