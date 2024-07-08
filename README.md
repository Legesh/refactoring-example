## Running the code

```
> composer install
> php index.php input.txt
```

## Test the code
```
> composer test
```

## Notes

Please pay attention that API for getting country by BIN number 
allows only 5 requests per hour.  Also, the API that returns exchange rates 
(exchangeratesapi.io) require API access_key, so I created my personal 
and put it to the source code for test, but it has some limits. 

