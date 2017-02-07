#!/bin/bash -xe

if ! docker-compose up -d > /tmp/docker-compose-up-d.log 2>&1 ; then
  cat /tmp/docker-compose-up-d.log
  exit -1
fi
set +x
MAX_TRIES=60
HTTP_CODE=$(curl -sL -w "%{http_code}\\n" localhost:8121/keys -o /dev/null || true)
while [[ $HTTP_CODE != 200 && $MAX_TRIES > 0 ]] ; do
  sleep 1
  HTTP_CODE=$(curl -sL -w "%{http_code}\\n" localhost:8121/keys -o /dev/null || true)
  MAX_TRIES=$(( MAX_TRIES - 1 ))
done
set -x
docker run -it --rm --net=pacificauploadstatus_default -e METADATA_URL=http://metadataserver:8121 -e PYTHONPATH=/usr/src/app pacifica/metadata python test_files/loadit.py
docker-compose stop uploadstatus
echo "doing unit tests"
cp vendor/phpunit/phpunit-selenium/PHPUnit/Extensions/SeleniumCommon/phpunit_coverage.php .
if ! ./vendor/bin/phpunit --coverage-text tests ; then
  cat /tmp/selenium-server.log || true
  cat travis/error.log || true
  cat travis/php-error.log || true
  exit -1
fi
