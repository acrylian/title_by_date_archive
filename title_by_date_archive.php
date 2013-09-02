<?php
/**
 * Simple plugin that provides a function to print an archive by title and month for albums and news articles. jQuery required for foldin/foldout.
 *
 * Usage:
 * Add these calls to a theme page, e.g. a static custom theme page like archive.php of standard Zenphoto themes.
 * <?php printTitleByDateArchive('albums','asc'); ?>
 * <?php printTitleByDateArchive('news','asc'); ?>
 * Both print a nested html list (<ul>)
 * 
 * @author Malte Müller (acrylian) 
 * @package plugins
 * @subpackage misc
 */

$plugin_description = gettext('Simple plugin that provides a function to print an archive by title and month for albums and news articles. jQuery required for foldin/foldout.');
$plugin_author = 'Malte Müller (acrylian)';
$plugin_version = '1.0';
$plugin_url = '';
$option_interface = 'title_by_date_archive_options';

class title_by_date_archive_options {

	/**
	 * class instantiation function
	 *
	 * @return admin_login
	 */
	function __construct() {
		setOptionDefault('title_by_date_archive_shownewsauthor', 0);
		setOptionDefault('title_by_date_archive_showalbumowner', 0);
	}

	/**
	 * Reports the supported options
	 *
	 * @return array
	 */
	function getOptionsSupported() {
	 $options = array(
	 	 gettext_pl('Show author','title_by_date_archive') => array(
			 	'key' => 'title_by_date_archive_shownewsauthor',
			 	'type' => OPTION_TYPE_CHECKBOX,
				'order' => 2,
				'desc' => gettext_pl('Check to list the author of the news article','title_by_date_archive')),
			gettext_pl('Show owner','title_by_date_archive') => array(
			 	'key' => 'title_by_date_archive_showalbumowner',
			 	'type' => OPTION_TYPE_CHECKBOX,
				'order' => 2,
				'desc' => gettext_pl('Check to list the owner of the album','title_by_date_archive'))
	 );
	 return $options;
	}
	
}
/**
 * Retrieves the album titles by month and year (NOTE: Only published and viewable albums even if logged in!).
 *
 * @param string $type 'albums' or 'news'
 * @param string $order 'desc' or 'asc' for descending or ascending order
 * @return array
 */
