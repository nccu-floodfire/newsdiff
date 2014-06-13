init:
	echo 'drop database if exists `newsdiff`; create database `newsdiff` default character set utf8;' | mysql -u root
	php webdata/scripts/table-build.php
	php webdata/scripts/news-raw-tables.php

all: init
