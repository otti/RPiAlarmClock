#! /bin/sh

case $1 in
        start)
		echo "Starting AlarmClock"
		/root/AlarmClock/AlarmClock&
		;;
        stop)
		killall AlarmClock 
		;;	

esac 
