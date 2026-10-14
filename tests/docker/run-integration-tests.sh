#!/bin/sh
# Trusts the test CA (so ext-imap validates the GreenMail certificate like it does in production),
# installs dev dependencies and runs the integration suite. Additional arguments are passed to PHPUnit.
set -eu

cp /certs/ca.crt /usr/local/share/ca-certificates/forwarder-test-ca.crt
update-ca-certificates >/dev/null

composer install --no-interaction --no-progress

exec vendor/bin/phpunit --testsuite integration "$@"
