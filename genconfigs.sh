#!/bin/bash

reldir=`dirname $0`
cd $reldir

mkdir -p ./configs
php ./genconfigs.php $1
