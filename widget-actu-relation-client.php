<?php
/*
Plugin Name: Widget Actualites Relation Client
Description: Ce plugin vous propose d'afficher sur votre blog (sous la forme d'un widget) les derni&egrave;res actualit&eacute;s des principaux sites dans le domaine de la relation client, du service client et de la qualit&eacute; pour les entreprises.
Ce plugin est destin&eacute; aux professionnels.
Author: Contacter.net
Version: 2.0
Author URI: http://contacter.net
*/

/*
    Please read this : this program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/



define('SGRSS_HOWMANY', 20);
define('SGRSS_MAXITEMS', 20);
define('SGRSS_FORCECACHE', false);

define('SGRSS_WPMU', false);


function widget_sgrss_init() {


	if ( !function_exists('register_sidebar_widget') )
		return;
	if ( class_exists('sg_rss') )
		return;


	if ( !function_exists('htmlspecialchars_decode') ){
	    function htmlspecialchars_decode($text){
	        return strtr($text, array_flip(get_html_translation_table(HTML_SPECIALCHARS)));
	    }
	}
	

	if (!function_exists('attribute_escape')){
		function attribute_escape($text) {
			$safe_text = wp_specialchars($text, true);
			return apply_filters('attribute_escape', $safe_text, $text);
		}
	}


	class sg_rss{
	

		var $title;
		var $linktitle;
		var $num_items;
		var $url;
		var $output_begin;
		var $output_format;
		var $output_end;
		var $utf;
		var $icon;
		var $display_empty;
		var $reverse_order;
		
		var $number;
		var $md5;
		var $md5_option;
		var $md5_option_ts;
		
		var $link;
		var $desc;
		
		var $tokens;
		var $items;

		function load_magpie(){
			if (function_exists('fetch_rss'))
				return true;

			if (file_exists(ABSPATH . WPINC . '/rss.php') )
				require_once(ABSPATH . WPINC . '/rss.php');
			elseif (file_exists(ABSPATH . WPINC . '/rss-functions.php') )
				require_once(ABSPATH . WPINC . '/rss-functions.php');

			if (function_exists('fetch_rss'))
				return true;
			return false;
		}
		
		function load_options( $args = false ){
			if (is_array($args)){
				$this->number = 0;
				$options[0]['url'] = $args[0];
				$options[0]['items'] = $args[1];
				$options[0]['output_format'] = $args[2];
				$options[0]['utf'] = $args[3];
			}else{
				$options = get_option('widget_sgrss');
			}
			
			$this->title = $options[$this->number]['title'];
			$this->linktitle = $options[$this->number]['linktitle'];

			$this->num_items = (int) $options[$this->number]['items'];
			if ( empty($this->num_items) || ($this->num_items < 1) || ($this->num_items > SGRSS_MAXITEMS) )
				$this->num_items = SGRSS_MAXITEMS;
			
			$this->url = $options[$this->number]['url'];
			if ( empty($this->url) || false===strpos($this->url,'http') )
				return false;

			while ( strstr($this->url, 'http') != $this->url )
				$this->url = substr($this->url, 1);

			$this->md5 = md5($this->url);
			$this->md5_option = 'rss_' . $this->md5;
			$this->md5_option_ts = $this->md5_option . '_ts';
			
			$this->output_begin = $options[$this->number]['output_begin'];
			$this->output_format = $options[$this->number]['output_format'];
			$this->output_end = $options[$this->number]['output_end'];
			$this->utf = $options[$this->number]['utf'];
			$this->display_empty = (1==$options[$this->number]['display_empty']) ? true : false;
			$this->reverse_order = (1==$options[$this->number]['reverse_order']) ? true : false;
			
			$this->icon = $options[$this->number]['icon'];
			
			if ( empty($this->output_format) )
				$this->output_format = '<li><a class="sgrsswidget" href="^link$" title="^description$"><strong>^title$</strong></a></li>';

			return true;
		}
		
		function force_cache(){

			global $userdata;
			if ( ('flush' == $_GET['sgrss_cache']) && ($userdata->user_level >= 7) ){
				delete_option( $this->md5_option );
				return;
			}

			if ( ! SGRSS_FORCECACHE )
				return;
			$cachetime = get_option( $this->md5_option_ts );
			if ( $cachetime < ( time() - 3600 ) )
				delete_option( $this->md5_option );
		}
		
		function get_feed(){
			$rss = @fetch_rss($this->url);
			
			/*
			$this->link = clean_url(strip_tags($rss->channel['link']));
			while( strstr($this->link, 'http') != $this->link )
				$this->link = substr( $this->link, 1 );
			*/

			$this->desc = attribute_escape(strip_tags(html_entity_decode($rss->channel['description'], ENT_QUOTES)));

			$this->url = clean_url(strip_tags($this->url));
			
			if ( ('link'==$this->linktitle) && $this->title )
				$this->title = '<a href="#">'. $this->title .'</a>';
				//$this->title = '<a href="'. $this->link .'">'. $this->title .'</a>';
			
			if ('' != $this->icon)
				$this->title = '<a class="sgrsswidget" href="'.$this->url.'" title="Suivez ce flux"><img width="14" height="14" src="'.$this->icon.'" alt="RSS" style="background:orange;color:white;" /></a> '.$this->title;
			

			if ( is_array($rss->items) && !empty( $rss->items ) ){
				if ($this->reverse_order)
					$rss->items = array_reverse( $rss->items );
				$rss->items = array_slice($rss->items, 0, $this->num_items);

				$this->items = '';

				foreach( $rss->items as $item ){
					$find = array();
					foreach( $this->tokens as $token ){
						$replace = '';
						if ( is_array($item[ $token['field'] ]) ){
							if ( $token['opts']['subfield'] ){
								$replace = $item[ $token['field'] ][ $token['opts']['subfield'] ];
								$replace = $this->item_cleanup( $replace, $token['opts'] );
							}elseif ( $token['opts']['loop'] ) {
								foreach( $item[ $token['field'] ] as $subfield ){
									$subfield = $this->item_cleanup( $subfield, $token['opts'] );
									$replace .= $token['opts']['beforeloop'] . $subfield . $token['opts']['afterloop'];
									}
							}
						}else{
							$replace = $item[ $token['field'] ];
							$is_url = ('link'==$token['slug']) ? true : false;
							$replace = $this->item_cleanup( $replace, $token['opts'], $is_url );
						}
						$find[ $token['slug'] ] = $replace;
					}
					$keys = array_keys( $find );
					$vals = array_values( $find );
					$this->items .= str_replace( $keys, $vals, $this->output_format );
				}
				
				if ($this->utf)
					$this->items = utf8_encode( $this->items );

			}else{
				if ($this->display_empty){
					if ( '<li' === substr( $this->output_format, 0, 3 ) )
						$this->items = '<li>' . __( 'Une erreur est survenue ! Contenu inaccessible pour le moment. Réessayez plus tard...' ) . '</li>';
					else
						$this->items = __( 'Erreur ! Contenu inaccessible pour le moment. Réessayez plus tard.' );
				}else{
					$this->items = '';
					return false;
				}
			}
			return true;
		}

		function item_cleanup($text,$opts=false,$url=false){
			if (SGRSS_WPMU || !is_array($opts) || !array_key_exists('bypasssecurity',$opts)){
				if ($url)
					$text = clean_url(strip_tags($text));
				else
					$text = str_replace(array("\n", "\r"), ' ', attribute_escape(strip_tags(html_entity_decode($text, ENT_QUOTES))));
			}

			if (!is_array($opts))
				return $text;
			extract($opts, EXTR_SKIP);
			
			if ($date){
				$text = $this->make_date($text,$date);
			}
			
			$ltrim = (is_numeric($ltrim) && 0<$ltrim) ? (int) $ltrim : null;
			if (is_int($ltrim))
				$text = substr( $text, $ltrim );

			$trim = (is_numeric($trim) && 0<$trim) ? (int) $trim : null;
			if (is_int($trim))
				$text = substr( $text, 0, $trim );

			return $text;
		}

		function make_date($string, $format){
			$time = strtotime( $string );
			if (false===$time || -1===$time)
				return $string;
			return date( $format, $time );
		}
		
		function detect_tokens(){
			if (''==$this->output_format)
				return false;
			preg_match_all( '~\^([^$]+)\$~', $this->output_format, $matches, PREG_SET_ORDER);
			if (!is_array($matches) || empty($matches))
				return false;
			$tokens = array();
			$used = array();
			foreach( $matches as $match ){
				if ( in_array($match[0], $used) )
					continue;
				$used[] = $match[0];

				$token = array();
				$token['slug'] = $match[0];
				$token['opts'] = array();

				if ( strpos($match[1], '[opts:') ){
					$explode = explode( '[opts:', $match[1], 2 );
					$match[1] = $explode[0];
					$opts = substr( $explode[1], 0, -1 );
					parse_str( $opts, $options );
					$token['opts'] = array_merge( $token['opts'], $options );
				}

				if ( strpos($match[1], '%%') ){
					$explode = explode( '%%', $match[1] );
					$match[1] = $explode[0];
					$token['opts']['trim'] = $explode[1];
				}

				if ( strpos($match[1], '=>') ){
					$explode = explode( '=>', $match[1], 2);
					$match[1] = $explode[0];
					$token['opts']['subfield'] = $explode[1];
				}elseif( strpos($match[1], '||') ){
					$explode = explode( '||', $match[1], 3);
					$match[1] = $explode[0];
					$token['opts']['loop'] = true;
					$token['opts']['beforeloop'] = $explode[1];
					$token['opts']['afterloop'] = $explode[2];
				}

				$token['field'] = $match[1];

				$tokens[] = $token;
			}

			if (empty($tokens))
				return false;

			$this->tokens = $tokens;
			return true;
		}

		function prepare_widget(){
			if (!$this->load_magpie())
				return false;
			if (!$this->load_options())
				return false;
			if (!$this->detect_tokens())
				return false;
			$this->force_cache();
			if (!$this->get_feed())
				return false;
			return true;
		}
		
		function display_widget( $args, $num = 1 ){
			$this->number = $num;
			if (!$this->prepare_widget()){
				echo '<!-- Actu relation client Widget -->';
				return;
			}
			extract( $args );
			echo $before_widget;
			if ( $this->title )
				echo $before_title . $this->title . $after_title;
			echo $this->output_begin;
			echo $this->items;
			echo $this->output_end;
			echo $after_widget;
		}
		
		function display_template( $url, $format, $numItems=10, $utf=false, $echo=true ){
			if (!$this->load_magpie())
				return false;
			if (!$this->load_options( array($url, $numItems, $format, $utf) ))
				return false;
			if (!$this->detect_tokens())
				return false;
			$this->force_cache();
			if (!$this->get_feed())
				return false;
			if ($echo)
				echo $this->items;
			else
				return $this->items;
		}
	}

	function widget_sgrss( $args, $number = 1 ){
		global $sg_rss;
		$sg_rss->display_widget( $args, $number );
	}

	function sg_rss_template($url, $format, $numItems=10, $utf=false, $echo=true){
		global $sg_rss;
		return $sg_rss->display_template( $url, $format, $numItems, $utf, $echo );
	}

	function widget_sgrss_control($number) {
		$options = get_option('widget_sgrss');
		$newoptions = $options;

		if ( $_POST["sgrss-submit-$number"] ) {
			$newoptions[$number]['items'] = (int) $_POST["sgrss-items-$number"];
			$newoptions[$number]['url'] = strip_tags(stripslashes($_POST["sgrss-url-$number"]));
			if ( file_exists(dirname(__FILE__) . '/rss.png') ){
				$icon = str_replace(ABSPATH, get_settings('siteurl').'/', dirname(__FILE__)) . '/rss.png';
			}else{
				$icon = get_settings('siteurl').'/wp-includes/images/rss.png';
			}
			$newoptions[$number]['icon'] = strip_tags(stripslashes($icon));
			if (SGRSS_WPMU){
				$newoptions[$number]['title'] = trim(strip_tags(stripslashes($_POST["sgrss-title-$number"])));
			}else{
				$newoptions[$number]['title'] = trim( stripslashes($_POST["sgrss-title-$number"]) );
			}
			$newoptions[$number]['linktitle'] = "link";
			$newoptions[$number]['display_empty'] = (1==$_POST["sgrss-hideempty-$number"]) ? 0 : 1;
			$newoptions[$number]['reverse_order'] = (1==$_POST["sgrss-reverseorder-$number"]) ? 1 : 0;
			$newoptions[$number]['output_format'] = htmlspecialchars_decode( stripslashes($_POST["sgrss-output_format-$number"]) );
			$newoptions[$number]['output_begin'] = htmlspecialchars_decode( stripslashes("<ul>") );
			$newoptions[$number]['output_end'] = htmlspecialchars_decode( stripslashes("</ul>") );
			$newoptions[$number]['utf'] = null;


		}
		if ( $options != $newoptions ) {
			$options = $newoptions;
			update_option('widget_sgrss', $options);
		}
		$url = htmlspecialchars($options[$number]['url'], ENT_QUOTES);
		$items = (int) $options[$number]['items'];
		$title = htmlspecialchars($options[$number]['title'], ENT_QUOTES);
		$display_empty = (int) $options[$number]['display_empty'];
		$reverse_order = (int) $options[$number]['reverse_order'];
		$output_format = htmlspecialchars($options[$number]['output_format'], ENT_QUOTES);

		$output_format1 = htmlspecialchars("<li><a class='sgrsswidget' href='^link\$' title='^description\$'>^title\$</a></li>", ENT_QUOTES);
		$output_format2 = htmlspecialchars("<li><a class='sgrsswidget' href='^link\$' title=''><strong>^title\$</strong></a><p class='sgrsswidget'>^description\$</p></li>", ENT_QUOTES);

		if ( empty($items) || $items < 1 ){
			$items = 10;
		}
		if ( '' == $title ){
			$title = "Actu relation client";
		}
		if ( '' == $output_format ){
			$output_format = $output_format1;
		}
		if ( '' == $url ){
			$url = "";
		}
		
		$feedcats = array(
      'Journaldunet.com (Management)' => 'http://www.journaldunet.com/management/rss/', 
      'Journaldunet.com (Business)' => 'http://www.journaldunet.com/business/rss/',
      'LSA-conso.fr (Service clientele)' => 'http://www.lsa-conso.fr/service-clientele/rss',
      'Easi-crm.com (Relation client)' => 'http://www.easi-crm.com/leblogdelarelationclient/feed/',
      'Dynamique-mag.com (Entreprenariat)' => 'http://www.dynamique-mag.com/rss/article.xml',
      'En-contact.com (Centre de contacts)' => 'http://www.en-contact.com/feed/',
      'Qualiteperformance.org (Qualite)' => 'http://www.qualiteperformance.org/l-actualite-de-la-qualite/rss/rss',
      'Lemagit.fr (Conseils IT)' => 'http://www.lemagit.fr/rss/Conseils-IT.xml',
      'Relationclientmag.fr (Strategie)' => 'http://www.relationclientmag.fr/rss/acteurs-strategies-1014.xml',
      'Relationclientmag.fr (Contact)' => 'http://www.relationclientmag.fr/rss/centre-de-contact-1015.xml',
      'Relationclientmag.fr (Technologie)' => 'http://www.relationclientmag.fr/rss/techno-solutions-it-1016.xml',
      'Relationclientmag.fr (Professionnels)' => 'http://www.relationclientmag.fr/rss/la-vie-de-la-profession-1017.xml',
    );
	?>
				<div id="sg_rss_settings_<?php echo $number; ?>">
					<table>
					<tr>
						<td><?php _e('Titre du widget (en option) :', 'sgwidgets'); ?> </td>
						<td colspan="3"><input style="width: 400px;" id="sgrss-title-<?php echo "$number"; ?>" name="sgrss-title-<?php echo "$number"; ?>" type="text" value="<?php echo $title; ?>" /></td>
					</tr>
					<tr>
						<td><label for="sgrss-url-<?php echo $number; ?>"><?php _e('Choix de la source :', 'sgwidgets'); ?> </label></td>
						<td colspan="3"><select id="sgrss-url-<?php echo $number; ?>" name="sgrss-url-<?php echo $number; ?>"><?php foreach ( $feedcats AS $key => $value ) echo "<option value='$value' ".($url==$value ? "selected='selected'" : '').">$key</option>"; ?></select></td>
					</tr>
					<tr>
            <td><label for="sgrss-output_format-<?php echo "$number"; ?>"><?php _e('Affichage', 'sgwidgets'); ?></label></td>
  					<td colspan="3"><select id="sgrss-output_format-<?php echo "$number"; ?>" name="sgrss-output_format-<?php echo "$number"; ?>">
  					  <option value="<?php echo $output_format1; ?>" <?php if($output_format == $output_format1) { echo 'selected="selected"'; } ?> >Les titres seulement</option>
  					  <option value="<?php echo $output_format2; ?>" <?php if($output_format == $output_format2) { echo 'selected="selected"'; } ?> >Titres et description courte</option>
            </select></td>
					</tr>
					<tr>
						<td><label for="sgrss-items-<?php echo $number; ?>"><?php _e('Nombre a afficher :', 'sgwidgets'); ?> </label></td>
						<td colspan="3"><select id="sgrss-items-<?php echo $number; ?>" name="sgrss-items-<?php echo $number; ?>"><?php for ( $i = 1; $i <= SGRSS_MAXITEMS; ++$i ) echo "<option value='$i' ".($items==$i ? "selected='selected'" : '').">$i</option>"; ?></select></td>
					</tr>
					<tr>
						<td><label for="sgrss-hideempty-<?php echo $number; ?>"><?php _e('Masquer le widget automatiquement si la source (le flux rss) est inaccessible ?', 'sgwidgets'); ?> </label></td>
						<td><input type="checkbox" name="sgrss-hideempty-<?php echo $number; ?>" id="sgrss-hideempty-<?php echo $number; ?>" value="1" <?php if ( 1!=$display_empty ){ echo 'checked="checked"'; } ?> /> </td>
					</tr>
					<tr>
						<td><label for="sgrss-reverseorder-<?php echo $number; ?>"><?php _e('Afficher dans le sens inverse ?', 'sgwidgets'); ?> </label></td>
						<td><input type="checkbox" name="sgrss-reverseorder-<?php echo $number; ?>" id="sgrss-reverseorder-<?php echo $number; ?>" value="1" <?php if ( 1===$reverse_order ){ echo 'checked="checked"'; } ?> /> </td>
					</tr>
					</table>
					<input type="hidden" id="sgrss-submit-<?php echo "$number"; ?>" name="sgrss-submit-<?php echo "$number"; ?>" value="1" />
				</div>
	<?php
	}
	

	function widget_sgrss_setup() {
		$options = $newoptions = get_option('widget_sgrss');
		if ( isset($_POST['sgrss-number-submit']) ) {
			$number = (int) $_POST['sgrss-number'];
			if ( $number > SGRSS_HOWMANY ) $number = SGRSS_HOWMANY;
			if ( $number < 1 ) $number = 1;
			$newoptions['number'] = $number;
		}
		if ( $options != $newoptions ) {
			$options = $newoptions;
			update_option('widget_sgrss', $options);
			widget_sgrss_register($options['number']);
		}
	}

	function widget_sgrss_page() {
		$options = $newoptions = get_option('widget_sgrss');
	?>
		<div class="wrap">
			<form method="POST">
				<h2>Widget Actualites Relation Client</h2>
				<p style="line-height: 30px;"><?php _e('Combien de widgets a afficher ?', 'sgwidgets'); ?>
				<select id="sgrss-number" name="sgrss-number" value="<?php echo $options['number']; ?>">
	<?php for ( $i = 1; $i <= SGRSS_HOWMANY; ++$i ) echo "<option value='$i' ".($options['number']==$i ? "selected='selected'" : '').">$i</option>"; ?>
				</select>
				<span class="submit"><input type="submit" name="sgrss-number-submit" id="sgrss-number-submit" value="<?php _e('Sauvegarder'); ?>" /></span></p>
			</form>
		</div>
	<?php
	}

	function widget_sgrss_register() {
		global $wp_version;
		$options = get_option('widget_sgrss');
		$number = $options['number'];
		if ( $number < 1 ) $number = 1;
		if ( $number > SGRSS_HOWMANY ) $number = SGRSS_HOWMANY;
		for ($i = 1; $i <= SGRSS_HOWMANY; $i++) {
			$name = array('Actu Relation Client %s', null, $i);
			if ( '2.2' == $wp_version ){
				register_sidebar_widget($name, $i <= $number ? 'widget_sgrss' : /* unregister */ '', '', $i);
				register_widget_control($name, $i <= $number ? 'widget_sgrss_control' : /* unregister */ '', 700, 580, $i);
			}elseif ( function_exists( 'wp_register_sidebar_widget' ) ){
				$id = "sg-rss-$i";
				$dims = array('width' => 700, 'height' => 580);
				$class = array( 'classname' => 'widget_sgrss' );
				$name = sprintf(__('Actualites Relation Client %d'), $i);
				wp_register_sidebar_widget($id, $name, $i <= $number ? 'widget_sgrss' : /* unregister */ '', $class, $i);
				wp_register_widget_control($id, $name, $i <= $number ? 'widget_sgrss_control' : /* unregister */ '', $dims, $i);
			}else{
				register_sidebar_widget($name, $i <= $number ? 'widget_sgrss' : /* unregister */ '', $i);
				register_widget_control($name, $i <= $number ? 'widget_sgrss_control' : /* unregister */ '', 700, 580, $i);
			}
		}
	
		add_action('sidebar_admin_setup', 'widget_sgrss_setup');
		add_action('sidebar_admin_page', 'widget_sgrss_page');

	}

	$GLOBALS['sg_rss'] = new sg_rss();
	widget_sgrss_register();

}

function widget_sgrss_troubleshooter(){
	if ( !($_GET['sgrss']) )
		return;

	global $userdata;
	if ( $userdata->user_level >= 7 ){
		if ( file_exists(ABSPATH . WPINC . '/rss.php') )
			require_once(ABSPATH . WPINC . '/rss.php');
		else
			require_once(ABSPATH . WPINC . '/rss-functions.php');
		$rss = @fetch_rss($_GET['sgrss']);
		$out = "<html><head><title>Widget Actualites Relation Client Probl&egrave;me</title></head><body><div style='background:#cc0;padding:1em;'><h2>Probleme(s)</h2><p>Ci-dessous, vous devriez voir votre blog WordPress avec le widget Actualites Relation Client. </p></div><pre>";
		$out .= htmlspecialchars( print_r($rss->items, true) );
		$out .= "</pre></body></html>";
		print $out;
		die;
	}else{
		print "<p>Vous devez &ecirc;tre connect&eacute; en tant qu'administrateur.</p>";
		die;
	}
	return;
}

add_action('widgets_init', 'widget_sgrss_init');
add_action('template_redirect', 'widget_sgrss_troubleshooter');

?>
