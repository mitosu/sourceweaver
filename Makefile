.PHONY: up down restart php bash logs install symfony dbshell rabbitmq

up:
	@echo "Iniciando contenedores..."
	@echo "Asegúrate de que Docker esté en ejecución."
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

fix-permissions:
	@echo "Corrigiendo permisos de archivos..."
	sudo chown -R $(shell id -u):$(shell id -g) .
	@echo "Permisos corregidos correctamente."

# Target para ejecutar comandos específicos de Symfony
symfony-command:
	docker-compose exec php php bin/console $(cmd)