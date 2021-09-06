FROM php:apache
RUN apt-get update
RUN apt-get install -y --no-install-recommends libpq-dev
RUN docker-php-ext-install mysqli pdo_pgsql pdo_mysql