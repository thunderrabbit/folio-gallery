<!--
	folioGallery v1.3 - 2014-05-01
	(c) 2014 Harry Ghazanian - foliopages.com/php-jquery-ajax-photo-gallery-no-database
	This content is released under the http://www.opensource.org/licenses/mit-license.php MIT License.
-->
<?php
/***** gallery settings *****/
// Note the big difference in the two Directory variables
$unixRootDirectory   = '..'; // NO trailing slash.   filesystem root directory of your domain, compared to this file
$urlRootDirectory    = '/'; // location of files in URL (after domain)
$album_page_url      = $_SERVER['PHP_SELF']; // url of page where gallery/albums are located
$no_thumb            = 'foliogallery/noimg.png';  // show this when no thumbnail exists
$extensions          = array("jpg","png","gif","JPG","PNG","GIF"); // allowed extensions in photo gallery
$itemsPerPage        = '12';    // number of images per page if not already specified in ajax mode
$thumb_width         = '150';   // width of thumbnails in pixels
$sort_albums_by_date = FALSE;    // TRUE will sort albums by upload date, FALSE will sort albums by name
$sort_images_by_date = TRUE;    // TRUE will sort thumbs by creation date, FALSE will sort images by name
$random_thumbs       = TRUE;    // TRUE will display random thumbnails, FALSE will display the first image from thumbs folders
$show_captions       = TRUE;    // TRUE will display file names as captions on thumbs inside albums, FALSE will display no captions
$num_captions_chars  = '50';    // number of characters displayed in album and thumb captions
/***** end gallery settings *****/

$numPerPage = (!empty($_REQUEST['numperpage']) ? (int)$_REQUEST['numperpage'] : $itemsPerPage);
$fullAlbum  = (!empty($_REQUEST['fullalbum']) ? 1 : 0);

// function to create thumbnails from images
function make_thumb($folder,$file,$dest,$thumb_width) {

	$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

	switch($ext)
	{
		case "jpg":
		$source_image = imagecreatefromjpeg($folder.'/'.$file);
		break;

		case "jpeg":
		$source_image = imagecreatefromjpeg($folder.'/'.$file);
		break;

		case "png":
		$source_image = imagecreatefrompng($folder.'/'.$file);
		break;

		case "gif":
		$source_image = imagecreatefromgif($folder.'/'.$file);
		break;
	}

	$width = imagesx($source_image);
	$height = imagesy($source_image);

	if($width < $thumb_width) // if original image is smaller don't resize it
	{
		$thumb_width = $width;
		$thumb_height = $height;
	}
	else
	{
		$thumb_height = floor($height*($thumb_width/$width));
	}

	$virtual_image = imagecreatetruecolor($thumb_width,$thumb_height);

	if($ext == "gif" or $ext == "png") // preserve transparency
	{
		imagecolortransparent($virtual_image, imagecolorallocatealpha($virtual_image, 0, 0, 0, 127));
		imagealphablending($virtual_image, false);
		imagesavealpha($virtual_image, true);
    }

	imagecopyresampled($virtual_image,$source_image,0,0,0,0,$thumb_width,$thumb_height,$width,$height);

	switch($ext)
	{
	    case 'jpg': imagejpeg($virtual_image, $dest,80); break;
		case 'jpeg': imagejpeg($virtual_image, $dest,80); break;
		case 'gif': imagegif($virtual_image, $dest); break;
		case 'png': imagepng($virtual_image, $dest); break;
    }

	imagedestroy($virtual_image);
	imagedestroy($source_image);

}

// I name most image files like 2020_june_jump_the_wire_shark.jpg
function make_file_name($filename)
{
   $filename_no_extension = preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename);
   return str_replace('_', ' ', $filename_no_extension);
}

function getNonThumbDirectoriesIn($currentDirectory)
{
  $directoriesInCurrentDirectory = glob($currentDirectory."/*", GLOB_ONLYDIR);
  $basenamesOfArrayOfDirectories = array_map("basename",$directoriesInCurrentDirectory);
  $nonThumbDirectories = array_diff($basenamesOfArrayOfDirectories, array('thumbs'));
  return $nonThumbDirectories;
}

