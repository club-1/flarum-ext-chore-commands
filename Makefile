DATE = $(shell date +%F)
REPO_URL = https://github.com/club-1/flarum-ext-chore-commands
INTERACTIVE := $(shell [ -t 0 ] && echo 1)
PHPSTANFLAGS += $(if $(INTERACTIVE),,--no-progress) $(if $(INTERACTIVE)$(CI),,--error-format=raw)
PHPUNITFLAGS += $(if $(INTERACTIVE)$(CI),--coverage-text,--colors=never)

export FLARUM_TEST_TMP_DIR ?= tests/integration/tmp
export DB_USERNAME         ?= $(USER)

all: vendor;

dev: vendor;

vendor: composer.json composer.lock
	composer install
	touch $@

$(FLARUM_TEST_TMP_DIR): vendor
	composer test:setup
	touch $@

# Create a new release
bump = echo '$2' | awk 'BEGIN{FS=OFS="."} {$$$1+=1} 1'
releasepatch: V := 3
releaseminor: V := 2
releasemajor: V := 1
release%: PREVTAG = $(shell git describe --tags --abbrev=0)
release%: TAG = v$(shell $(call bump,$V,$(PREVTAG:v%=%)))
release%: CONFIRM_MSG = Create release $(TAG)
releasepatch releaseminor releasemajor: release%: .confirm check all
	sed -i CHANGELOG.md \
		-e '/^## \[unreleased\]/s/$$/\n\n## [$(TAG)] - $(DATE)/' \
		-e '/^\[unreleased\]/{s/$(PREVTAG)/$(TAG)/; s#$$#\n[$(TAG)]: $(REPO_URL)/releases/tag/$(TAG)#}'
	git add CHANGELOG.md
	git commit -m $(TAG)
	git push
	git tag $(TAG)
	git push --tags

check: analyse test;

analyse: analysephp;

analysephp: vendor
	vendor/bin/phpstan analyse $(PHPSTANFLAGS)

test: testintegration;
#test: testunit testintegration;

testunit testintegration: export XDEBUG_MODE=coverage
testunit testintegration: test%: vendor $(FLARUM_TEST_TMP_DIR)
	composer test:$* -- --coverage-cache=tests/.phpunit.cov.cache --coverage-clover=tests/.phpunit.$*.cov.xml $(PHPUNITFLAGS)

clean: cleancache
	rm -rf vendor

cleancache:
	rm -rf tests/.phpunit*

.confirm:
	@echo -n "$(CONFIRM_MSG)? [y/N] " && read ans && [ $${ans:-N} = y ]

.PHONY: all dev releasepatch releaseminor releasemajor check analyse analysephp test testunit testintegration clean cleancache .confirm
