#!/usr/bin/make -f

CURRENT_UID := $(shell id -u)
CURRENT_GID := $(shell id -g)

COMPOSE = UID=$(CURRENT_UID) GID=$(CURRENT_GID) docker-compose
PHP = $(COMPOSE) run --rm --no-deps php

php:
	$(PHP) sh
serve:
	$(PHP) composer update
	$(PHP) vendor/bin/rr get -n
	$(COMPOSE) up
build:
	$(COMPOSE) build