// return array sorted by date or name
function sort_array(&$array,$dir,$sort_by_date) { // array argument must be passed as reference

	if($sort_by_date)
	{
		foreach ($array as $key=>$item)
		{
			$stat_items = stat($dir .'/'. $item);
			$item_time[$key] = $stat_items['ctime'];
		}
		return array_multisort($item_time, SORT_DESC, $array);
	}
	else
	{
		return usort($array, 'strnatcasecmp');
	}

}

// display pagination
function paginate_array($numPages,$urlVars,$alb,$currentPage) {

   $html = '';

   if ($numPages > 1)
   {
       if ($currentPage > 1)
	   {
	   	   $html .=  "<div id='nextPage'>";
	       $prevPage = $currentPage - 1;
	       $html .= ' <a class="pag prev" rel="'.$alb.'" rev="'.$prevPage.'" href="?'.$urlVars.'p='.$prevPage.'">Last Page</a> ';
	   	   $html .=  '</div>';
	   }

	   if ($currentPage != $numPages)
	   {
	   	   $html .=  "<div id='prevPage'>";
           $nextPage = $currentPage + 1;
		   $html .= ' <a class="pag next" rel="'.$alb.'" rev="'.$nextPage.'" href="?'.$urlVars.'p='.$nextPage.'">Next Page</a>';
	   	   $html .=  '</div>';
	   }

	   for( $i=0; $i < $numPages; $i++ )
	   {
           $p = $i + 1;
		   $class = ($p==$currentPage ? 'current-paginate' : 'paginate');
		   $html .= '<a rel="'.$alb.'" rev="'.$p.'" class="'.$class.' pag" href="?'.$urlVars.'p='.$p.'">'.$p.'</a>';
	   }

   }

   return $html;

}
?>

<div class="fg">

<?php
$currentDirectory = $unixRootDirectory;
$currentAlbumDirectory = $urlRootDirectory;
if (!empty($_REQUEST['album'])) // if no album requested, show all albums
{
  // TODO clean up user input $_REQUEST['album']
  $currentDirectory = $unixRootDirectory . "/" . $_REQUEST['album'];
  $currentAlbumDirectory = $_REQUEST['album'];
}

if(1)  // always display directories, even if in a subdirectory.  Keeping { } block so it's easier to encapsulate later
{
  // GLOB_ONLYDIR = load only the directories
  // "basename" removes the parent dirs from the directory path
  // /home/www/album2 ==> album2
	$albums = getNonThumbDirectoriesIn($currentDirectory);
	$numAlbums = count($albums);

	if($numAlbums == 0)    // this should probably only be if we are in the root directory
	{ ?>

		<div class="titlebar"><p>There are currently no albums</p></div>

	<?php
	}
	else
	{
		sort_array($albums,$currentDirectory,$sort_albums_by_date); // rearrange array either by date or name
		$numPages = ceil( $numAlbums / $numPerPage );

		if(isset($_REQUEST['p']))
		 {
		 	$currentPage = ((int)$_REQUEST['p'] > $numPages ? $numPages : (int)$_REQUEST['p']);
         }
		 else
		 {
		 	$currentPage=1;
         }

		$start = ($currentPage * $numPerPage) - $numPerPage; ?>

		<div class="p10-lr">
        	<span class="title">Photo Gallery</span> - <?php echo $numAlbums; ?> albums
        </div>

        <div class="clear"></div>

		<?php
	    for( $i=$start; $i<$start + $numPerPage; $i++ )
		{

			if(isset($albums[$i]))
			{
				$thumb_pool = glob($currentDirectory.'/'.$albums[$i].'/thumbs/*{.'.implode(",", $extensions).'}', GLOB_BRACE);

				if (count($thumb_pool) == 0)
				{
					$album_thumb = $no_thumb;
				}
				else
				{
					$album_thumb = ($random_thumbs ? $thumb_pool[array_rand($thumb_pool)] : $thumb_pool[0]); // display a random thumb or the 1st thumb
				} ?>

				<div class="thumb-wrapper">
					<div class="thumb">
					   <a class="showAlb" rel="<?php echo $albums[$i]; ?>" href="<?php echo $_SERVER['PHP_SELF']; ?>?album=<?php echo urlencode($currentAlbumDirectory . "/" . $albums[$i]); ?>">
					     <img src="<?php echo $album_thumb; ?>" alt="<?php echo $albums[$i]; ?>" />
					   </a>
					</div>
					<div class="caption"><?php echo substr(make_file_name($albums[$i]),0,$num_captions_chars); ?></div>
				</div>

			<?php
			}

		}
		?>

		 <div class="clear"></div>

         <div align="center" class="paginate-wrapper">
        	<?php
			$urlVars = "";
			$alb = "";
            echo paginate_array($numPages,$urlVars,$alb,$currentPage);
			?>
         </div>
    <?php
	}

}

