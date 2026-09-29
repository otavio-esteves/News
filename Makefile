DC = docker compose

.PHONY: setup up down restart shell test lint logs artisan composer npm db-reset

setup:
	@test -f .env || cp .env.example .env
	$(DC) up -d postgres
	$(DC) build app
	$(DC) run --rm app composer install
	@if grep -q '^APP_KEY=$$' .env; then $(DC) run --rm app php artisan key:generate; fi
	$(DC) run --rm app php artisan migrate --force
	$(DC) run --rm app php artisan db:seed --force
	$(DC) run --rm node npm ci
	$(DC) run --rm node npm run build
	$(DC) up -d

up:
	$(DC) up -d

down:
	$(DC) down

restart:
	$(DC) restart

shell:
	$(DC) exec app sh

test:
	$(DC) run --rm --no-deps -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e QUEUE_CONNECTION=sync -e SESSION_DRIVER=array app php artisan test

lint:
	$(DC) run --rm app ./vendor/bin/pint --test

logs:
	$(DC) logs -f

artisan:
	$(DC) exec app php artisan $(cmd)

composer:
	$(DC) run --rm app composer $(cmd)

npm:
	$(DC) run --rm node npm $(cmd)

db-reset:
	$(DC) exec app php artisan migrate:fresh --seed
