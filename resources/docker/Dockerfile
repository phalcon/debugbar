# syntax=docker/dockerfile:1
ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-cli AS base

ARG PHP_VERSION=8.5
ARG PHALCON_VARIANT=v5
ARG PHALCON_V5_CONSTRAINT="^5.0"
# PIE release and the sha256 of its pie.phar. Update both for a new release.
ARG PIE_VERSION=1.5.5
ARG PIE_SHA256=2ca0dff6868f1b53c095625674deef38ac698588e0a25e4321cd834a5b7553bc
ARG UID=1000
ARG GID=1000
ARG USER=debugbar
ARG GROUP=debugbar

# System packages + PHP extensions + an unprivileged user matching the host UID
RUN <<EOT
set -eux
groupadd -g "${GID}" "${GROUP}"
useradd -l -m -u "${UID}" -g "${GID}" -s /bin/bash "${USER}"
apt-get update -y
apt-get install -yq --no-install-recommends \
    curl git unzip zip \
    libicu-dev libzip-dev
docker-php-ext-install intl zip
apt-get clean -y
rm -rf /var/lib/apt/lists/*
EOT

# Composer
COPY --from=composer/composer:2 /usr/bin/composer /usr/bin/composer

# Node.js for the JavaScript tests (`node --test`), the same major version as CI
COPY --from=node:26-slim /usr/local/bin/node /usr/local/bin/node

RUN <<EOT
set -eux
apt-get update -y
apt-get install -yq --no-install-recommends ${PHPIZE_DEPS} libtool
curl -fsSL "https://github.com/php/pie/releases/download/${PIE_VERSION}/pie.phar" -o /usr/local/bin/pie
echo "${PIE_SHA256}  /usr/local/bin/pie" | sha256sum --check --strict
chmod +x /usr/local/bin/pie
pie install --no-interaction pecl/pcov
# Phalcon v5 C extension (v5 variant only; v6 uses the phalcon/phalcon composer package)
if [ "${PHALCON_VARIANT}" = "v5" ]; then
    pie install --no-interaction phalcon/cphalcon:${PHALCON_V5_CONSTRAINT}
    php -m | grep -i phalcon
fi
apt-get clean -y
rm -rf /var/lib/apt/lists/*
EOT

COPY resources/docker/extra.ini /usr/local/etc/php/conf.d/zz-debugbar.ini
COPY --chown=${UID}:${GID} resources/docker/.bashrc /home/${USER}/.bashrc

ENV HOME=/home/${USER} \
    COMPOSER_HOME=/home/${USER}/.composer

RUN install -d -m 0775 -o "${USER}" -g "${GROUP}" /srv
WORKDIR /srv
USER ${USER}

# No code/vendor baked in: the compose bind mount provides /srv and `composer install`
# runs there. For the v6 variant, also run `composer require --dev "phalcon/phalcon:^6@dev"`.

# Keepalive so `docker compose up -d` holds the container open for `exec`.
CMD ["sleep", "infinity"]
