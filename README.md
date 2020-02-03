# guya
Github €CO+

```
$ cp .env .env.local
```
* Change DATABASE_URL on the new file then run

```
$ ./install.sh
```

* Create/delete user
```
$ php bin/console guya:au
$ php bin/console guya:ru
```

* Change nginx config on your needs