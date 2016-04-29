REM set the number of millisecs to change the interval of the rename process
:Top
php renamer.php
REM millisecs set to 30 mins
set millisecs=1800000
PING 1.1.1.1 -n 1 -w %millisecs% >NUL
Goto Top