<html>
<head><title>Sitemap Generator</title></head>
<body>
<?php
	error_reporting(E_ALL ^ (E_NOTICE | E_WARNING));
	
	// base URL
	$base_url="http://www.baseurl.com/";
	// lower priority of URLs containing these substrings
	$low_priority="/old/";
	// blacklist (i.e., avoid recursing over) URLs containing these substrings
	$filter=array('/administration','/img','.png','.jpg','javascript','.css','.js','.wmv','.avi','.flv','mailto:');
	
	// add the http(s):// prefix to the base URL if not present
	if(substr($base_url,0,7)!="http://" && substr($base_url,0,8)!="https://" )
	{
		$base_url="http://".$base_url;
	}

	// extract the domain name, discarding all the folders and filenames in the URL
	if(strpos($base_url,"/",8)===FALSE)
		$site_name=$base_url;
	else
		$site_name=substr($base_url,0,strpos($base_url,"/",8));
	
	$aVisit[]=$base_url;
	$aVisitPriority[]=0.9;
	$aMap[]=$base_url;
	$aPriority[]=0.9;
	$iVisited=0;
	while(count($aVisit)!=$iVisited)
	{
		// get the next page
		$content=file_get_contents($aVisit[$iVisited]);
		echo $aVisit[$iVisited]."<br>";
		ob_flush();
		flush();
		
		if($content===FALSE) // the page is not accessible, remove it from the sitemap
		{
			echo "->".$aVisit[$iVisited]." is not a valid page!<br>";
			
			$pos=array_search($aVisit[$iVisited],$aMap);
			for($i=$pos;$i<count($aMap)-1;$i++)
				$aMap[$i]=$aMap[$i+1];
			array_pop($aMap);
			
			for($i=$pos;$i<count($aPriority)-1;$i++)
				$aPriority[$i]=$aPriority[$i+1];
			array_pop($aPriority);
		}
		
		// analyze the page
		while($content!==FALSE && stripos($content,"<a ")!==FALSE) // while there are still links not yet analyzed
		{
			if( !(
					// the first link is inside a <script> block
						stripos($content,"<script") !== FALSE &&
						stripos($content,"<script") < stripos($content,"<a ") &&
						stripos($content,"</script>") > stripos($content,"<a ") ||
					// $content beginning is inside a <script> block, no other <script> block is present afterwards
						stripos($content,"</script>") !== FALSE &&
						stripos($content,"<script") === FALSE ||
					// $content beginning is inside a <script> block, another <script> block is present afterwards
						stripos($content,"</script>") !== FALSE &&
						stripos($content,"<script") !== FALSE &&
						stripos($content,"<script") > stripos($content,"</script>")
				)) // i.e., we are outside a <script> block and the first link is not inside a <script> block
			{
				
				// find the link and get the URL
				$content=stristr($content,"<a ");
				$content=stristr($content,"href=");
				
				// extract the link URL
				if(stripos($content,"\"")===5) // is the URL between double quotes?
				{
					$link=substr($content,6);
					$link=substr($link,0,stripos($link,"\"")); // URL ends at double quote
				}
				else
				{
					$link=substr($content,5);
					$link=substr($link,0,stripos($link," ")); // URL ends at white space
				}
				
				// put the URL in absolute format
				if(substr($link,0,1)=="/") // URL relative to the root
				{
					$link=$site_name.$link;
				}
				elseif(substr($link,0,7)=="http://" || substr($link,0,8)=="https://") // fully absolute URL (includes protocol)
				{} // do nothing
				elseif(substr($link,0,7)=="file://") // URL using the file: protocol
				{} // do nothing
				elseif(substr($link,0,4)=="www.") // fully absolute URL (without protocol)
				{
					$link="http://".$link;
				}
				elseif(substr($link,0,3)=="../") // relative URL pointing to parent
				{
					$parent=$aVisit[$iVisited];
					while(substr($link,0,3)=="../")
					{
						$parent=substr($aVisit[$iVisited],0,strripos($aVisit[$iVisited],'/'));
						$link=substr($link,3);
					}
					$link=$parent."/".$link;
				}
				else // relative URL
				{
					$parent=substr($aVisit[$iVisited],0,strripos($aVisit[$iVisited],'/')+1);
					$link=$parent.$link;
				}
				
				// check if the URL is in the blacklist
				$filtered=false;
				for($i=0;$i<count($filter) && !$filtered;$i++)
				{
					if(stripos($link,$filter[$i])!==FALSE)
					{
						$filtered=true;
					}
				}
				
				if(substr($link,0,strlen($site_name))==$site_name // the URL points to a page of this domain
					&& !in_array($link,$aMap) // was not visited yet
					&& !$filtered // is not in the blacklist
				)
				{
					if(stripos($link,"#")===FALSE) // the link does not include an anchor
					{
						// put the URL in the sitemap
						$aMap[]=$link;
						$aPriority[]=$aVisitPriority[$iVisited]-0.1;
						if(stripos($link,$low_priority)!==FALSE && $aPriority[count($aPriority)-1]>0.5)
						{
							$aPriority[count($aPriority)-1]=0.5;
						}
					}
					
					if(strpos($link,".html")!==FALSE || strpos($link,".htm")!==FALSE ||
						strpos($link,".php")!==FALSE || strpos($link,".asp")!==FALSE ||
						strpos($link,".cgi")!==FALSE) // the URL points to a file with a known webpage extension
					{
						// put the URL in the lst of pages to visit
						$aVisit[]=$link;
						$aVisitPriority[]=$aVisitPriority[$iVisited]-0.1;
						if(stripos($link,$low_priority)!==FALSE && $aVisitPriority[count($aVisitPriority)-1]>0.5)
						{
							$aVisitPriority[count($aVisitPriority)-1]=0.5;
						}
					}
				}
			}
			else // jump to the end of the <script> block
			{
				$content=substr(stristr($content,"</script>"),9);
			}
		}
		$iVisited++;
	}
	
	echo "-------------------------------------------------<br>";
	$filename="../sitemap.xml";
	file_put_contents($filename,"<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<urlset xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd\" xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n");
	$today=date('Y-m-d');
	while(count($aMap)!=0)
	{
		echo $aMap[0]." - ".$aPriority[0]."<br>";
		$string="<url>\n    <loc>".array_shift($aMap)."</loc>\n    <lastmod>$today</lastmod>\n    <priority>".array_shift($aPriority)."</priority>\n</url>\n";
		file_put_contents($filename,$string,FILE_APPEND);
	}
	file_put_contents($filename,"</urlset>\n",FILE_APPEND);
	
	echo "Completed";
?>
</body>
</html>
