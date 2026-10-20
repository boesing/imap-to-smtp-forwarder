#!/bin/sh
# Generates a throw-away CA and a GreenMail server certificate (CN/SAN: greenmail) into /certs.
# The CA is trusted by the test runner so that the IMAP client validates the certificate like in production.
set -eu

cd /certs

if [ -f greenmail.p12 ]; then
    echo "Certificates already exist, skipping."
    exit 0
fi

openssl req -x509 -newkey rsa:2048 -nodes -days 30 \
    -keyout ca.key -out ca.crt -subj "/CN=imap-to-smtp-forwarder-test-ca"

openssl req -newkey rsa:2048 -nodes \
    -keyout greenmail.key -out greenmail.csr -subj "/CN=greenmail"

printf "subjectAltName=DNS:greenmail\n" > san.ext

openssl x509 -req -in greenmail.csr -CA ca.crt -CAkey ca.key -CAcreateserial \
    -days 30 -extfile san.ext -out greenmail.crt

openssl pkcs12 -export -in greenmail.crt -inkey greenmail.key -certfile ca.crt \
    -name greenmail -passout pass:changeit -out greenmail.p12

chmod 644 /certs/*
