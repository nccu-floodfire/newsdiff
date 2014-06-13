include /opt/u/share/dev.make


myinitdb::
ifdef DB_PASSWORD
	mysql -u root -p$(DB_PASSWORD) < ./_INSTALL/data.sql
	mysql -u root -p$(DB_PASSWORD) < ./_INSTALL/permission.sql
else
	mysql -u root < ./_INSTALL/data.sql
	mysql -u root < ./_INSTALL/permission.sql
endif
	php webdata/scripts/table-build.php
	php webdata/scripts/news-raw-tables.php



all:: myinitdb
