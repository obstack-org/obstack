# Installation

As one of ObStack's goals the installation is easy to setup using a basic linux webserver setup with PostgreSQL, as described below.

Obstack also supplies a <a href="https://github.com/obstack-org/obstack-docker" target="_blank">docker</a> image and a docker-compose script for easy deployment.

## Requirements

* Linux server
* Web server (Apache2, NGINX)
* PHP >= v7.4 _**with** pdo package_
* PostgreSQL >= v11 _**with** contrib package_

## Basic example (AlmaLinux8/Rocky8)
 
```
# Install packages
sudo dnf -y module disable php postgresql
sudo dnf -y module enable php:7.4 postgresql:12
sudo dnf -y install httpd php php-pdo postgresql-server postgresql-contrib sudo git

# Setup Apache2
sudo systemctl enable httpd && sudo systemctl start httpd
sudo firewall-cmd --permanent --zone=public --add-service=http
sudo firewall-cmd --permanent --zone=public --add-service=https
sudo firewall-cmd --reload

# Setup PostgreSQL
sudo postgresql-setup --initdb
sudo systemctl enable postgresql && sudo systemctl start postgresql
sudo -u postgres bash -c "cd; createdb obstack; psql -c \"CREATE USER obstack WITH PASSWORD 'obstack'; GRANT ALL PRIVILEGES ON DATABASE obstack TO obstack;\""

# Setup ObStack
cd /var/lib
sudo git clone "https://github.com/obstack-org/obstack.git"
sudo -u postgres psql /var/lib/obstack/resources/obstack-postgresql.sql
sudo ln -s /var/lib/obstack/webapp /var/www/html/obstack
```
