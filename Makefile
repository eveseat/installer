# SeAT Installer distribution phar Makefile

all: phar shasum 

phar:
	@echo Building new phar
	vendor/bin/box build
	@echo Phar file built

shasum:
	@echo Generating new shasum
	cd dist/; shasum seat.phar > seat.phar.version
	@echo New shasum recorded

