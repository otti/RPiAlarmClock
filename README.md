RPiAlarmClock
=============

Raspberry Pi Alarm Clock with Web Interface

##Setup
* groupadd www-data
* usermod -a -G www-data www-data
* apt-get update
* apt-get install apache2
* apt-get install php5
* apt-get install libapache2-mod-php5 php5 php5-cli php5-common php5-curl php5-dev php5-gd php5-imap php5-ldap php5-mhash php5-mysql php5-odbc
* WiringPI (https://projects.drogon.net/raspberry-pi/wiringpi/)


##Config
* Copy index.php and actions.xml to \var\www\wecker
* Set access rights for actions.xml 'chmod 666 actions.xml'
* Change mixer_type to 'software' in /etc/mpd.conf
* Set 'options snd-usb-audio index=0' in /etc/modprobe.d/alsa-base.conf
* Set volume to 100% with alsamixer
* store mixer settings with 'alsactl store'
* Copy AlarmClock.sh to '/etc/init.d/'
* Make script executable 'chmod 755 /etc/init.d/AlarmClock.sh'
* Add script to runlevel 'update-rc.d AlarmClock.sh defaults'