function printTitleByDateArchive($type='albums',$order='asc') {
	global $_zp_gallery, $_zp_zenpage;

	if($type == 'news' && !getOption('zp_plugin_zenpage')) {
		echo '<p><strong>The Zenpage CMS plugin is required for this and not enabled!</strong></p>';
		break;
	}
	$alldates = array();
	$cleandates = array();
	$result = array();
	switch($type) {
		case 'albums':
			$sql = "SELECT `date` FROM ". prefix('albums')." WHERE `show` = 1";
			$hidealbums = getNotViewableAlbums();
			if (!is_null($hidealbums)) {
				foreach ($hidealbums as $id) {
					$sql .= ' AND `id` !='.$id.'';
				}
				//$sql = substr($sql, 0, -5);
			} 
			$result = query_full_array($sql);
			foreach($result as $row){
				$alldates[] = $row['date'];
			}
	 		break;
	case 'news':
		if(!class_exists('Zenpage')) {
			echo '<p>'.gettext('Error: The Zenpage CMS plugin is required for this option').'</p>';
			break;
		} else {
			$result = $_zp_zenpage->getAllArticleDates();
			// switch key to value, array_flip() clears duplicates so not suitable!
			foreach($result as $key=>$value) {
				$alldates[] = $key;
			}
		}
		break;
	}
	//echo "<pre>"; print_r($alldates); echo "</pre>";
	foreach ($alldates as $adate) {
		if (!empty($adate)) {
			$months[] = substr($adate, 0, 7);
		}
	}
	$months = array_unique($months);
	if ($order == 'desc') {
		arsort($months);
	} else {
		asort($months);
	}
	//echo "<pre>"; print_r($months); echo "</pre>";
	foreach($months as $date) {
		$years[] = substr($date, 0, 4);
	}
	$years = array_unique($years);
	//echo "<pre>"; print_r($years); echo "</pre>";
	$count = '';
	foreach($years as $year) {
		$count++;
		$currentyear = ' notcurrent';
		if($count == 1) {
			$currentyear = ' currentyear';
		}
		$typeclass = ' '.$type;
		?>
		 <script type="text/javascript">
			// <!-- <![CDATA[
			$(function() {
				$("ul.news li.notcurrent ul#<?php echo $type; ?>month<?php echo $year; ?>").toggle();   
				$("ul.albums li.notcurrent ul#<?php echo $type; ?>month<?php echo $year; ?>").toggle();   
			 	$("ul.news a.openyear<?php echo $type.$year; ?>").click(function() {
					$("ul#newsmonth<?php echo $year; ?>").toggle();
				}); 
				$("ul.albums a.openyear<?php echo $type.$year; ?>").click(function() {
					$("ul#albumsmonth<?php echo $year; ?>").toggle();
				}); 
			}); 
		// ]]> -->
	 </script>
	 <?php
		echo '<ul class="archive'.$typeclass.'"><li class="year'.$currentyear.'"><a class="openyear'.$type.$year.'" href="#">'.$year.'</a>'."\n";
		foreach($months as $month) {
			$result2 = '';
			$monthonly = substr($month, 5, 7);
			if(substr($month, 0, 4) == $year) {
				$monthname = strftime('%Y-%B', strtotime($month));
				$monthname = substr($monthname, 5);
				echo '<ul id="'.$type.'month'.$year.'" class="'.$type.'month"><li>'.$monthname."\n";
				//echo substr($month, 0, 4)."/".$year."<br />";
				// get the items by year and month
				switch($type) {
					case 'albums':
						$sql = "SELECT `folder`, `date` FROM ". prefix('albums')." WHERE `show` = 1 AND `date` LIKE '".$month."%'";
						if (!is_null($hidealbums)) {
							foreach ($hidealbums as $id) {
								$sql .=  ' AND `id` != '.$id;
							}
						}
						$result = query($sql);
						$hint = $show = NULL;
						if($result) {
							while ($item = db_fetch_assoc($result)) {
								$obj = new Album(null,$item['folder']);
								if ($obj->checkAccess($hint, $show)) {
									$result2[] = $obj;
								}
							}
						db_free_result($result);
						}
						break;
					case 'news':
						$sql = "SELECT `titlelink`, `date` FROM ". prefix('news')." WHERE `show` = 1 AND `date` LIKE '".$month."%'";
						$result = query($sql);
						if($result) {
							while ($item = db_fetch_assoc($result)) {
								$obj = new ZenpageNews($item['titlelink']);
								if ($obj->categoryIsVisible()) {
									$result2[] = $obj;
								}
							}
							db_free_result($result);
						}
						break;
				}
			} 
			if($result2) {
				echo '<ul class="entries">'."\n";
				$entrycount = count($result2);
				$count = '';
				foreach($result2 as $entry) {
					$count++;
					$comma = '';
					//if($count != $entrycount) {
					//	$comma = ', ';
					//}
					$authorlink = '';
					$authorfull = NULL;
					switch($type) {
						case 'albums':
							$title = $entry->getTitle();
							$date = zpFormattedDate(DATE_FORMAT, strtotime($entry->getDateTime()));
							$link = $entry->getAlbumLink(); 
							if(getOption('title_by_date_archive_showalbumowner')) {
								$author = $entry->getOwner();
								$authorfull = getTitleByDateAuthorFullname($author);
							}
							$category = ''; 
							break;
						case 'news':
							// news are already objects here!
							$title = $entry->getTitle();
							$date = zpFormattedDate(DATE_FORMAT, strtotime($entry->getDateTime()));
							$link = getNewsURL($entry->getTitleLink());
							if(getOption('title_by_date_archive_shownewsauthor')) {
								$author = $entry->getAuthor();
								$authorfull = getTitleByDateAuthorFullname($author);
							} 
							$category = ' – '.getTitleByDateArticleCategories($entry);
							break;
					}
					if(!empty($authorfull)) {
						$authorlink = ' – '.html_encode($authorfull);
					} 
					echo '<li><a href="'.html_encode($link).'">'.html_encode($title).'</a> <em>('.$date.')</em> <span class="author">'.$category.$authorlink.$comma.'</span></li>'."\n";
				} 
				echo '</ul></li></ul>';
			} 
		}
		echo '</li></ul>';
	}
	//echo "<pre>"; print_r($result2); echo "</pre>";
}


/**
 * Helper function: gets the categories for an article with comma separator
 *
 * @param obj $obj news article object
 */
function getTitleByDateArticleCategories($obj) {
	$category = '';
	$separator = ', ';
	$categories = $obj->getCategories();
	$catcount = count($categories);
	if($catcount != 0) {
		$count = 0;
		foreach($categories as $cat) {
			$count++;
			$catobj = new ZenpageCategory($cat['titlelink']);
			if($count >= $catcount) {
				$separator = '';
			}
			$category .= $catobj->getTitle().html_encode($separator);
		}
	} else {
		$category = gettext('News');
	}
	return $category;
}

/**
 * gets the article's or page's author's or album owner's full name
 *
 * @param string $author the author or owner
 */
function getTitleByDateAuthorFullname($author) {
	global $_zp_authority;
	if(empty($author)) return NULL;
	$authorfull = $author;
	$admin = $_zp_authority->getAnAdmin(array('`user`=' => $author, '`valid`=' => 1));
	if (is_object($admin)) {
		$authorfull = $admin->getName();
		if(empty($authorfull)) {
			$authorfull = $author;
		}
	}
	return $authorfull;
}
?>