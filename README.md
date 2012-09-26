# zf2-geoname

[![Build Status](https://secure.travis-ci.org/heartsentwined/zf2-geoname.png)](http://travis-ci.org/heartsentwined/zf2-geoname)

Maintain a local copy of the [Geonames](http://geonames.org) (places) database.

This module will install a self-updating local copy of the Geonames (places) database. It covers the location information, as available from [the official database dump](http://download.geonames.org/export/dump/). It does not cover the "add-ons", e.g. earthquake, weather data, etc, as available from their webservices.

**Attention**: the Geonames database is around 1.5GiB - 2GiB in size, when installed in a MySQL database. Are you sure you need a local copy, instead of [the official webservices](http://www.geonames.org/export/ws-overview.html)?

# Installation

[Composer](http://getcomposer.org/):

```json
{
    "require": {
        "heartsentwined/zf2-geoname": "3.*"
    }
}
```

Then add `Heartsentwined\Geoname` to the `modules` key in `(app root)/config/application.config.*`

Geoname module will also hook onto your application's database, through [`DoctrineORMModule`](https://github.com/doctrine/DoctrineORMModule). It will create a number of tables with the prefix `he_geoname_*`, and will use the default EntityManager `doctrine.entitymanager.orm_default`. If your settings are different, please modify the `doctrine` section of `config/module.config.yml` as needed.

Geoname module makes use of the [Cron module](https://github.com/heartsentwined/zf2-cron), so make sure you follow its settings, and have set up your cron job properly.

Finally, you need to update your database schema. The recommended way is through Doctrine's CLI:

```sh
$ vendor/bin/doctrine-module orm:schema-tool:update --force
```

# Config

Copy `config/geoname.local.php.dist` to `(app root)/config/autoload/geoname.local.php`, and modify the settings.

- `tmpDir`: temporary directory for storing geonames database source files. (Make sure it is script-writable.)
- `cron`: (cron expression) how frequently Geoname should be run.

How frequent should `cron` be? The recommended setup is every 15 minutes, which is also the default. However, you can make your own adjustments:

At present (18 Sep 2012), it will take 820 cron jobs to install the database. At 15-minute intervals, that would take ~8.5 days to install the database. As for the updates, only 1 cron job per day is needed. However, setting more than one per day is highly recommended to provide redundancy - just in case the geonames server is temporarily unreachable, for example.

You can also adjust to a less frequent cron after install. The `status` field of the `Meta` entity, or the `he_geoname_meta` table, will be `Heartsentwined\Geoname\Repository\Meta::STATUS_INSTALL_*` during installation, and `Heartsentwined\Geoname\Repository\Meta::STATUS_UPDATE` afterwards.

# Usage

## Database sync

Just follow the installation instructions. Geoname module will install and update its database in your cron jobs.

Note: when the sources indicate a "delete", Geoname module will not actually delete the corresponding record from your database; it will only mark it as deprecated (by setting the `isDeprecated` field to `true` in the entities `Place` and `AltName`). This is to ensure that you can rely on the primary IDs set up by Geoname module in your ZF2 app.

## Querying the database

You can use the Doctrine 2 ORM API directly. Mapping files are located at `(zf2-geoname)/src/Heartsentwined/Geoname/Entity/Mapping`. All places' hierarchy, from `continent`, `country`, to the various administration levels, have been properly captured in the `parent` and `children` fields of the `Place` entity.

**TODO**: add a set of API for common tasks, e.g. finding a place by name, listing all places in a country, etc.
