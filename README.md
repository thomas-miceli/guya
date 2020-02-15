# guya
Github €CO+

* Set up the project with
```
$ ./install.sh <db_user> <db_password> <db_name>
```

* Create/delete user
```
$ php bin/console guya:au
$ php bin/console guya:ru
```

* Start the server
```
$ symfony serve
```
or set root `/public` with apache/nginx.