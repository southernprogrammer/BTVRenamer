<?php

	include('xml.php');
	$log = "Log.txt";
	$fh = fopen($log, 'a') or die("can't open file");
	$config_file = XML_unserialize(file_get_contents("configuration.xml"));
	
	$dir = $config_file['config']['dir'];
	$newdir = $config_file['config']['newdir'];
	$server = $config_file['config']['server'];
	$authentication = strtolower($config_file['config']['authentication']) == "true";
	$webuser = $config_file['config']['webuser'];
	$webpass = $config_file['config']['webpass'];
	$showsqueezewait = strtolower($config_file['config']['showsqueezewait']) == "true";
	$smartskipwait = strtolower($config_file['config']['smartskipwait']) == "true";
	$showsqueezedir = "";
	$showsqueezeformat = "";
	if(isset($config_file['config']['showsqueezedir']))
		$showsqueezedir = $config_file['config']['showsqueezedir'];
	if(isset($config_file['config']['showsqueezeformat']))
		$showsqueezeformat = $config_file['config']['showsqueezeformat'];
	$ignore_list = array();
	if(isset($config_file['config']['ignore']) && is_array($config_file['config']['ignore']))
		$ignore_list = $config_file['config']['ignore'];
	else if(isset($config_file['config']['ignore']))
		$ignore_list[0] = $config_file['config']['ignore'];
	
	//the reading of files from OLDDIR is non-recursive
	$dh = opendir($dir);
	while(($file = readdir($dh)) !== false) {
		//main loop for file names
		//for each file we need the showname and the episode name
		if(valid_file($file, $dir, $smartskipwait, $showsqueezewait, $showsqueezedir, $showsqueezeformat))
		{
			//we will need the file extension later on
			$ext = substr($file, strripos($file, '.'), strlen($file) - strripos($file, '.'));
			
		    if($authentication)
				$com = "BTVMetaData.exe --server=$server --user=$webuser --password=$webpass --mode=extract \"" . $dir . '\\' . $file . "\"";
			else
				$com = "BTVMetaData.exe --server=$server --mode=extract \"" . $dir . '\\' . $file . "\"";
			
			print exec($com);
			$xmlname = $dir . '\\' . StripExtension(strtolower($file)) . '.xml';
			$episode_data = XML_unserialize(file_get_contents($xmlname));
			//we should now have beyondtv's metadata
			$originalairdate = "";
			$fullnameindex = 0;
			$folderindex = 0;
			$showname = "";
			$episodetitle = "";
			$count = 0;
			
			$keys = array_keys($episode_data['episode-properties']['property']);
			foreach($keys as $key)
			{
				
				if(EndsWith($key, 'attr'))
				{
					if($episode_data['episode-properties']['property'][$key]['name'] == 'OriginalAirDate')
					{
						$originalairdate = $episode_data['episode-properties']['property'][$count / 2];
						$originalairdate = substr($originalairdate, 0, 4) . '-' . substr($originalairdate, 4, 2) . '-' . substr($originalairdate, 6, 2);
					}
					
					if($episode_data['episode-properties']['property'][$key]['name'] == 'FullName')
					{
						$fullnameindex = $count / 2;
					}
					
					else if($episode_data['episode-properties']['property'][$key]['name'] == 'Folder')
					{
						$folderindex = $count / 2;
					}
					
					else if($episode_data['episode-properties']['property'][$key]['name'] == 'Title')
					{
						$showname = $episode_data['episode-properties']['property'][$count / 2];
					}
					
					else if($episode_data['episode-properties']['property'][$key]['name'] == 'EpisodeTitle')
					{
						$episodetitle = $episode_data['episode-properties']['property'][$count / 2];
					}
				}
				$count++;
			}

			//now we have the information needed for a lookup
			$episodeInfo = getEpisodeInfo($showname, $originalairdate, $episodetitle, $ignore_list);
			
			//now we can do some awesome renaming of files!
			if($episodeInfo !== false) // if we got results, rename the file
			{
				//$episodetitle came from beyondtv
				//$episodeInfo['title'] came from tvrage.com
				
				$eptitleformat = $episodetitle;
				//if the title is empty try the other data we got
				if(empty($eptitleformat))
					$eptitleformat = $episodeInfo['title'];
				//now if that info is not empty we can add parenthesis around the title
				if(!empty($eptitleformat))
					$eptitleformat = ' (' . replace_illegal_chars($eptitleformat) . ')';
				$showname = replace_illegal_chars($showname); //cannot have illegal chars
															  //in a file name
				$mkdir = $newdir . '\\' . $showname . ' (' . $episodeInfo['started'] . ')\\'; 
				if(!file_exists($mkdir))
					mkdir($mkdir);
				$newloc = "$showname.S" . leading_zeros($episodeInfo['season'], 2);
				$newloc = $newloc . 'E' . $episodeInfo['episode'];
				$newloc = $newloc . $eptitleformat;
				//rename & move the old file (warnings will be thrown if it's not there)
				
				//if there are multiple files with the same title
				if(file_exists($mkdir . $newloc . $ext))
				{
					$count = 2;
					while(file_exists($mkdir . $newloc . '-' . $count . $ext))
						$count++;
					$newloc = $newloc . '-' . $count;
				}

				$readonly = is_readonly($dir . '\\' . $file);
				print $dir . '\\' . $file . ' is readonly ? ' . $readonly;
				rename($dir . '\\' . $file, $mkdir . $newloc . $ext);
				//rename seg
				
				//rename & move the xmlfile that we must later edit and import
				rename($xmlname, $mkdir . strtolower($newloc) . '.xml');
				//rename & move its associated smartchapters file
				rename($dir . '\\' . $file . '.chapters.xml', $mkdir . $newloc . $ext . '.chapters.xml');
				//move some dat files that beyondtv creates
				
				//rename($dir . '\\' . $file . '.index.dat', $mkdir . $newloc . $ext . '.index.dat');
				//rename($dir . '\\' . $file . '.timeindex.dat', $mkdir . $newloc . $ext . '.timeindex.dat');
				
				//instead of renaming the .dat files, let's delete them, because apparently boxee picks up .dat
				//files
				
				unlink($dir . '\\' . $file . '.index.dat');
				unlink($dir . '\\' . $file . '.timeindex.dat');
				
				//now modify the appropriate metadata information
				$episode_data['episode-properties']['property'][$fullnameindex] = $mkdir . $newloc . $ext;
				$episode_data['episode-properties']['property'][$folderindex] = $newdir; 
				//now write back the metadata information to the xml file
				$newxml = XML_serialize($episode_data);
				$xmlfh = fopen($mkdir . strtolower($newloc) . '.xml', 'w');
				fwrite($xmlfh, $newxml);
				fclose($xmlfh);
				//now execute the command to import the metadata into beyondtv
				//---------------------------------------------
				if($authentication)
					$com = "BTVMetaData.exe --server=$server --user=$webuser --password=$webpass --mode=import \"" . $mkdir . $newloc . $ext . "\"";
				else
					$com = "BTVMetaData.exe --server=$server --mode=import \"" . $mkdir . $newloc . $ext . "\"";
				
				print exec($com);

				//---------------------------------------------
				//now delete the metadata file
				unlink($mkdir . strtolower($newloc) . '.xml');
				sleep(5);
				if($readonly)
					set_readonly($mkdir . $newloc . $ext);
				else
					set_not_readonly($mkdir . $newloc . $ext);
				
			}
			else //move the file to Movies or Other accordingly, by doing so they will not be scanned again
			{
				//This is the case it should go to the movies folder
				if($showname != 'Movies')
				{
					$time = date("m-d-Y g:i:s a");
					fwrite($fh, "$time - $file could not Be found on TVRage.com and was moved to the other folder instead\r\n\r\n");
				
					$otherloc = $newdir . '\\Other';
					if(!file_exists($otherloc))
						mkdir($otherloc);
					//move the file
					$newloc = $otherloc . '\\' . $file;
					
					//if there are multiple files with the same title
					$count = 1;
					while(file_exists($newloc))
						$count++;
					if($count > 1)
					{
						$newloc = StripExtension($newloc) . '-' . $count . $ext;
						$file = StripExtension(strtolower($file)) . '-' . $count . $ext;
					}
					
					$readonly = is_readonly($dir . '\\' . $file);
					rename($dir . '\\' . $file, $newloc);
					print $dir . '\\' . $file . ' is readonly ? ' . $readonly;
					//rename seg

					//rename & move the xmlfile that we must later edit and import
					rename($xmlname, $otherloc . '\\' . StripExtension(strtolower($file)) . '.xml');
					//move the smartchapters file
					rename($dir . '\\' . $file . '.chapters.xml', $otherloc . '\\' . $file . '.chapters.xml');
					//move some dat files that beyondtv creates
					
					//rename($dir . '\\' . $file . '.index.dat', $otherloc . '\\' . $file . '.index.dat');
					//rename($dir . '\\' . $file . '.timeindex.dat', $otherloc . '\\' . $file . '.timeindex.dat');
					
					//instead of renaming them, let's delete them because boxee actually picks up .dat files
					
					unlink($dir . '\\' . $file . '.index.dat');
					unlink($dir . '\\' . $file . '.timeindex.dat');
					
					//now modify the appropriate metadata information
					$episode_data['episode-properties']['property'][$fullnameindex] = $newloc;
					$episode_data['episode-properties']['property'][$folderindex] = $newdir; 
					//now write back the metadata information to the xml file
					$newxml = XML_serialize($episode_data);
					$xmlfh = fopen($otherloc . '\\' . StripExtension(strtolower($file)) . '.xml', 'w');
					fwrite($xmlfh, $newxml);
					fclose($xmlfh);
					//now execute the command to import the metadata into beyondtv
					//---------------------------------------------
					if($authentication)
						$com = "BTVMetaData.exe --server=$server --user=$webuser --password=$webpass --mode=import \"" . $newloc . "\"";
					else
						$com = "BTVMetaData.exe --server=$server --mode=import \"" . $newloc . "\"";
					
					print exec($com);

					//---------------------------------------------
					
					//remove the xml file with the metadata that we created
					unlink($otherloc . '\\' . StripExtension(strtolower($file)) . '.xml');
					//the xml data should be automatically transferred via beyondtv's internal process
					sleep(5);
					if($readonly)
						set_readonly($newloc);
					else
						set_not_readonly($newloc);
				}
				else // it is going to be moved into the movies folder
				{
					$moviesloc = $newdir . '\\Movies';
					mkdir($moviesloc);
					//we'll even take out the date string at the end, just for fun					
					$newfile = preg_replace('/-\d\d\d\d-\d\d-\d\d-\d/', '', $file);
					$newloc = $moviesloc . '\\' . $newfile;
					
					//if there are multiple files with the same title
					$count = 1;
					while(file_exists($newloc))
						$count++;
					if($count > 1)
					{
						$newloc = StripExtension($newloc) . '-' . $count . $ext;
						$newfile = StripExtension(strtolower($file)) . '-' . $count . $ext;	
					}
					
					$readonly = is_readonly($dir . '\\' . $file);
					rename($dir . '\\' . $file, $newloc);
					print $dir . '\\' . $file . ' is readonly ? ' . $readonly;
					//rename seg
				
					//rename & move the xmlfile that we must later edit and import
					rename($xmlname, $moviesloc . '\\' . StripExtension(strtolower($newfile)) . '.xml');
					//move the smartchapters file
					rename($dir . '\\' . $file . '.chapters.xml', $moviesloc . '\\' . $newfile . '.chapters.xml');
					//move some dat files that beyondtv creates
					//rename($dir . '\\' . $file . '.index.dat', $moviesloc . '\\' . $newfile . '.index.dat');
					//rename($dir . '\\' . $file . '.timeindex.dat', $moviesloc . '\\' . $newfile . '.timeindex.dat');
 
					//instead of renaming the .dat files, let's delete them because boxee recognizes .dat files
					
					unlink($dir . '\\' . $file . '.index.dat');
					unlink($dir . '\\' . $file . '.timeindex.dat');
					
					//now modify the appropriate metadata information
					$episode_data['episode-properties']['property'][$fullnameindex] = $newloc;
					$episode_data['episode-properties']['property'][$folderindex] = $newdir; 
					//now write back the metadata information to the xml file
					$newxml = XML_serialize($episode_data);
					$xmlfh = fopen($moviesloc . '\\' . StripExtension(strtolower($newfile)) . '.xml', 'w');
					fwrite($xmlfh, $newxml);
					fclose($xmlfh);
					//now execute the command to import the metadata into beyondtv
					//---------------------------------------------
					if($authentication)
						$com = "BTVMetaData.exe --server=$server --user=$webuser --password=$webpass --mode=import \"" . $newloc . "\"";
					else
						$com = "BTVMetaData.exe --server=$server --mode=import \"" . $newloc . "\"";
					
					print exec($com);

					//---------------------------------------------
					
					//remove the xml file with the metadata that we created
					unlink($moviesloc . '\\' . StripExtension(strtolower($newfile)) . '.xml');
					//the xml data should be automatically transferred via beyondtv's internal process
					sleep(5);
					if($readonly)
						set_readonly($newloc);
					else
						set_not_readonly($newloc);	
				}
			}
		}
	}
	
	fclose($fh);
	closedir($dh);

	
	function getEpisodeInfo($showname, $originalairdate, $episodetitle, $ignore_list) 
	{
		//if the Showname is 'Movies', there is no need to do a lookup
		//or if the showname isn't suppose to be looked up, no need to lookup
		//or if it is just some recording we did that has no data
		if($showname == 'Movies' || in_array($showname, $ignore_list) || preg_match('/Recording.*$/', $showname))
			return false;
		//if an episodetitle begins with A or The remove it for better regex matching
		//we only do the episode title search if there is no airdate information, or it wasn't
		//found with the airdate
		if($ans = preg_match('/A .*$/', $episodetitle))
			$episodetitle = substr($episodetitle, 2, strlen($episodetitle) - 2);
		if(preg_match('/The .*$/', $episodetitle))
			$episodetitle = substr($episodetitle, 4, strlen($episodetitle) - 4);
			
		$episodeinfo = false;
		$showxml = file_get_contents("http://www.tvrage.com/feeds/search.php?show=" . urlencode($showname));
		if($showxml === false) //we should at least get an empty xml file
			die('Died on Show XML');
		$possible_shows_xml = XML_unserialize($showxml);
		
		if($possible_shows_xml['Results'] == 0) //if there are no results	
			return false;

		//if it only returned 1 show
		//put shows in the correct array structure
		if(!in_array('0', array_keys($possible_shows_xml['Results']['show'])))
			$shows[0] = $possible_shows_xml['Results']['show']; 
		else
			$shows = $possible_shows_xml['Results']['show'];

		foreach($shows as $show) //for each of the found shows do an exhausive search for the air-date
		{
			//if what we got for showname doesn't exist within the text of the showtitle
			//we don't want any results
			if(!strstr(strtolower($show['name']), strtolower($showname)))
				break;
			$showid = $show['showid'];
			$episode_xml = file_get_contents("http://www.tvrage.com/feeds/episode_list.php?sid=" . $showid);
			if($episode_xml === false) //we should at least get something
				die('Died on Epixode XML');
			$episodes_xml = XML_unserialize($episode_xml);
			//if there is only 1 season, we have to modify the structure a little
			if(isset($episodes_xml['Show']['Episodelist']['Season']['episode']))
			{
				$newarr[0] = $episodes_xml['Show']['Episodelist']['Season'];
				$episodes_xml['Show']['Episodelist']['Season'] = $newarr;
			}
			$seasonnum = 1; //if there is only 1 season, 'no' will not be found, not sure why
			foreach($episodes_xml['Show']['Episodelist']['Season'] as $season)
			{
				//get the season number or do the search
				if(in_array('no', array_keys($season)))
				{
					$seasonnum = $season['no'];
				}
				else //else we are looking at the actual season
				{
					if(!is_array($season['episode']))
						$season['episode'][0] = $season['episode']; //in the case of 1 episode, make it an array
					foreach($season['episode'] as $episode)
					{
						if($episode['airdate'] == $originalairdate)
						{
							$episodeinfo['season'] = $seasonnum;
							$episodeinfo['episode'] = $episode['seasonnum'];
							$episodeinfo['title'] = $episode['title'];
							$episodeinfo['started'] = $show['started'];
							return $episodeinfo;
						}
						
						//if the episodetitle that I passed into the function (and maybe took the words 
						//"A" and "The" out of the front exists within the episode title that I found
						if(is_episode($episodetitle, $episode['title']))
						{
							$episodeinfo['season'] = $seasonnum;
							$episodeinfo['episode'] = $episode['seasonnum'];
							$episodeinfo['title'] = $episode['title'];
							$episodeinfo['started'] = $show['started'];
							//we don't return episode info, because we want to search airdates first
							//and return based on that first, if that fails we will return
							//any information that was logged here
						}
					}
				}
			}
		}
		return $episodeinfo; //if nothing was found, it is false
	}

	function EndsWith($FullStr, $EndStr)
	{
		// Get the length of the end string
		$StrLen = strlen($EndStr);
		// Look at the end of FullStr for the substring the size of EndStr
		$FullStrEnd = substr($FullStr, strlen($FullStr) - $StrLen);
		// If it matches, it does end with EndStr
		
		return $FullStrEnd == $EndStr;
    }
	
	function StripExtension($file)
	{
		return substr($file, 0, strripos($file, '.'));
	}
	
	function leading_zeros($value, $places){
		if(is_numeric($value)){
			$leading = '';
			for($x = 1; $x <= $places; $x++){
				$ceiling = pow(10, $x);
				if($value < $ceiling){
					$zeros = $places - $x;
					for($y = 1; $y <= $zeros; $y++){
						$leading .= "0";
					}
				$x = $places + 1;
				}
			}
			$output = $leading . $value;
		}
		else{
			$output = $value;
		}
		return $output;
	}
	
	function replace_illegal_chars($thestring)
	{
		$find_arr = array(':', '"', '?', '<', '>', '|', '\\', '/');
		$replace_arr = array('_', '_', '_', '_', '_', '_', '_', '_');
		$thestring = str_replace($find_arr, $replace_arr, $thestring);
		return $thestring;
	}
	
	function file_hidden($full_loc, $file)
	{
		$outarr = array();
		exec("dir \"$full_loc\" /a:h", $outarr);
		return isset($outarr[5]); //if the fifth line of output exists, it's hidden
	}
	
	function is_episode($btvtitle, $tvragetitle)
	{
		//if the title exists within the tvragetitle
		if(preg_match('/^.*' . strtolower($btvtitle) . '.*$/', strtolower($tvragetitle)))
			return true;
		
		//if all the words in the title exist in the tvragetitle/////////////
		$find_arr = array(':', ';', '-');
		$replace_arr = array(' ', ' ', ' ');
		$exploded = explode(' ', str_replace($find_arr, $replace_arr, $btvtitle));
		$count = 0;
		foreach($exploded as $ex)
		{
			if(preg_match('/^.*' . strtolower($ex) . '.*$/', strtolower($tvragetitle)))
			{
				$count++;
			}
		}
		if($count == count($exploded))
			return true;
		/////////////////////////////////////////////////////////////////////	
		return false;
	}
	
	function valid_file($file, $dir, $smartskipwait, $showsqueezewait, $showsqueezedir, $showsqueezeformat)
	{
		$smartskipvalid = False;
		if($smartskipwait == False)
			$smartskipvalid = True;	
		else 
		{	
			if(file_exists($dir . '\\' . $file . '.chapters.xml'))
				$smartskipvalid = True;
		}			
		$showsqueezevalid = False;
		if($showsqueezewait == False)
			$showsqueezevalid = True;
		else 
		{	
			if(file_exists($showsqueezedir . '\\' . StripExtension($file) . '.' . $showsqueezeformat))
				$showsqueezevalid = True;
			if(file_exists($showsqueezedir . '\\' . StripExtension($file) . '.' . $showsqueezeformat . '.chapters.xml'))
				$smartskipvalid = True;
		}

		if($file != '.' && $file != '..' && !EndsWith($file, '.xml') && !is_dir($dir . '\\' . $file) && !EndsWith($file, '.ini') && !EndsWith($file, '.db') && !EndsWith($file, '.dat') && !file_hidden($dir . '\\' . $file, $file) && $showsqueezevalid && $smartskipvalid)
			return true;
		else
			return false;
	
	}
	
	function is_readonly($file)
	{
		$arr = explode(" ", exec("ATTRIB \"$file\""));
		foreach($arr as $a)
			if($a == 'R')
				return true;
		return false;
	}
	
	function set_readonly($file)
	{
		exec("ATTRIB +R \"$file\"");
		print "ATTRIB +R \"$file\"";
	}
	
	function set_not_readonly($file)
	{
		exec("ATTRIB -R \"$file\"");
		print "ATTRIB -R \"$file\"";
	}
	
?>