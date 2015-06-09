# APRS-DB #

This is a simple application that collects world-wide APRS traffic from the APRS-IS network and
stores it in a mysql database that can be queried via a simple http interface.

The purpose of this software is to feed APRS data to the [Hab
Tracker](https://github.com/trackuino/hab-tracker) Android app to track high altitude balloons
on the field.

The APRS network generates **huge** amounts of data, so only data of the past few days is kept.
The aprs2db daemon uses mysql partitions to efficiently rotate older data without impacting the
CPU, so this can be run on servers with low resources.

## Installing ##

Installation has been tested on Ubuntu 12.04 LTS.

1. Install apache + PHP:

   apt-get install apache2 php5 libapache2-mod-php5

2. Copy 'setup.php' over to /var/www/

   cp setup.php /var/www

3. Browse http://yourserver/setup.php and follow the instructions there.

## Ask me if you want me to host it ##

Want to use Hab Tracker to track your balloon but don't want to mess with aprsdb or don't have
a spare server to install this on? Contact me and I can host aprsdb for you.
