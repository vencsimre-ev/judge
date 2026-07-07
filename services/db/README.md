# DB service

MariaDB service folder.

- `conf.d/`: optional MariaDB config snippets mounted to `/etc/mysql/conf.d`
- `initdb/`: optional first-run SQL scripts mounted to `/docker-entrypoint-initdb.d`

The database data itself is stored in the Docker volume `dbdata`, not in this folder.
