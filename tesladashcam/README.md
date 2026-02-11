Attention : still a work in progress, regular updates coming up soon ;-)

## INTRODUCTION :

An add-on to TMM (TeslaMateMail), allowing the use of your Dashcam videos.
Select a directory and then display all 4 cameras ("old" models), a map, and a
movable control bar.
At first select a directory or use files already downloaded in uploads/ :
<img width="1618" height="398" alt="image" src="https://github.com/user-attachments/assets/0319606a-481f-43fc-a744-5f451c6f7405" />
then wait a bit, untile all files are compiled and built-in datas extracted from all videos : 
<img width="1618" height="398" alt="image" src="https://github.com/user-attachments/assets/401831e1-f59a-476d-b77b-7d8ca10d66b0" />
and tadaaaa, the main screen appears :


<img width="1884" height="694" alt="image" src="https://github.com/user-attachments/assets/629692b4-cad1-4df4-83b3-4a3d0e5a5b14" />
You have a floating command bar showing all information :

* blinkers
* breaks light
* Auto Pilot on/off
* Gear : P, N, D, R
* Speed in universal km/h unit
* a progress bar

You can use the progress bar to move to any video timeshift or click on the trip map to go directly to the road you'd like to monitor.

## INSTALLATION :

In /var/www/html, copy the new tesla.php front page, tesladashcam.php.
Add all files in cgi-bin/ too.

I then created a installdashcam.sh script : use "bash installdashcam.sh" and it should install all dependencies you don't have, including Python3 librairies, setting chown and chmod permissions.

In cgi-bin/export.py, you'll find a script which generates uploads/export.mp4, showing ALL information in ONE single video : all four videos, speed, blinkers, etc. Attention : this is a HUGE process, it takes ressources and time to compile, so use a real computer to do this...
