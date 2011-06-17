#!/bin/bash

if [ ! $# -eq 1 ] 
then
    echo "Usage $0 [Numero de version]"
    exit 1
fi

VERSION=$1

DIR=`pwd`
cd /tmp/
rm -rf /tmp/respear-$VERSION

#generation du tar.gz
echo  "Génération de respear-$VERSION.tar.gz"
svn export --quiet https://svn.inist.fr/repository/respear/trunk/var/www/respear /tmp/respear-$VERSION
echo $VERSION > respear-$VERSION/version
tar czf $DIR/respear-$VERSION.tar.gz respear-$VERSION/

rm -rf /tmp/respear-$VERSION
cd $DIR
