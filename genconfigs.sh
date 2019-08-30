#!/bin/bash

reldir=`dirname $0`
cd $reldir

php ./genconfigs.php

cd ./configs

if diff sip.conf sip.conf.new > /dev/null 2>&1
then
  echo "SIP config not changed"
else
  cp sip.conf.new sip.conf
  cp sip.registry.conf.new sip.registry.conf
  asterisk -rx "sip reload"
fi

if diff queues.conf queues.conf.new > /dev/null 2>&1
then
  echo "Queue config not changed"
else
  cp queues.conf.new queues.conf
  asterisk -rx "queue reload all"
fi