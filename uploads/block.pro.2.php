<?php
/*=====================================================
BlockPro 2 - Модуль для вывода блоков с новостями на страницах сайта DLE (тестировался на 9.5)
=====================================================
Автор: ПафНутиЙ 
URL: http://pafnuty.name/
ICQ: 817233 
email: p13mm@yandex.ru
=====================================================
Файл:  block.pro.2.php
------------------------------------------------------
Версия: 2.6 (11.05.2012)
=====================================================*/

if(!defined('DATALIFEENGINE')){die("Мааамин ёжик, двиг скукожился!!!");}

//показываем админу время выполнения скрипта (раскомментировать для показа эту строку и в конце ещё одну)
//if($member_id['user_id'] == 1) $start = microtime(true);

/*************Дальше не нужно ничего трогать если не знаете, что делать *****************/
if(!is_numeric($day)) 			$day = 30; 					
if(!is_string($show_cat)) 		$show_cat = ""; 			
if(!is_string($ignore_cat)) 	$ignore_cat = ""; 					
if(!is_numeric($start_from)) 	$start_from = 0; 			
if(!is_numeric($news_num)) 		$news_num = 10; 			
if(!is_string($img_xfield)) 	$img_xfield = "";			
if(!is_string($img_size)) 		$img_size = "60x60";			
if(!is_string($noimage)) 		$noimage = "noimage.png";			
if(!is_string($template))		$template = "";		
if(!is_string($author))			$author = "";
if(!is_string($xfilter))		$xfilter = "";

$img_size = intval($chk_img_size[0]).((count($chk_img_size)>=2)?'x'.intval($chk_img_size[1]):''); // Мало ли идиотов
$author = @$db->safesql ( strip_tags ( str_replace ( '/', '', $author ) ) );
$xfilter = @$db->safesql ( strip_tags ( str_replace ( '/', '', $xfilter ) ) );

if(floatval($config['version_id'])>=9.6) $new_version = 1; //контроль версий DLE.

if($nocache) {
	$config['allow_cache'] = "no";
} else {
	$config['allow_cache'] = "yes";
}

if($show_cat == "this") $block_id .= "_cat_".$category_id;

if($author) $block_id .= "_author_".$author;

$blockpro = dle_cache("news_bp_".$block_id, $config['skin']);

