# Installation

As one of ObStack's goals the installation is easy to setup using a basic linux webserver setup with PostgreSQL, as described below.

Obstack also supplies a <a href="https://github.com/obstack-org/obstack-docker" target="_blank">docker</a> image and a docker-compose script for easy deployment.

## Requirements

* Linux server
* Web server (Apache2, NGINX)
* PHP >= v7.4 _**with packages:**_ pdo pdo_pgsql session mbstring json ldap
* PostgreSQL >= v11 _**with packages:**_ contrib

## Basic example (AlmaLinux8/Rocky8)

**_Note:_** \
_The security and credentials used in this example is for test and/or demo purposes only. For other usage configure your security accordingly._

```bash
# Install packages
sudo dnf -y module disable php postgresql
sudo dnf -y module enable php:7.4 postgresql:12
sudo dnf -y install httpd php php-pdo php-pgsql postgresql-server postgresql-contrib git

# Setup Apache2
sudo setsebool -P httpd_can_network_connect_db on
sudo systemctl enable httpd && sudo systemctl start httpd
sudo firewall-cmd --permanent --zone=public --add-service=http
sudo firewall-cmd --permanent --zone=public --add-service=https
sudo firewall-cmd --reload

# Setup PostgreSQL
sudo postgresql-setup --initdb
sudo sed -i '/^# TYPE/a\\n# ObStack\nhost\tobstack\t\tobstack\t\t127.0.0.1/32\t\tmd5\nhost\tobstack\t\tobstack\t\t::1/128\t\t\tmd5' /var/lib/pgsql/data/pg_hba.conf
sudo systemctl enable postgresql && sudo systemctl start postgresql
sudo -u postgres bash -c "cd; createdb obstack; psql -c \"CREATE USER obstack WITH PASSWORD 'obstack'; GRANT CONNECT ON DATABASE obstack TO obstack;\""

# Setup ObStack
cd /var/lib
sudo git clone "https://github.com/obstack-org/obstack.git"
sudo -u postgres psql obstack </var/lib/obstack/resources/obstack-schema-v1.sql
sudo -u postgres bash -c "cd; psql obstack -c \"GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO obstack; GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO obstack;\""
sudo ln -s /var/lib/obstack/webapp /var/www/html/obstack
sudo mkdir -pm 750 /etc/obstack
cp /var/lib/obstack/resources/obstack.conf /etc/obstack/
chown -R root:apache /etc/obstack
```

Now login to your new installation on [http://yourserver/obstack](http://yourserver/obstack) with default authentication: _admin_/_admin_.
