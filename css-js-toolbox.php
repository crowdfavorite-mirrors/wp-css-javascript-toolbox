<?php
/*
 Plugin Name: CSS & JavaScript Toolbox
 Plugin URI: http://wipeoutmedia.com/whats-new/css-javascript-toolbox/
 Description: WordPress plugin to easily add custom CSS and JavaScript to individual pages
 Version: 0.3
 Author: Wipeout Media 
 Author URI: http://wipeoutmedia.com/whats-new/css-javascript-toolbox/

 Copyright (c) 2011, Wipeout Media.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */


//avoid direct calls to this file where wp core files not present
if (!function_exists ('add_action')) {
		header('Status: 403 Forbidden');
		header('HTTP/1.1 403 Forbidden');
		exit();
}

define('CJTOOLBOX_VERSION', '0.2');
define('CJTOOLBOX_PATH', plugin_basename( dirname(__FILE__) ));
define('CJTOOLBOX_URL', WP_PLUGIN_URL . '/' . plugin_basename( dirname(__FILE__) ) . '/' );

if( !class_exists('cssJSToolbox') ) {
class cssJSToolbox {
	var $settings = array(); 
	var $settings_name;
	var $cjdata = array(); 
	var $cjdata_name;

	function __construct() {
	
		$this->settings_name = 'cjtoolbox_settings';
		$this->cjdata_name = 'cjtoolbox_data';
	
		// Load saved settings
		$this->getSettings();
	
		// Start this plugin once all other plugins are fully loaded
		add_action( 'plugins_loaded', array(&$this, 'start_plugin') );
	}

	function __destruct() {
		// Nothing to do now...
	}

	// Save new settings
	function saveSettings() {
		update_option($this->settings_name, $this->settings);
	}

	// Get back all saved settings
	function getSettings() {
		$this->settings = get_option($this->settings_name);
	}

	// Save/Get data
	function saveData() {
		$this->cjdata = array_values($this->cjdata);
		update_option($this->cjdata_name, $this->cjdata);
	}
	function getData() {
		$this->cjdata = get_option($this->cjdata_name);
	}

	function start_plugin() {
		if (is_admin() ) {
			// Load for admin panel
			add_action('admin_menu', array(&$this, 'add_plugin_menu'));
			//register the callback been used if options of page been submitted and needs to be processed
			add_action('admin_post_save_cjtoolbox', array(&$this, 'on_save_changes'));
			// register ajax save function
			add_action('wp_ajax_cjtoolbox_save', array(&$this, 'ajax_save_changes'));
			add_action('wp_ajax_cjtoolbox_save_newcode', array(&$this, 'ajax_save_newcode'));
			add_action('wp_ajax_cjtoolbox_form', array(&$this, 'ajax_show_form'));
			add_action('wp_ajax_cjtoolbox_get_code', array(&$this, 'ajax_get_code'));
			add_action('wp_ajax_cjtoolbox_delete_code', array(&$this, 'ajax_delete_code'));
			add_action('wp_ajax_cjtoolbox_add_block', array(&$this, 'ajax_add_block'));
			// Create the tables and sample data
		} else {
			// Add the script and style files to footer
			add_action('wp_head', array(&$this, 'cjtoolbox_insertcode'));
		}
	}

	function cjtoolbox_insertcode() {
		global $post;
		// Home page displays a page.
		$check_for = '';
		if(is_home()) { // The blog page. It will be either same as front page or will be a page.
			$check_for = 'allposts';
		}
		if(is_front_page()) {
			$check_for = 'frontpage';
		}
		if(is_page()) {
			$check_for = $post->ID;
		}
		if(is_single()) {
			$check_for = 'allposts';
		}

		$this->getData();

		$data = $this->cjdata;
		foreach($data as $key => $value) {
			$page_list = $data[$key]['page'];
			if(is_array($page_list)) {
				if(is_page() && in_array('allpages', $page_list)) {
					echo stripslashes($data[$key]['code']);
					continue;
				} else if(in_array($check_for, $page_list)) {
					echo stripslashes($data[$key]['code']);
					continue;
				}
			}
			if(is_category()) {
				$this_category = get_query_var('cat');
				$category_list = $data[$key]['category'];
				if(is_array($category_list)) {
					if(in_array($this_category, $category_list)) {
						echo stripslashes($data[$key]['code']);
						continue;
					}
				}
			}

			$pageURL = 'http';
			if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
			$pageURL .= "://";
			if ($_SERVER["SERVER_PORT"] != "80") {
			$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
			} else {
			$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
			}
			$links = stripslashes($data[$key]['links']);
			$link_list = explode("\n", $links);
			if(in_array($pageURL, $link_list)) {
				echo stripslashes($data[$key]['code']);
				continue;
			}
		}
	}

	// To add sub page & meta boxes
	function add_plugin_menu() {
		$this->hook_manage = add_options_page('CSS & JavaScript Toolbox' ,'CSS & JavaScript Toolbox', '10',  'cjtoolbox', array(&$this, 'admin_display'));
		// register callback gets call prior your own page gets rendered
		add_action('load-'.$this->hook_manage, array(&$this, 'admin_load_page'));

		// register callback to show styles needed for the admin page
		add_action( 'admin_print_styles-' . $this->hook_manage, array(&$this, 'admin_print_styles') );
		// Load scripts for admin panel working
		add_action('admin_print_scripts-' . $this->hook_manage, array(&$this, 'admin_print_scripts'));
	}

	//will be executed if wordpress core detects this page has to be rendered
	function admin_load_page() {
		//ensure, that the needed javascripts been loaded to allow drag/drop, expand/collapse and hide/show of boxes
		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');
		wp_enqueue_script('thickbox');
		wp_enqueue_script('jquery-ui-tabs');

		//add several metaboxes now, all metaboxes registered during load page can be switched off/on at "Screen Options" automatically, nothing special to do therefore
		// add_meta_box('cjtoolbox-contentbox-1', 'CSS & JavaScript Block 1', array(&$this, 'on_contentbox_1_content'), $this->hook_manage, 'normal', 'core');
	}

	 // Apply our stylesheet 
	function admin_print_styles() {
		wp_enqueue_style('thickbox');
		wp_enqueue_style( 'cjtoolbox', CJTOOLBOX_URL . 'media/admin.css', '', CJTOOLBOX_VERSION, 'all' );
	}

	// Add javascripts
	function admin_print_scripts() {
		wp_enqueue_style('jquery');
		wp_print_scripts('jquery');
?>
<script type="text/javascript" >
jQuery(document).ready(function($) {
	jQuery('form#cjtoolbox_form').submit(function() {
		var data = jQuery(this).serialize();
		jQuery('#cj-ajax-load').fadeIn();
		jQuery.post(ajaxurl, data, function(response) {
			var success = jQuery('#cj-popup-save');
			var loading = jQuery('#cj-ajax-load');
			loading.fadeOut();  
			success.fadeIn();
			window.setTimeout(function(){
			   success.fadeOut(); 
			}, 3000);
		});
		return false;
	});

	//Update Message popup
	jQuery.fn.center = function () {
		this.animate({"top":( jQuery(window).height() - this.height() - 200 ) / 2+jQuery(window).scrollTop() + "px"}, 50);
		this.animate({"left":( jQuery(window).width() - this.width() - 200 ) / 2 + "px"}, 50);
		return this;
	}

	// Update loading
	jQuery.fn.loadingcenter = function () {
		this.animate({"top":( jQuery(window).height() - this.height() - 60 ) / 2 + jQuery(window).scrollTop() + "px"}, 50);
		this.animate({"left":( jQuery(window).width() - this.width() - 60 ) / 2 + "px"}, 50);
		return this;
	}


	jQuery('#cj-popup-save').center();
	jQuery('#cj-popup-reset').center();
	jQuery('#cj-ajax-load img').loadingcenter();
	jQuery(window).scroll(function() { 
		jQuery('#cj-popup-save').center();
		jQuery('#cj-popup-reset').center();
		jQuery('#cj-ajax-load img').loadingcenter();
	});

	jQuery('#cjtoolbox-addblock').click(function(e) {
		var count = jQuery('#cjblock-count').val();
		var security = jQuery('#cjsecurity').val();
		var data = {
			action : 'cjtoolbox_add_block',
			count : count,
			security : security,
		};
		jQuery('#cj-ajax-load').fadeIn();
		jQuery.post(ajaxurl, data, function(response) {
			var loading = jQuery('#cj-ajax-load');
			loading.fadeOut();
			if(response == '' || response == 0) {
				alert('Oops, unable to add CSS & JavaScript Block! Please try again!!!');
			} else {
				jQuery('#normal-sortables').append(response);
				jQuery(".meta-box-sortables").sortable('refresh');
				jQuery('#cjblock-count').val(parseInt(jQuery('#cjblock-count').val()) + 1);
			}
		});
		return false; // For link to behave inactive
	});

});

function insert_code(type, id) {
	var cid = jQuery('#cjtoolbox-'+type+'-'+id+ ' option:selected').val();
	var security = jQuery('#cjsecurity').val();
	var data = {
		action : 'cjtoolbox_get_code',
		type : type,
		id : cid,
		security : security,
	};
	jQuery('#cj-ajax-load').fadeIn();
	jQuery.post(ajaxurl, data, function(response) {
		var loading = jQuery('#cj-ajax-load');
		loading.fadeOut();
		if(response == '' || response == 0) {
			alert('Oops, unable to fetch selected '+type+' template! Please try again!!!');
		} else {
			jQuery('#cjcode-'+id).insertAtCaret(response);
		}
	});
	return false; // For link to behave inactive
}

function delete_code(type, id) {
	var sure = confirm('Are you sure? Selected template will be deleted permanently!!!');
	if(!sure) return false;

	var cid = jQuery('#cjtoolbox-'+type+'-'+id+ ' option:selected').val();
	var security = jQuery('#cjsecurity').val();
	var data = {
		action : 'cjtoolbox_delete_code',
		type : type,
		id : cid,
		security : security,
	};
	jQuery('#cj-ajax-load').fadeIn();
	jQuery.post(ajaxurl, data, function(response) {
		var loading = jQuery('#cj-ajax-load');
		loading.fadeOut();
		if(response == '' || response == 0) {
			alert('Oops, unable to delete selected '+type+' template! Please try again!!!');
		} else {
			alert('Selected '+type+' template deleted successfully!');
			jQuery('.cjtoolbox-'+type).each(function() {
				jQuery(this).find('option[value='+cid+ ']').remove();
			});
		}
	});
	return false; // For link to behave inactive
}

function delete_block(id) {
	var sure = confirm('Are you sure?');
	if(sure) {
			jQuery('#cjtoolbox-'+id).remove();
			alert('Please make sure to click "Save All Changes" to delete the block permanently!');

		// Reduce total block count by 1 NOTE: Not used now.
		// jQuery('#cjblock-count').val(parseInt(jQuery('#cjblock-count').val()) - 1);
	}
	return false; // For link to behave inactive
}

jQuery.fn.extend({
	insertAtCaret: function(myValue){
		return this.each(function(i) {
			if (document.selection) {
				this.focus();
				sel = document.selection.createRange();
				sel.text = myValue;
				this.focus();
			}
			else if (this.selectionStart || this.selectionStart == '0') {
				var startPos = this.selectionStart;
				var endPos = this.selectionEnd;
				var scrollTop = this.scrollTop;
				this.value = this.value.substring(0, startPos)+myValue+this.value.substring(endPos,this.value.length);
				this.focus();
				this.selectionStart = startPos + myValue.length;
				this.selectionEnd = startPos + myValue.length;
				this.scrollTop = scrollTop;
			} else {
				this.value += myValue;
				this.focus();
			}
		})
	}
});

</script>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#cjtoolbox_newcode').live('submit',
			function (event) {
				event.preventDefault();
				var title = $('#cjtoolbox_newcode #new_title').val();
				var code = $('#cjtoolbox_newcode #new_code').val();
				var type = $('#cjtoolbox_newcode #new_type').val();
				var security = $('#cjtoolbox_newcode #new_security').val();
				if(title == '') { alert('Please enter title for code!'); return false; }
				if(code == '') { alert('Please enter code to save!'); return false; }

				var data = {
					action : 'cjtoolbox_save_newcode',
					type : type,
					title : title,
					code : code,
					security : security,
				};
				jQuery('#cjtoolbox_popup .ajax-loading-img').fadeIn();
				jQuery.post(ajaxurl, data, function(response) {
					var loading = jQuery('#cjtoolbox_popup .ajax-loading-img');
					var lastid = parseInt(response);
					loading.fadeOut();
					if(lastid > 0) { 
						alert('New code saved successfully!!!');
						jQuery('.cjtoolbox-'+type).each(function() {
							jQuery(this).append( '<option value="'+lastid+'">'+title+'</option>' );
						});
					} else {
						alert('ISSUE: '+response);
					}
					tb_remove();
				});
				return false;
		});
	});

