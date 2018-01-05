#!/bin/sh
yum install epel-release -y &&
rpm -ivh http://rpms.famillecollet.com/enterprise/remi-release-7.rpm &&
yum install httpd mod_ssl openssl -y &&
/bin/cp -f ./data/MariaDB.repo /etc/yum.repos.d &&
/bin/cp -r -f ./data/test.com /home &&
/bin/cp -f ./data/vhost.conf /etc/httpd/conf.d &&
/bin/cp -f ./data/httpd.conf /etc/httpd/conf &&
yum install mariadb-server mariadb  -y &&
/bin/cp -f ./data/server.cnf /etc/my.cnf.d &&
yum install --enablerepo=remi --enablerepo=remi-php56 php php-opcache php-devel php-mbstring php-mcrypt php-mysqlnd php-phpunit-PHPUnit php-pecl-xdebug php-pecl-xhprof php-bcmath php-gd php-gmp php-ldap php-snmp net-snmp rrdtool net-snmp-libs net-snmp-utils -y &&
systemctl start firewalld &&
firewall-cmd --add-service=http --permanent
firewall-cmd --add-service=https --permanent
firewall-cmd --add-service=snmp --permanent
systemctl stop firewalld
setenforce 0
sed -i 's/SELINUX=enforcing/SELINUX=disabled/g' /etc/selinux/config &&
sed -i '57a view    all     included        .1' /etc/snmp/snmpd.conf &&
sed -i '890a date.timezone = Asia/Chongqing' /etc/php.ini &&
systemctl start httpd mariadb snmpd &&
systemctl enable httpd mariadb snmpd &&
chmod -R 777 /home/test.com/logs
chmod -R 777 /home/test.com/public_html/cacti/resource
chmod -R 777 /home/test.com/public_html/cacti/scripts
chmod -R 777 /home/test.com/public_html/cacti/log
chmod -R 777 /home/test.com/public_html/cacti/rra
echo "*/1 * * * * /bin/php /home/test.com/public_html/cacti/poller.php" >> /var/spool/cron/root
mysqladmin -uroot 'Admin888'
HOSTNAME="127.0.0.1"
DBNAME="cacti"
PORT="3306"
USERNAME="root"
PASSWORD="Admin888"
create_db_sql1="create user '$DBNAME'@'localhost' identified by 'Botonet123'"
create_db_sql2="create database IF NOT EXISTS ${DBNAME}"
create_db_sql3="GRANT all privileges ON *.* TO '$DBNAME'@'localhost'"
mysql -h${HOSTNAME}  -P${PORT}  -u${USERNAME} -p${PASSWORD} -e "${create_db_sql1}"
mysql -h${HOSTNAME}  -P${PORT}  -u${USERNAME} -p${PASSWORD} -e "${create_db_sql2}"
mysql -h${HOSTNAME}  -P${PORT}  -u${USERNAME} -p${PASSWORD} -e "${create_db_sql3}"
mysql -h$HOSTNAME -u$USERNAME -p$PASSWORD cacti < /home/test.com/public_html/cacti/cacti.sql
mysql_tzinfo_to_sql /usr/share/zoneinfo/ | mysql -u root -p mysql
#mysql_secure_installation
echo "Your Cacti Platform installed successfully "