if( !$blockpro ) {

	$dir = ROOT_DIR . '/uploads/blockpro/';
	if(!is_dir($dir)){	
		@mkdir($dir, 0777);
		@chmod($dir, 0777);
	} 
	if(!chmod($dir, 0777)) {
		@chmod($dir, 0777);
	}
	
	if($template){	
		
		global $tplb;
		if(!isset($tplb)) {
			$tplb = new dle_template();
			$tplb->dir = TEMPLATE_DIR;
		}
				
		$tplb->load_template ( $template.'.tpl' );
		
		$tooday = date ('Y-m-d H:i:s', $_TIME); 
		
		$query_mod = "";
		$ignore_category = $ignore_cat?"NOT":"";
		
		if ($show_cat && $show_cat !="this") $query_mod .= "AND {$ignore_category} p.category regexp '[[:<:]](".str_replace(',', '|', $show_cat).")[[:>:]]'"; 
		if ($show_cat == "this" && $category_id !="") $query_mod .= "AND {$ignore_category} p.category IN (".intval($category_id).")";
		
		if($xfilter) $query_mod .= "AND p.xfields regexp '[[:<:]](".$xfilter.")[[:>:]]'";
		
		$p_date = $new_version?"p.date":"date";
		if ($day && $day !== 0 && !$last && !$relatedpro && !$random) $query_mod .= "AND '$p_date' >= '$tooday' - INTERVAL {$day} DAY"; 
		$query_mod .= " AND '$p_date' < '$tooday' "; 

		$sort_var = "rating DESC, comm_num DESC, news_read DESC"; //По умолчанию выводим как обычный топ
		if ($random) $sort_var = "RAND()"; // Рандомный вывод
		if ($last) $sort_var = "'$p_date' DESC"; // По дате
		if ($top_comm) $sort_var = "comm_num DESC"; // По комментариям
		if ($top_rating) $sort_var = "rating DESC"; // По рейтингу
		if ($top_views) $sort_var = "news_read DESC"; // По просмотрам
		
		if($author) $query_mod .= "AND autor='{$author}'";
		
		if($relatedpro) {
			if( strlen( $row['full_story'] ) < strlen( $row['short_story'] ) ) $body = $row['short_story'];
			else $body = $row['full_story'];
			$body = $db->safesql( strip_tags( stripslashes( $metatags['title'] . " " . $body ) ) );		
			if($new_version) {
				$tb = $db->query("
					SELECT 
						p.id, p.autor, p.date, p.short_story, p.xfields, p.title, p.category, p.alt_name, p.comm_num, e.news_read, e.allow_rate, e.rating, e.vote_num, e.votes 
					FROM
						(SELECT
							p.id, p.autor, p.date, p.short_story, p.xfields, p.title, p.category, p.alt_name, p.comm_num
						FROM
							" . PREFIX . "_post p
						WHERE 
							MATCH (title, short_story, full_story, xfields) AGAINST ('$body') AND id != " . $row['id'] . " AND approve {$query_mod}
						) as p
						LEFT JOIN " . PREFIX . "_post_extras e ON (p.id=e.news_id) LIMIT ".$start_from.",".$news_num); 
			} else {
				$tb = $db->query("SELECT id, category, title, news_read, short_story, full_story, autor, xfields, comm_num, date, flag, alt_name, allow_rate, rating, vote_num FROM ".PREFIX."_post WHERE MATCH (title, short_story, full_story, xfields) AGAINST ('$body') AND id != " . $row['id'] . " AND approve=1 {$query_mod} LIMIT ".$start_from.",".$news_num);
			}
		} else {
			if($new_version) {
				$tb = $db->query("SELECT p.id, p.autor, p.date, p.short_story, p.xfields, p.title, p.category, p.alt_name, p.comm_num, e.news_read, e.allow_rate, e.rating, e.vote_num, e.votes FROM " . PREFIX . "_post p LEFT JOIN " . PREFIX . "_post_extras e ON (p.id=e.news_id) WHERE p.approve=1 {$query_mod} ORDER BY {$sort_var} LIMIT ".$start_from.",".$news_num); 
			} else {
				$tb = $db->query("SELECT id, category, title, news_read, short_story, full_story, autor, xfields, comm_num, date, flag, alt_name, allow_rate, rating, vote_num FROM ".PREFIX."_post WHERE approve=1 {$query_mod} ORDER BY {$sort_var} LIMIT ".$start_from.",".$news_num);
			}
		}
		
		while ($rowb = $db->get_row($tb)) {
		
			$xfields = xfieldsload();
			$rowb['date'] = strtotime( $rowb['date'] );
			$rowb['short_story'] = stripslashes($rowb['short_story']);
			
			// ссылки на категории
			$my_cat = array ();
			$my_cat_icon = array ();
			$my_cat_link = array ();
			$cat_list = explode( ',', $rowb['category'] );
			foreach ( $cat_list as $element ) {
				if( isset($cat_info[$element]) ) {
					$my_cat[] = $cat_info[$element]['name'];
					if ($cat_info[$element]['icon'])
						$my_cat_icon[] = "<img class=\"category-icon\" src=\"{$cat_info[$element]['icon']}\" alt=\"{$cat_info[$element]['name']}\" />";
					else
						$my_cat_icon[] = "<img class=\"category-icon\" src=\"/templates/".$config['skin']."/images/no_icon.gif\" alt=\"{$cat_info[$element]['name']}\" />";
					if( $config['allow_alt_url'] == "yes" ) 
						$my_cat_link[] = "<a href=\"" . $config['http_home_url'] . get_url( $element ) . "/\">{$cat_info[$element]['name']}</a>";
					else 
						$my_cat_link[] = "<a href=\"$PHP_SELF?do=cat&category={$cat_info[$element]['alt_name']}\">{$cat_info[$element]['name']}</a>";
				}
			}
			// конец ссылкам
			
			$tplb->set ( '{link-category}', implode( ', ', $my_cat_link ) );
			$tplb->set ( '{category}', implode( ', ', $my_cat ) );
			$tplb->set ( '{category-icon}', implode( '', $my_cat_icon) );
			if ( $rowb['category'] )
				$tplb->set( '{category-url}', $config['http_home_url'] . get_url( $rowb['category'] ) . "/" );
			else
				$tplb->set( '{category-url}', "#" );
			
			// ссылочка на всю новость
			$rowb['category'] = intval( $rowb['category'] ); // из всех категорий, берём первую
			
			if( $config['allow_alt_url'] == "yes" ) {
				if($config['seo_type'] == 1 OR $config['seo_type'] == 2) {
					if( $rowb['category'] and $config['seo_type'] == 2 ) {
						$full_link = $config['http_home_url'] . get_url( $rowb['category'] ) . "/" . $rowb['id'] . "-" . $rowb['alt_name'] . ".html";
					} else {
						$full_link = $config['http_home_url'] . $rowb['id'] . "-" . $rowb['alt_name'] . ".html";
					}
				} else {
					$full_link = $config['http_home_url'] . date( 'Y/m/d/', $rowb['date'] ) . $rowb['alt_name'] . ".html";
				}
			} else {
				$full_link = $config['http_home_url'] . "index.php?newsid=" . $rowb['id'];
			}
			// конец ссылкам

			// работаем с доп. полям
			if( strpos( $tplb->copy_template, "[xfvalue_" ) !== false OR strpos( $tplb->copy_template, "[xfgiven_" ) !== false ) {
				$xfieldsdata = xfieldsdataload( $rowb['xfields'] );
				foreach ( $xfields as $value ) {				
					$preg_safe_name = preg_quote( $value[0], "'" );
					if ( $value[6] AND !empty( $xfieldsdata[$value[0]] ) ) {
						$temp_array = explode( ",", $xfieldsdata[$value[0]] );
						$value3 = array();
						foreach ($temp_array as $value2) {
							$value2 = trim($value2);
							$value2 = str_replace("&#039;", "'", $value2);
							if( $config['allow_alt_url'] == "yes" ) $value3[] = "<a href=\"" . $config['http_home_url'] . "xfsearch/" . urlencode( $value2 ) . "/\">" . $value2 . "</a>";
							else $value3[] = "<a href=\"$PHP_SELF?do=xfsearch&amp;xf=" . urlencode( $value2 ) . "\">" . $value2 . "</a>";
						}
						$xfieldsdata[$value[0]] = implode(", ", $value3); 
						unset($temp_array);
						unset($value2);
						unset($value3);
					}			
					if( empty( $xfieldsdata[$value[0]] ) ) {
						$tplb->copy_template = preg_replace( "'\\[xfgiven_{$preg_safe_name}\\](.*?)\\[/xfgiven_{$preg_safe_name}\\]'is", "", $tplb->copy_template );
						$tplb->copy_template = str_replace( "[xfnotgiven_{$preg_safe_name}]", "", $tplb->copy_template );
						$tplb->copy_template = str_replace( "[/xfnotgiven_{$preg_safe_name}]", "", $tplb->copy_template );
					} else {
						$tplb->copy_template = preg_replace( "'\\[xfnotgiven_{$preg_safe_name}\\](.*?)\\[/xfnotgiven_{$preg_safe_name}\\]'is", "", $tplb->copy_template );
						$tplb->copy_template = str_replace( "[xfgiven_{$preg_safe_name}]", "", $tplb->copy_template );
						$tplb->copy_template = str_replace( "[/xfgiven_{$preg_safe_name}]", "", $tplb->copy_template );
					}
					
					// выдергиваем картинку из доп. поля, если оно задано параметрами
					if ($preg_safe_name == $img_xfield) {
						$info = pathinfo($xfieldsdata[$value[0]]);
						if (isset($info['extension'])) {
							$info['extension'] = strtolower($info['extension']);
							// это точно картинка?
							if(in_array($info['extension'],array('jpg','jpeg','gif','png'))) {								
								$file_name = strtolower ( basename ($xfieldsdata[$value[0]]));
								$original_img = $xfieldsdata[$value[0]];
								$file_name = $img_size."_".$file_name;
								// если картинки нету, делаем её
								if (!file_exists($dir.$file_name)) {
									require_once ENGINE_DIR . '/classes/thumb.class.php';
									$thumb = new thumbnail($xfieldsdata[$value[0]]);
									$thumb->size_auto($img_size);
									$thumb->save($dir.$file_name); 
								}
								$tplb->copy_template = str_replace( "[xfvalue_{$img_xfield}]", $config['http_home_url']."uploads/blockpro/".$file_name, $tplb->copy_template );
							} else {
								// обмануть решил? получи $noimage, если я не знаю твой формат
								$tplb->copy_template = str_replace( "[xfvalue_{$img_xfield}]", "/templates/".$config['skin']."/images/".$noimage."", $tplb->copy_template );
							}
						}
					}
					// конец картинкам
					$tplb->copy_template = str_replace( "[xfvalue_{$preg_safe_name}]", stripslashes( $xfieldsdata[$value[0]] ), $tplb->copy_template );
				}
			}
			// конец допполей
			
			// ух какой хитрый, картинки захотел? будем работать, если только ты не захотел картинку из доп поля.
			if (stripos ( $tplb->copy_template, "{image-" ) !== false && !$img_xfield) {
				
				// парсим адресат из шорт_стори			
				preg_match_all('/(img|src)=("|\')[^"\'>]+/i', $rowb['short_story'], $media);
				unset($data);
				$data=preg_replace('/(img|src)("|\'|="|=\')(.*)/i',"$3",$media[0]);
				$image = array();
				
				// бывает же такое, спарсили что-то, обрабатываем спаршенное и собираем опять
				foreach($data as $url) {
					// во первых, проверка, чтобы картинка была только в папке uploads, ибо нефик!.
					
					//Покачто закомментировал т.к. на локалке работает всё нормально, нужно проверять на хостинге.
					/*$url = explode('/uploads/', $url);
					if(count($url) != 2) continue; // да ну нафиг, если в нескольких папках uploads					
					$url = ROOT_DIR . '/uploads/' . $url[1];				
					if(!is_file($url))  continue;*/
					
					// Так то дешевле будет ))) вдруг другой домен?
					//if(stripos($url, $config['http_home_url'].'uploads/')===false) continue;
					
					$info = pathinfo($url);				
					if (isset($info['extension'])) {
						$info['extension'] = strtolower($info['extension']);
						if(in_array($info['extension'],array('jpg','jpeg','gif','png'))) {
							$original_img = str_replace(ROOT_DIR, '', $url);
							$file_name = strtolower ( basename ( $url ));
							$file_name = $img_size."_".$file_name;
							if (!file_exists($dir.$file_name)) {
								require_once ENGINE_DIR . '/classes/thumb.class.php';
								$thumb = new thumbnail($url);
								$thumb->size_auto($img_size);	
								$thumb->save($dir.$file_name); 
							}
							//if($img_size == 0) {
							if (!file_exists($dir.$file_name)) {
								$image[] = $original_img;
							} else {
								$image[] = $config['http_home_url']."uploads/blockpro/".$file_name;
							}
						}
					}
				} 
				
				if ( count($image) ) {
					$i=0;
					foreach($image as $url) {
						$i++;
						$tplb->copy_template = str_replace( '{image-'.$i.'}', $url, $tplb->copy_template );
					}
				}
				// для всех незаполненых тегов {image-x} вставляем заглушку
				$tplb->copy_template = preg_replace( "#\\{image-([0-9]{1,})\\}#i", "/templates/".$config['skin']."/images/".$noimage."", $tplb->copy_template );
			
			}
			//показ оригинальной картинки если в шаблоне есть тег {image_original}
			if ($original_img == "") {
				$tplb->set( '{image_original}', "");
				$tplb->copy_template = preg_replace( "'\\[image_original\\](.*?)\\[/image_original\\]'is", "", $tplb->copy_template );
				$tplb->copy_template = str_replace( "[not_image_original]", "", $tplb->copy_template );
				$tplb->copy_template = str_replace( "[/not_image_original]", "", $tplb->copy_template );
			} else {
				$tplb->set( '{image_original}', $original_img );
				$tplb->copy_template = preg_replace( "'\\[not_image_original\\](.*?)\\[/not_image_original\\]'is", "", $tplb->copy_template );
				$tplb->copy_template = str_replace( "[image_original]", "", $tplb->copy_template );
				$tplb->copy_template = str_replace( "[/image_original]", "", $tplb->copy_template );
			}
			unset($original_img);
			//конец показа оригинальной картинки
			
			//Показ рейтинга
			if( $rowb['allow_rate'] ) {
				if( $config['short_rating'] and $user_group[$member_id['user_group']]['allow_rating'] ) $tplb->set( '{rating}', ShortRating( $rowb['id'], $rowb['rating'], $rowb['vote_num'], 1 ) );
				else $tplb->set( '{rating}', ShortRating( $rowb['id'], $rowb['rating'], $rowb['vote_num'], 0 ) );
				$tplb->set( '{vote-num}', $rowb['vote_num'] );
				$tplb->set( '[rating]', "" );
				$tplb->set( '[/rating]', "" );
			} else {
				$tplb->set( '{rating}', "" );
				$tplb->set( '{vote-num}', "" );
				$tplb->set_block( "'\\[rating\\](.*?)\\[/rating\\]'si", "" );
			}
			//Конец показа рейтинга
			
			/* понеслась, куча тегов.. */
			if( $config['allow_alt_url'] == "yes" ) $go_page = $config['http_home_url'] . "user/" . urlencode( $rowb['autor'] ) . "/"; 
			else $go_page = "$PHP_SELF?subaction=userinfo&amp;user=" . urlencode( $rowb['autor'] );
			
			$tplb->set( '[profile]', '<a href="'. $go_page .'" title="'. urlencode( $rowb['autor'] ) .'">' );
			$tplb->set( '[/profile]', '</a>' );
			$tplb->set( '{login}', $rowb['autor'] );		
			$tplb->set( '{author}', "<a onclick=\"ShowProfile('" . urlencode( $rowb['autor'] ) . "', '" . $go_page . "', '" . $user_group[$member_id['user_group']]['admin_editusers'] . "'); return false;\" title=\"" . urlencode( $rowb['autor'] ) . "\" href=\"" . $go_page . "\">" . $rowb['autor'] . "</a>" );
			
			
			if( date( 'Ymd', $rowb['date'] ) == date( 'Ymd', $_TIME ) ) {
				$tplb->set( '{date}', $lang['time_heute'] . langdate( ", H:i", $rowb['date'] ) );
			} elseif( date( 'Ymd', $rowb['date'] ) == date( 'Ymd', ($_TIME - 86400) ) ) {
				$tplb->set( '{date}', $lang['time_gestern'] . langdate( ", H:i", $rowb['date'] ) );
			} else {
				$tplb->set( '{date}', langdate( $config['timestamp_active'], $rowb['date'] ) );
			}
			// дата разного формата бывает...
			$tplb->copy_template = preg_replace ( "#\{date=(.+?)\}#ie", "langdate('\\1', '{$rowb['date']}')", $tplb->copy_template );
			
			$title = htmlspecialchars( strip_tags( stripslashes( $rowb['title'] ) ) );
			$tplb->set( '{title}', $title );
			if ( preg_match( "#\\{title limit=['\"](.+?)['\"]\\}#i", $tplb->copy_template, $t_matches ) ) {
				$t_count= intval($t_matches[1]);
				if( $t_count AND dle_strlen( $title, $config['charset'] ) > $t_count ) {
					$title = dle_substr( $title, 0, $t_count, $config['charset'] ) . "&hellip;";				
				}
				$tplb->set( $t_matches[0], $title );
			} 
			
			if( $user_group[$member_id['user_group']]['allow_hide'] )
				$rowb['short_story'] = str_ireplace( array("[hide]","[/hide]"), "", $rowb['short_story']);
			else 
				$rowb['short_story'] = preg_replace ( "#\[hide\](.+?)\[/hide\]#ims", "<div class=\"quote\">" . $lang['news_regus'] . "</div>", $rowb['short_story'] );

			if ( preg_match( "#\\{text limit=['\"](.+?)['\"]\\}#i", $tplb->copy_template, $matches ) ) {
				$count= intval($matches[1]);
				$rowb['short_story'] = strip_tags( $rowb['short_story'], "<br>" );
				$rowb['short_story'] = trim(str_replace( array("<br>",'<br />'), " ", $rowb['short_story'] ));
				if( $count>0 AND dle_strlen( $rowb['short_story'], $config['charset'] ) > $count ) {					
					$rowb['short_story'] = dle_substr( $rowb['short_story'], 0, $count, $config['charset'] ). "&hellip;";					
					if( !$wordcut && ($word_pos = dle_strrpos( $rowb['short_story'], ' ', $config['charset'] )) ) 
						$rowb['short_story'] = dle_substr( $rowb['short_story'], 0, $word_pos, $config['charset'] ). "&hellip;";
				}
				$tplb->set( $matches[0], $rowb['short_story'] );
			} else 
				$tplb->set( '{text}', $rowb['short_story'] );
			
			$tplb->set( '{full-link}', $full_link );
			$tplb->set ( '{comments-num}', $rowb['comm_num'] );
			$tplb->set ( '{views}', $rowb['news_read'] );

			if( $allow_userinfo and ! $rowb['approve'] and ($member_id['name'] == $rowb['autor'] and ! $user_group[$member_id['user_group']]['allow_all_edit']) ) {
				$tplb->set( '[edit]', "<a href=\"" . $config['http_home_url'] . "index.php?do=addnews&id=" . $rowb['id'] . "\" >" );
				$tplb->set( '[/edit]', "</a>" );
			} elseif( $is_logged and (($member_id['name'] == $rowb['autor'] and $user_group[$member_id['user_group']]['allow_edit']) or $user_group[$member_id['user_group']]['allow_all_edit']) ) {
				$_SESSION['referrer'] = $_SERVER['REQUEST_URI'];
				$tplb->set( '[edit]', "<a onclick=\"return dropdownmenu(this, event, MenuNewsBuild('" . $rowb['id'] . "', 'short'), '170px')\" href=\"#\">" );
				$tplb->set( '[/edit]', "</a>" );
				$allow_comments_ajax = true;
			} else
				$tplb->set_block( "'\\[edit\\](.*?)\\[/edit\\]'si", "" );
			/* конец кучки тегов  */
			
			$tplb->compile ( 'blockpro' ); 
		}
			$blockpro = $tplb->result['blockpro'];
	} else {
		$blockpro = 'Извини дружище, но без шаблона я не умею работать, советую в строке подключения указать: <strong style="color: red;">&template=blockpro</strong> <br />Только не забудь почистить кеш DLE!';
	}
		//unset($tplb);
		$db->free();
		create_cache("news_bp_".$block_id, $blockpro, $config['skin'] );
}

	if(!$relatedpro && !$blockpro) 
		$blockpro = '<div class="blockpro">Где то косяк! Проверь правильность строки подключения. Возможно просто нет новостей за последние 30 дней.</div>';

	if($relatedpro){
		if($blockpro){
			$tpl->set( '[related-news]', "" );
			$tpl->set( '[/related-news]', "" );
		} else {
			$tpl->set_block( "'\\[related-news\\](.*?)\\[/related-news\\]'si", "" );
		}
		$tpl->set( '{related-news}', $blockpro );
	} else
		echo $blockpro;	
		
unset($blockpro);

//показываем админу время выполнения скрипта (раскомментировать для показа эту строку и в начале ещё одну)
//if($member_id['user_id'] = 1)echo "Время выполнения Block.Pro: <b>". round((microtime(true) - $start), 6). "</b> сек";
?>