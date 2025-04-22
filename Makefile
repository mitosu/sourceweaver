.PHONY: up down restart php bash logs install symfony dbshell rabbitmq

up:
	docker-compose up -d --build

down:
	docker-compose down

restart:
	docker-compose down
	docker-compose up -d --build

php:
	docker-compose exec php php

bash:
	docker-compose exec php bash

logs:
	docker-compose logs -f

install:
	docker-compose exec php composer install

symfony:
	docker-compose exec php php bin/console

dbshell:
	docker-compose exec db mysql -u symfony -psecret ironwhisper

rabbitmq:
	open http://localhost:15672

fix-perms:
	sudo chown -R $$(id -u):$$(id -g) var
	sudo chmod -R 775 var