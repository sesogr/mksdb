FROM php:apache
RUN apt-get update; \
  apt-get install -y --no-install-recommends libpq-dev; \
  docker-php-ext-install mysqli pdo_pgsql pdo_mysql; \
  a2enmod rewrite \
