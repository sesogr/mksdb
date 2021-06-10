# Installation

1. Import the core data using one of the sets in [csv-import](./csv-import/). There is a complete single file or folders with sets of multiple files. Those files are clearly labelled and must be executed in the given order. This step will be necessary only during the very first installation. Once succeeded, you can skip this for any subsequent updates and fixes.

2. Execute the [SQL operations](./operations-compact.sql) that build up the actual working tables from the cora data set. Since the core data set is not changes by this operation, you can repeat this over and over, only rebuilding the working tables. This step should be repeated after any update or fix.

3. Edit config.inc.php to configure the connection to your database. The original values only apply to the docker container spun up during development and thus have no other meaning than serving as an example.

# Work log

1. export as CSV with all text cells quoted
2. in DataGrip create new MariaDB connection to docker container
3. import CSV
    1. with first line as header
    2. trim whitespace
    3. remove single-quoted values
    4. add column ID with int unsigned not null primary key auto_increment
    5. change all other columns to TEXT

# Issues encountered

- no value in AG12498 => 12497:Index is null
- no value in A2938:AF2938 and A2976:AF2976 => 2937 and 2975: everything but Index is null
- strange value in Q3613 => 3613:\[Interpret 4] is 08.12.2020

# Development DB instance

    docker run \
        -p 127.0.0.1:3306:3306 \
        --name mariadb-schlager \
        -e MYSQL_DATABASE=schlager \
        -e MYSQL_USER=schlager \
        -e MYSQL_PASSWORD=zorofzoftumev \
        -e MYSQL_RANDOM_ROOT_PASSWORD=yes \
        -d \
        mariadb \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci

Generated root password is ```6f5df6289dee033bf2b8205e5ce4eb5d7cb3b5d0d5da67ac96c8b0a7a09c2e61```.

