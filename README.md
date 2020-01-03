# WeGov openreferral API and demo Application

## Environment

**LAMP stack**
*	php >=7.1
*	MySQL >=5.7
*	Apache >=2.2
*	mod_rewrite
*	composer


## Installation

Arrange Apache virtual host - level 1 domain or subdomain, not a level 2+ folder

`cd /target/folder`

`git clone https://github.com/sarapis/orapi-wegov .`

`mysql -u root -p < fusio-wegov.sql`

`composer update`



Enter root API entry point into .env file located at _/target/folder/.env_

ex: `FUSIO_URL="https://example.com/api"`



.. and into env.php application file located at _/target/folder/socialServicesApp/include/env.php_

ex: `define('APIENTRY', 'https://example.com/api');`


## API

API is available for reading data from several entry points described by Human Service Data API Suite (HSDA) - https://openreferral.readthedocs.io/en/latest/hsda/

Base API entry point is /api

Authentication is not required



## API back-end

/api/fusio/#!/login

user: fusio-orapi-user

pass: Openreferral123changethis++


## MySQL

During the installation two databases and service user will be created:
* DB `openreferral_data` - containing main HSDA dataset
* DB `openreferral_fusio` - containing management information for API



user: fusio-orapi-user

pass: Openreferral123changethis++


If you need to change DB names or credentials you have to update 
* .env file located at _/target/folder/.env_
* Connection &gt; Mysql_HSDA parameters at API back-end
