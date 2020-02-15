# guya
Github €CO+

```
$ cp .env .env.local
```
* Change [DATABASE_URL](https://github.com/thomas-miceli/guya/blob/master/.env#L28) on the new file then run

```
$ ./install.sh
```

* Create/delete user
```
$ php bin/console guya:au
$ php bin/console guya:ru
```

* Start the server
```
$ php -S localhost:8000 -t public
```
