Program: BTVRenamer
Developed by: Bryan Price
Email: anomaly07@gmail.com

About the Script: 

This script should be used for organizing BeyondTV files in such a way that XBMC or Boxee can detect the media.  There are 3 suggested ways to setup this program.  

YOU WILL NEED TO EDIT CONFIGURATION.XML before DOING ANYTHING.

Scenario 1 involves setting newdir and dir in the configuration to your main BeyondTV video folder (you will of course monitor this folder with Boxee or XBMC).  (If you want to wait for autosmartskip or autoshowsqueeze jobs to be processed, then set smartskipwait or showsqueeze wait to True accordingly).  Two additional tags are needed if showsqueeze wait is on.  They are showsqueezedir and showsqueezeformat.  Put in the directory of where your showsqueezed files go to in the showsqueezedir tag and the file format in showsqueeze format tag (IE: avi).

Scenario 2 involves 2 folders, only 1 of which will be monitored by Boxee or XBMC.  First you have your main BeyondTV folder, second you have a folder that recordings are "ShowSqueezed" into (they can simply be copied if you like).  The showsqueezed folder is newdir and dir in the configuration.

Scenario 3 is if you want the recordings to no longer be in BeyondTV (so you are really "archiving" your files to XBMC or Boxee).  It is just like the second way except there is a third folder that is not monitored by BeyondTV. The third folder is the only folder that XBMC or Boxee monitors.  (Note the BTVMetaData.exe process will most likely fail on all of the moved files, but I don't think it'll be a problem).


A typical file name looks as follows: "Maverick (1957)\Maverick.S04E32 (The Devil's Necklace).mpg". If the script detects that your file is a movie, it will appear in the form "Movies\Movie Name.mpg" If the script doesn't detect what your file is, it simply keeps its current filename and throws it in "Other".

Requirements: Php Must be installed and you should make sure that the location of php is located in the "Path" Environmental Variable in Windows (a quick google search should help)

Installation:

Copy the BTVRenamer folder anywhere you like, create a shortcut of launch.bat and copy the shortcut into your startup folder (start->all programs->startup)

Edit configuration.xml with dir as the directory that will be monitored and newdir to where the files will be moved (Do not put a \ at the end of the directories).  If you have setup authentication on the web interface of BeyondTV, please set Authentication as True.  Else put false and leave webuser and webpass as blank.  You can also add a bunch of <ignore> tags to the configuration.xml file to ignore certain shows (put in the showname).  For instance I’m ignoring the show “College Football”, because I know for a fact that my script will name it completely wrong if I don’t.  For the most part, this script works AWESOME wonders though.

You can also edit the variable millisecs in run.bat to change the timing of the renaming process (it will occur this many millisecs after its previous run).

If you want to start the process without having to restart, simply run launch.bat

Manage External Videos Must be turned on via The Web Admin Under Miscellaneous - for BeyondTv to be able to expire renamed files (Requires BTV Restart).

Recurse Subdirectories must also be on for the newdir in BeyondTV (if you are running scenario 1 or 2) (Requires BTV Restart).

Special Thanks: Thank you TVRage.com for your wonderful feed feature for gathering TV information. Thank you Keith Devens for your wonderful xml.php file which made this whole process a lot easier.  And thank you “psionic” from the snapstream forums for your amazing BTVMetaData.exe tool, without which we could not keep BTV data of the renamed files.

Enjoy :-D

