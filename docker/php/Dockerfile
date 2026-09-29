FROM dunglas/frankenphp:1-php8.5

RUN install-php-extensions @composer pdo_pgsql intl zip

WORKDIR /app

COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