</script>
<?php
	}

	// Display the admin page for all options
	function admin_display() {
		$this->getData();
		$count = count($this->cjdata);

		/** RESET the metabox order to normal, we dont want to retain the order now. Just boxes with data are sufficient.*/
		$page = $this->hook_manage;
		$neworder = array();
		for($i = 1; $i <= $count; $i++) {
			$neworder[] = 'cjtoolbox-' . $i;
		}
		$order['normal'] = implode(',', $neworder);
		$user = wp_get_current_user();
		update_user_option($user->ID, "meta-box-order_$page", $order, true);
		/*Block END*/

		if($count <= 0) $count = 1; // Atleast have one block by default.
		for($i = 1; $i <= $count; $i++) {
			add_meta_box('cjtoolbox-'.$i, 'CSS & JavaScript Block #'.$i, array(&$this, 'cjtoolbox_unit'), $this->hook_manage, 'normal', 'core', $i - 1);
		}

		echo '<div id="cjtoolbox-admin" class="wrap">';
		echo '<div id="custom-icon" style="background: transparent url(\''. CJTOOLBOX_URL . '/media/CSS_JS_Toolbox_Icon.png\') no-repeat;" class="icon32"><br /></div>';

		switch ($_GET['page']){
			case 'cjtoolbox':
			default:
				echo '<h2>CSS & JavaScript Toolbox</h2>';
				$this->cjtoolbox_manage();
				break;
		}

		echo '</div>';
	}


	// Display the admin page to manage custom css and javscripts
	function cjtoolbox_manage() {
		$this->getData();
		$count = count($this->cjdata);

/*
print_r($this->cjdata);

echo "<pre>";
$page = $this->hook_manage;
$sorted = get_user_option( "meta-box-order_$page" );
print_r($sorted);
echo "</pre>";
*/
		?>

	<div id="cjtoolbox_donate">
		Like this plugin? Please support our work
		<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
			<input type="hidden" name="cmd" value="_s-xclick">
			<input type="hidden" name="hosted_button_id" value="VMXTA3838F6A8">
			<input type="image" src="<?php echo CJTOOLBOX_URL;?>/media/Donate_Button.png" border="0" name="submit" alt="Donate!">
			<img alt="" border="0" src="https://www.paypalobjects.com/en_AU/i/scr/pixel.gif" width="1" height="1">
		</form>
	</div>
	<div class="cj-save-popup" id="cj-popup-save">
		<div id="cj-save-save">Options Updated</div>
	</div>
	<div class="cj-save-popup" id="cj-popup-reset">
		<div id="cj-save-reset">Options Reset</div>
	</div>
	<div id="cj-ajax-load">
		<img src="<?php echo CJTOOLBOX_URL; ?>/media/ajax-loader.gif" class="ajax-loading-img ajax-loading-img-bottom" alt="Working..." />
	</div>


	<form id="cjtoolbox_form" action="admin-post.php" method="post">
		<?php wp_nonce_field('cjtoolbox'); ?>
		<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
		<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>
		<input type="hidden" name="action" value="cjtoolbox_save" />
		<input type="hidden" id="cjsecurity" name="security" value="<?php echo wp_create_nonce('cjtoolbox-admin');?>" />
		<input type="hidden" id="cjblock-count" name="count" value="<?php echo $count; ?>" />
		<div id="poststuff" class="metabox-holder">
			<div id="post-body">
				<?php do_meta_boxes($this->hook_manage, 'normal', $this->cjdata); ?>
			</div>
			<br class="clear"/>
			<div id="save_bar">
				<a class="button-secondary" id="cjtoolbox-addblock">Add New CSS/JS Block</a>
				<input type="submit" value="Save All Changes" class="button-primary" name="save" />
			</div>
		</div>
	</form>

	<div id="cjtoolbox-tips">
		<ul>
			<li>Note: CSS &amp; JavaScript Blocks with EMPTY code will not be saved!</li>
			<li><b>Warning!</b> Please make sure to validate added CSS &amp; JavaScript codes, the plugin doesn't do that for you!</li>
		</ul>
	</div>
	<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function($) {
			// close postboxes that should be closed
			$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
			// postboxes setup
			postboxes.add_postbox_toggles('<?php echo $this->hook_manage; ?>');
		});
		//]]>
	</script>
		<?php
	}


	function cjtoolbox_unit($data = '', $arg = '') {
		$boxid = -1; // Because block 1 might have some content...
		if($arg != '') {
			$boxid = $arg['args'];
		}
		$this->getData();

		?>

<div class="cjpageblock">
	<?php $this->show_page_list($boxid, $this->cjdata[$boxid]['page'], $this->cjdata[$boxid]['category']); ?>
	<div style="clear:both;"></div>
</div>
<div class="cjcontainer">
<div class="cjcodeblock">
	<div class="cssblock">
		<p class="cjtitle">CSS Template <?php $this->show_dropdown_box('css', $boxid);?></p>
		<p class="cjbutton"><a class="insert_code" title="Insert selected CSS Template" href="#" onclick="return insert_code('css', '<?php echo $boxid;?>');">Insert Code</a> <a class="delete_code" title="Delete selected CSS Template" href="#" onclick="return delete_code('css', '<?php echo $boxid;?>');">Delete Code</a> <a class="add_code thickbox" href="<?php echo get_option('siteurl'); ?>/wp-admin/admin-ajax.php?action=cjtoolbox_form&type=css&width=500&height=350" title="Add New CSS Code Template">New</a></p>
	</div>
	<div class="jsblock">
		<p class="cjtitle">JS Template <?php $this->show_dropdown_box('js', $boxid);?></p>
		<p class="cjbutton"><a class="insert_code" title="Insert selected JavaScript Template" href="#" onclick="return insert_code('js', '<?php echo $boxid;?>');">Insert Code</a> <a class="delete_code" title="Delete selected JavaScript Template" href="#" onclick="return delete_code('js', '<?php echo $boxid;?>');">Delete Code</a> <a class="add_code thickbox" href="<?php echo get_option('siteurl'); ?>/wp-admin/admin-ajax.php?action=cjtoolbox_form&type=js&width=500&height=350" title="Add New JavaScript Code Template">New</a></p>
	</div>
	<div class="datablock">
		<textarea cols="100" rows="12" name="cjtoolbox[<?php echo $boxid;?>][code]" id="cjcode-<?php echo $boxid;?>"><?php echo stripslashes($this->cjdata[$boxid]['code']);?></textarea>
	</div>
</div>
</div>
<div class="deleteblock">
	<p class="cjexample">Click for <a target="_blank" href="http://wipeoutmedia.com/wordpress-plugins/css-javascript-toolbox/" title="Click for Hints &amp; Tips"><strong>Hints &amp; Tips</strong></a></p>
	<a class="button-secondary" href="#" onclick="return delete_block('<?php echo ($boxid + 1);?>');">Delete This Block</a>
	<div style="clear:both;"></div>
</div>

	<?php
	}


	function show_page_list($boxid, $pages, $categories) {
	?>
<script type="text/javascript">
	jQuery(function() {
		jQuery( "#tabs-<?php echo $boxid;?>" ).tabs();
	});
</script>
<div id="tabs-<?php echo $boxid;?>">
	<ul>
		<li><a href="#tabs-<?php echo $boxid;?>-1">Pages</a></li>
		<li><a href="#tabs-<?php echo $boxid;?>-2">Categories</a></li>
		<li><a href="#tabs-<?php echo $boxid;?>-3">URL List</a></li>
	</ul>

	<div id="tabs-<?php echo $boxid;?>-1">
		<p>Add this CSS/JS code to ?</p>
		<ul class="pagelist">
			<li><label><input type="checkbox" name="cjtoolbox[<?php echo $boxid;?>][page][]" value="frontpage" <?php echo (is_array($pages) && in_array('frontpage', $pages)) ? 'checked="checked"' : ''; ?> /> Front Page</label> <a class="l_ext" target="_blank" href="<?php bloginfo('url');?>"></a></li>
			<li><label><input type="checkbox" name="cjtoolbox[<?php echo $boxid;?>][page][]" value="allposts" <?php echo (is_array($pages) && in_array('allposts', $pages)) ? 'checked="checked"' : ''; ?> /> All Posts</label></li>
			<li><label><input type="checkbox" name="cjtoolbox[<?php echo $boxid;?>][page][]" value="allpages" <?php echo (is_array($pages) && in_array('allpages', $pages)) ? 'checked="checked"' : ''; ?> /> All Pages</label></li>
			<?php $this->show_pages_with_checkbox($boxid, $pages); ?>
		</ul>
	</div>
	<div id="tabs-<?php echo $boxid;?>-2">
		<p>Add this CSS/JS code to category page?</p>
		<ul class="pagelist">
			<?php $this->show_taxonomy_with_checkbox($boxid, $categories); ?>
		</ul>
	</div>
	<div id="tabs-<?php echo $boxid;?>-3" class="linklist">
		<p>Add one URL per line (include http://)</p>
		<textarea cols="31" rows="9" name="cjtoolbox[<?php echo $boxid;?>][links]" id="cjcode-links-<?php echo $boxid;?>"><?php echo stripslashes($this->cjdata[$boxid]['links']);?></textarea>
	</div>
	
</div>
	<?php
	}


	// Copied from nav menu feature of WordPress
	function show_taxonomy_with_checkbox($boxid, $taxonomy_selected) {
		$taxonomy_name = 'category';
	    $args = array(
    	    'child_of' => 0,
	        'exclude' => '',
    	    'hide_empty' => false,
	        'hierarchical' => 1,
    	    'include' => '',
	        'include_last_update_time' => false,
    	    'number' => 9999,
    	    'order' => 'ASC',
	        'orderby' => 'name',
    	    'pad_counts' => false,
    	);

	    $terms = get_terms( $taxonomy_name, $args );

    	if ( ! $terms || is_wp_error($terms) ) {
			// No items
	        return;
    	}

	    $db_fields = false;
	    if ( is_taxonomy_hierarchical( $taxonomy_name ) ) {
    	    $db_fields = array( 'parent' => 'parent', 'id' => 'term_id' );
	    }
	
		$walker = new cj_Walker_Nav_Menu_Checklist( $db_fields, $boxid, 'category', $taxonomy_selected );
		$args['walker'] = $walker;

		echo walk_nav_menu_tree( array_map('wp_setup_nav_menu_item', $terms), 0, (object) $args );
	}

	function show_pages_with_checkbox($boxid, $pages_selected) {

		$post_type_name = 'page';
		$args = array(
			'order' => 'ASC',
			'orderby' => 'title',
			'posts_per_page' => 9999,
			'post_type' => $post_type_name,
			'suppress_filters' => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false
		);

		// @todo transient caching of these results with proper invalidation on updating of a post of this type
		$get_posts = new WP_Query;
		$posts = $get_posts->query( $args );
		if ( ! $get_posts->post_count ) {
			// No items
			return;
		}

		$db_fields = false;
		if ( is_post_type_hierarchical( $post_type_name ) ) {
			$db_fields = array( 'parent' => 'post_parent', 'id' => 'ID' );
		}

		$walker = new cj_Walker_Nav_Menu_Checklist( $db_fields, $boxid, 'page', $pages_selected );
		$post_type_object = get_post_type_object($post_type_name);

		$args['walker'] = $walker;

		$checkbox_items = walk_nav_menu_tree( array_map('wp_setup_nav_menu_item', $posts), 0, (object) $args );
		echo $checkbox_items;
	}

	//executed if the post arrives initiated by pressing the submit button of form
	function on_save_changes() {
		//user permission check
		if ( !current_user_can('manage_options') )
			wp_die( __('Cheatin&#8217; uh?') );
		//cross check the given referer
		check_admin_referer('cjtoolbox');

		// TODO: This is not coded now because we use AJAX. Should be coded to make the plugin work even if javascript is disabled.
		//process here your on $_POST validation and / or option saving
		//print_r($_POST);
		//die();

		//lets redirect the post request into get request (you may add additional params at the url, if you need to show save results
		wp_redirect(add_query_arg( array('updated' => 'true'), $_POST['_wp_http_referer']));
	}


	function ajax_add_block() {
		check_ajax_referer('cjtoolbox-admin', 'security');
		$count = (int) $_POST['count'];
		if($count == 0) $count = 1;
		$args = array();
		$args['args'] = $count;
?>
<div id="cjtoolbox-<?php echo $count+1; ?>" class="postbox">
	<div class="handlediv" title="Click to toggle"><br /></div><h3 class='hndle'><span>CSS & JavaScript Block #<?php echo $count+1; ?></span></h3>
	<div class="inside">
		<?php $this->cjtoolbox_unit('', $args); ?>
	</div>
</div>
<?php
		die();
	}

	function ajax_save_newcode() {
		check_ajax_referer('cjtoolbox-popup', 'security');

		// Add new row to cjdata table
		$type = $_POST['type'];
		$title = $_POST['title'];
		$code = $_POST['code'];
		$response = $this->add_cjdata($type, $title, $code);
		if($response != false) {
			die($response);
		}
		die('Oops, unable to save '.$type.' code template! Please try again!!!');
	}


	function ajax_save_changes() {

		check_ajax_referer('cjtoolbox-admin', 'security');
		if($_POST['action'] == 'cjtoolbox_save') {
			// Save data and return 1 on success
			$cjdata = $_POST['cjtoolbox'];
			foreach($cjdata as $key => $value) {
				if($value['code'] == '') {
					unset($cjdata[$key]);
				}
			}
			$this->cjdata = $cjdata;
			$this->saveData();
			return 1; die('1');
		}
		return 0; // Something wrong!
		die(); // this is required to return a proper result
	}

	function ajax_delete_code() {
		check_ajax_referer('cjtoolbox-admin', 'security');
		$type = $_POST['type'];
		$id = (int) $_POST['id'];
		if($id <=0 || ($type != 'js' && $type != 'css')) return 'Invalid Request: Unable to process the request!';

		$this->delete_cjdata($type, $id);
		die('1');
	}

	function ajax_get_code() {
		check_ajax_referer('cjtoolbox-admin', 'security');
		$type = $_POST['type'];
		$id = (int) $_POST['id'];
		if($id <=0 || ($type != 'js' && $type != 'css')) return 'Invalid Request: Unable to process the request!';

		$code = $this->get_cjdata($type, $id);
		die($code);
	}

	function ajax_show_form() {
		$type = '';
		switch($_GET['type']) {
			case 'js':
				$type = 'js';
				break;
			case 'css':
			default:
				$type = 'css';
				break;
		}
		?>
	<div id="cjtoolbox_popup">
		<form id="cjtoolbox_newcode" action="admin-post.php" method="post">
			<input id="new_type" type="hidden" name="new_type" value="<?php echo $type;?>" />
			<input id="new_security" type="hidden" name="security" value="<?php echo wp_create_nonce('cjtoolbox-popup');?>" />
			<p><label>Title <input type="text" id="new_title" name="new_title" value="" size="59" /></label></p>
			<p><label>Code <textarea cols="57" id="new_code"rows="9" name="new_code"></textarea></label></p>
			<input type="submit" name="submit" value="Save Code Template" />
			<img style="display:none;" src="<?php echo CJTOOLBOX_URL; ?>/media/loading-bottom.gif" class="ajax-loading-img ajax-loading-img-bottom" alt="Working..." />
		</form>
	</div>
		<?php
		die();
	}

	function show_dropdown_box($type, $boxid) {
		global $wpdb;

		$query = $wpdb->prepare("SELECT id, title FROM {$wpdb->prefix}cjtoolbox_cjdata WHERE type = '{$type}'");
		$list = $wpdb->get_results($query);
		if(count($list)) {
			echo '<select id="cjtoolbox-'.$type.'-'.$boxid.'" class="cjtoolbox-'.$type.'">';
			foreach($list as $def) {
				echo '<option value="' . $def->id . '">'. $def->title . '</option>';
			}
			echo '</select>';
		}
	}

	function add_cjdata($type, $title, $code) {
		global $wpdb;

		if($type == '' || $title == '' || $code == '') return false;
		$query = $wpdb->prepare("INSERT INTO {$wpdb->prefix}cjtoolbox_cjdata (type,title,code) VALUES ('%s', '%s', '%s')", $type, $title, $code);
		$wpdb->query($query);
		// Get inserted id
		$lastid = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}cjtoolbox_cjdata ORDER BY id DESC LIMIT 0,1");
		return $lastid;
	}

	function delete_cjdata($type, $id) {
		global $wpdb;
		if($type == '' || $id <= 0) return false;
		$query = $wpdb->prepare("DELETE FROM {$wpdb->prefix}cjtoolbox_cjdata WHERE type = '%s' AND id = '%d' LIMIT 1", $type, $id);
		$wpdb->query($query);
		return true;
	}

	function get_cjdata($type, $id) {
		global $wpdb;

		if($type == '' || $id <= 0) return false;
		$query = $wpdb->prepare("SELECT code FROM {$wpdb->prefix}cjtoolbox_cjdata WHERE type = '%s' AND id = '%d' LIMIT 1", $type, $id);
		$code = $wpdb->get_var($query);
		return $code;
	}

	function activate_plugin() {
		global $wpdb;
		$database_version = CJTOOLBOX_VERSION;
		$installed_db = get_option('cjtoolbox_db_version');
		if($database_version != $installed_db) {
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			// Create the table structure
			$sql = "CREATE TABLE `{$wpdb->prefix}cjtoolbox_cjdata` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
					`type` VARCHAR( 15 ) NOT NULL ,
					`title` TINYTEXT NOT NULL ,
					`code` MEDIUMTEXT NOT NULL ,
					PRIMARY KEY ( `id` , `type` )
					)";
			dbDelta($sql);
			update_option( "cjtoolbox_db_version", $database_version );

			// Add sample code
			$count = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}cjtoolbox_cjdata WHERE type='css'");
			if($count == 0) {
				$wpdb->query("INSERT INTO {$wpdb->prefix}cjtoolbox_cjdata (type,title,code) VALUES ('css','Inline CSS Declaration','<style type=\"text/css\">\n\n</style>')");
				$wpdb->query("INSERT INTO {$wpdb->prefix}cjtoolbox_cjdata (type,title,code) VALUES ('css','External Stylesheet','<link rel=\"stylesheet\" type=\"text/css\" href=\"\"/>')");
			}

			$count = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}cjtoolbox_cjdata WHERE type='js'");
			if($count == 0) {
				$wpdb->query("INSERT INTO {$wpdb->prefix}cjtoolbox_cjdata (type,title,code) VALUES ('js','Inline JavaScript Declaration','<script type=\"text/javascript\">\n\n</script>')");
				$wpdb->query("INSERT INTO {$wpdb->prefix}cjtoolbox_cjdata (type,title,code) VALUES ('js','External JavaScript','<script type=\"text/javascript\" src=\"\"></script>')");
			}
		}
	}
}// END Class

