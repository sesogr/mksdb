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

