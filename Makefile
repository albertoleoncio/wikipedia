COMPOSER ?= composer
.DEFAULT_GOAL := all

.PHONY: all
all: .env clear composer wikipedia

.env:
	cp -n .env.dist .env
	@echo "Remember to configure the .env file" && return 1

.PHONY: clear
clear:
	rm -rf var/tmp/*

.PHONY: composer
composer: vendor/autoload.php

vendor/autoload.php:
	$(COMPOSER) install \
		--no-progress \
		--no-ansi

wikipedia: download-minister wikipedia/create-table wikipedia/publish-table

.PHONY: download-minister
download-minister: composer
	@echo "=> downloads current data from Brazil's Ministry of Health"
	php bin/download-minister.php

.PHONY: wikipedia/create-table
wikipedia/create-table: clear download-minister
	@echo '=> generates covid-19 with portuguese wikipedia format'
	php bin/create-covid-table.php

.PHONY: wikipedia/publish-table
wikipedia/publish-table:
	@echo '=> publish covid-19 table to portuguese wikipedia'
	php bin/publish-table.php