if(1)  // Force the images to always be displayed, especially in the root directory.  I am leaving the { } block so I can encapsulate this later
{

	$album = $unixRootDirectory;   // this works for the root directory

  if(!empty($_REQUEST['album']))
  {
    $album .= '/'.$_REQUEST['album'];     // must not have a trailing slash on album name
  }
  // GLOB_BRACE = expands search to valid image extensions
  // "basename" removes the parent dirs from the directory path
  // /home/www/album2/monkey.jpg ==> album2/monkey.jpg
	$files = array_map("basename",glob($unixRootDirectory.'/'.$_REQUEST['album'].'/*{.'.implode(",", $extensions).'}', GLOB_BRACE));
	$numFiles = count($files); ?>

	<div class="p10-lr">
		<?php if($fullAlbum==1) { ?>
			<span class="title"><a href="<?php echo $album_page_url; ?>" class="refresh">Albums</a></span>
			<span class="title">&raquo;</span>
		<?php } ?>
		<span class="title"><?php echo $_REQUEST['album']; ?></span> - <?php echo $numFiles; ?> images
	</div>

	<div class="clear"></div>

	<?php
	if($numFiles == 0)
	{ ?>

		 <div class="p10-lr"><p>There are no images in this album.</p></div>

	<?php
	}
	else
	{
		sort_array($files,$album,$sort_images_by_date); // rearrange array either by date or name
		$numPages = ceil( $numFiles / $numPerPage );

		if(isset($_REQUEST['p']))
		{
		 	$currentPage = ((int)$_REQUEST['p'] > $numPages ? $numPages : (int)$_REQUEST['p']);
		}
		 else
		{
		 	$currentPage=1;
		}

		$start = ($currentPage * $numPerPage) - $numPerPage;

		if (!is_dir($album.'/thumbs'))
		{
			mkdir($album.'/thumbs');
			chmod($album.'/thumbs', 0777);
			//chown($album.'/thumbs', 'apache');
		}

		for( $i=0; $i <= $numFiles; $i++ )
		{
			if(isset($files[$i]) && is_file($album .'/'. $files[$i]))
			{
				$ext = strtolower(pathinfo($files[$i], PATHINFO_EXTENSION));
				$caption = substr($files[$i], 0, -(strlen($ext)+1));

				if(in_array($ext, $extensions))
				{
					$thumb = $album.'/thumbs/'.$files[$i];

					if($i>=$start && $i<$start + $numPerPage) {

							if (!file_exists($thumb)) {
								make_thumb($album,$files[$i],$thumb,$thumb_width);
							}
 ?>
					<div class="thumb-wrapper">
						<div class="thumb">
							<a href="<?php echo $album; ?>/<?php echo $files[$i]; ?>" title="<?php echo $files[$i]; ?>" class="albumpix">
								<img src="<?php echo $thumb; ?>" alt="<?php echo $files[$i]; ?>" />
							</a>
						</div>
						<?php if($show_captions) { ?><div class="caption"><?php echo substr(make_file_name($caption),0,$num_captions_chars); ?></div><?php } ?>

					<?php
					}

				if($i>=$start && $i<$start + $numPerPage) { ?></div><?php } }

			}

		} ?>

		<div class="clear"></div>

		<div align="center" class="paginate-wrapper">
			<?php
			$urlVars = "album=".urlencode($_REQUEST['album'])."&amp;";
			$alb = $_REQUEST['album'];
			echo paginate_array($numPages,$urlVars,$alb,$currentPage);
			?>
		</div>

	<?php
	} // end if numFiles not 0

}
?>
</div>
