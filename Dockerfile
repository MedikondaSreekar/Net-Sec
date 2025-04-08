FROM ubuntu:22.04
ENV DEBIAN_FRONTEND=noninteractive

# Install dependencies
RUN apt-get update && \
    apt-get install -y \
    composer \
    sudo \
    curl \
    git \
    postgresql \
    postgresql-contrib \
    php \
    php-pgsql \
    nano\
    php8.1-curl

WORKDIR /var/www/html
COPY . /var/www/html

# Configure PostgreSQL to listen only on localhost
RUN echo "listen_addresses = 'localhost'" >> /etc/postgresql/14/main/postgresql.conf

# Create non-root user
RUN useradd -m netsec && \
    echo "netsec:bhagvatgita" | chpasswd

# Add netsec to the sudoers file (requires password for sudo)
RUN usermod -aG sudo netsec

# Initialize PostgreSQL
RUN service postgresql start && \
    sudo -u postgres psql -c "ALTER USER postgres PASSWORD 'parleg';" && \
    service postgresql stop

COPY start.sh /home/netsec/start.sh
RUN chmod +x /home/netsec/start.sh
ENTRYPOINT ["/home/netsec/start.sh"]
    

