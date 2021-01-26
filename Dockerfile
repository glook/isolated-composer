FROM php:7.2-cli-alpine

RUN apk update && apk upgrade && \
    apk add --no-cache bash git openssh zip

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN composer global config minimum-stability dev

RUN export COMPOSER_ALLOW_SUPERUSER=1; \
    composer global require glook/isolated-composer:dev-master

COPY src /root/.composer/vendor/glook/isolated-composer/src
COPY bin /root/.composer/vendor/glook/isolated-composer/bin

ENTRYPOINT ["/root/.composer/vendor/glook/isolated-composer/bin/isolated-composer"]
