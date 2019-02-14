#!/bin/bash
set -e

#
# Index (or optionally setup, then index) elasticpress content.
#
# To setup, pass the string "setup" as the first parameter to this script.
# e.g.
# bash bin/index_elasticpress.bash setup
#
# Otherwise, with no parameters, existing content is reindexed without deleting anything.
# e.g.
# bash bin/index_elasticpress.bash
#
source /opt/rh/rh-php70/enable

wp="sudo -u apache /usr/local/bin/wp --path=/wordpressdata/nucommons/web/wp --url=$(hostname)"
all_networks_wp=/wordpressdata/nucommons/bin/all_networks_wp.bash

if [[ "$1" = "setup" ]]
then
  $all_networks_wp elasticpress index --setup
else
  $all_networks_wp elasticpress index --allow-root
fi
$wp elasticpress-buddypress index_from_all_networks --post-type=humcore_deposit --allow-root
$all_networks_wp elasticpress-buddypress index --allow-root
