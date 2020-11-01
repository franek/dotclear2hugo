Convert an ATOM feed (dotclear) to Hugo content (markdown)
===

This project can be used to convert blog contents from Dotclear to Hugo. 

It will used ATOM feed as source to retrieve data.

Usage
----

```bash
$ composer install
$ php bin/console dotclear2hugo -f https://blog.domain.com/feed/atom
# Blog articles will be created into content directory
```
