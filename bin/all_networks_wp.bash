#!/bin/bash
#set -ex
set -x # since some networks do not exist yet, we want to proceed even when wp complains about that, so do not exit on error
#
# Will run a wp cli command on each network in a multi-network setup (ONE.MYDOMAIN.ORG, etc.)
#
source /opt/rh/rh-php70/enable
domain="northeasterncommons.org"
networks=("phdnetwork" "gse" "next")
path="/wordpressdata/nucommons/web/wp"
pre_php=/tmp/__pre.php; [[ -e "$pre_php" ]] || echo "<?php error_reporting( 0 ); define( 'WP_DEBUG', false );date_default_timezone_set( 'America/New_York' );" > "$pre_php"
# show help & bail if no arguments passed
if [[ -z "$*" ]]
then
        echo "usage: $0 [wp command]"
        echo "  e.g. $0 plugin activate debug-bar"
        exit 1
fi
# first the main network
/usr/local/bin/wp --require="$pre_php" --url="$domain" --path="$path" $*
# now the rest
for slug in "${networks[@]}"
do
        /usr/local/bin/wp --require="$pre_php" --url="$slug.$domain" --path="$path" $*
done