// Let's start the plugin
global $cssJSToolbox;
$cssJSToolbox = new cssJSToolbox();

// Activation
register_activation_hook(__FILE__, array(&$cssJSToolbox, "activate_plugin"));
}

/** Following code copied from WordPress core */
/**
 * Create HTML list of nav menu input items.
 *
 * @package WordPress
 * @since 3.0.0
 * @uses Walker_Nav_Menu
 */
class cj_Walker_Nav_Menu_Checklist extends Walker_Nav_Menu  {
	function __construct( $fields = false, $boxid = 0, $type = 'page', $selected = array() ) {
		if ( $fields ) {
			$this->db_fields = $fields;
		}
		$this->boxid = $boxid;
		$this->selected = $selected;
		$this->type = $type;
	}

	function start_lvl( &$output, $depth ) {
		$indent = str_repeat( "\t", $depth );
		$output .= "\n$indent<ul class='children'>\n";
	}

	function end_lvl( &$output, $depth ) {
		$indent = str_repeat( "\t", $depth );
		$output .= "\n$indent</ul>";
	}

	/**
	 * @see Walker::start_el()
	 * @since 3.0.0
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $item Menu item data object.
	 * @param int $depth Depth of menu item. Used for padding.
	 * @param object $args
	 */
	function start_el(&$output, $item, $depth, $args) {

		$possible_object_id =  $item->object_id;

		$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';

		$output .= $indent . '<li>';
		$output .= '<label>';
		$output .= '<input type="checkbox" ';
		if ( ! empty( $item->_add_to_top ) ) {
			$output .= ' add-to-top';
		}
		$output .= ' name="cjtoolbox['.$this->boxid.']['.$this->type.'][]" value="'. esc_attr( $item->object_id ) .'" ';
		if(is_array($this->selected)) {
			$output .= in_array($item->object_id, $this->selected) ? 'checked="checked"' : '';
		}
		$output .= '/> ';
		$output .= empty( $item->label ) ? esc_html( $item->title ) : esc_html( $item->label );
		$permalink = '';
		if($this->type == 'category') {
			$permalink = get_category_link($item->object_id);
		} else {
			$permalink = get_permalink($item->object_id);
		}
		$output .= '</label> <a class="l_ext" target="_blank" href="'. $permalink .'"></a>';

	}
}
