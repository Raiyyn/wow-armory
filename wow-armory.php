<?php
/*
Plugin Name: WoW Armory
Plugin URI: http://www.marenkay.com/wordpress/wow-armory/
Description: WoW Armory is a plugin which allows to embed item and character information from the World of Warcraft armory.
Version: 1.0
Author: Daniel S. Reichenbach
Author URI: http://www.marenkay.com/
*/
/*  Copyright 2008  Daniel S. Reichenbach  (email : shiendra@marenkay.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

load_plugin_textdomain('wow_armory_plugin',PLUGINDIR . '/' . dirname(plugin_basename (__FILE__)) . '/lang');

/**
 * Include the phpArmory class to retrieve the XML from the Armory
 */
include_once(dirname(__FILE__) . '/phparmory/phpArmory.class.php');


if (!class_exists("WordPressArmoryCache")) {
    class WordPressArmoryCache extends phpArmory {
        
        /**
         * The mysql cache table
         *
         * @var string
         */
        var $dataTable = "armory_cache";

        /**
         * The time between cache updates in seconds
         *
         * @todo Make the interval able to be set for each thing (ie. update items once a week, update guilds once a day, etc..)
         *
         * @var integer
         */
        var $updateInterval = 14400;

        /**
         * Internal cache id of the current item
         *
         * @var integer
         */
        var $cacheID = 0;
        
        /**#@-*/
        /**
        * The Constructor
        *
        * This function is called when the object is created. It has
        * three optional parameters. The first sets the base url of
        * the Armory website that will be used to fetch the serialized
        * XML data. The second sets whether data will be stored in
        * flat files or a mysql database. The third indicates how
        * long a cached XML query should be kept before updating.
        *
        * @param string     $armoryArea     URL of the Armory website
        * @param integer    $retries        Time (in seconds) between cache updates
        */
        function WordPressArmoryCache($armoryArea = NULL, $retries = NULL) {

            global $wpdb;
                
            $table_name = $wpdb->prefix . $this->dataTable;

            if($wpdb->get_var("show tables like '$table_name'") != $table_name) {

                $sql = "CREATE TABLE IF NOT EXISTS `" . $table_name . "` (
                    `cache_id` VARCHAR(100) NOT NULL DEFAULT '',
                    `cache_time` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    `cache_xml` TEXT,
                    PRIMARY KEY  `cache_id` (`cache_id`))";

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);

            }
            
    		if ($armoryArea){
    			$this->phpArmory($armoryArea);
    		}
    		if ($retries){
    			$this->phpArmory($retries);
    		}
            
        }

        /**
        * characterFetch
        *
        * Attempts to fetch a cached version of the requested
        * character. Otherwise, it calls the parent function.
        *
        * @return string[]                  An associative array
        * @param string     $character      The name of the character
        * @param string     $realm          The character's realm
        * @author Claire Matthews <poeticdragon@stormblaze.net>
        */
        function characterFetch($character = NULL, $realm = NULL) {
            
            if(($character==NULL)&&($this->character)) $character = $this->character;
            if(($realm==NULL)&&($this->realm)) $realm = $this->realm;
            
            $this->cacheID = "c".md5($character.$realm);
            $cached = $this->cacheFetch($this->cacheID);

            if (!is_array($cached)) {
                $cached = parent::characterFetch($character, $realm);

                if ( $this->cacheID ) {
                    $scached = serialize($cached);
                    $this->cacheSave($this->cacheID, $scached);
                    unset($this->cacheID);
                }

                return $cached;
            }else{
                return $cached;
            }

        }
        
        /**
        * guildFetch
        * 
        * Attempts to fetch a cached version of the requested
        * guild. Otherwise, it calls the parent function.
        *
        * @return string[]                  An associative array
        * @param string     $guild          The name of the guild
        * @param string     $realm          The guild's realm
        * @author Claire Matthews <poeticdragon@stormblaze.net>
        */
        function guildFetch($guild = NULL, $realm = NULL) {
        
            if(($guild==NULL)&&($this->guild)) $guild = $this->guild;
            if(($realm==NULL)&&($this->realm)) $realm = $this->realm;
        
            $this->cacheID = "g".md5($guild.$realm);
            $cached = $this->cacheFetch($this->cacheID);

            if (!is_array($cached)) {
                $cached = parent::guildFetch($guild, $realm);

                if ( $this->cacheID ) {
                    $scached = serialize($cached);
                    $this->cacheSave($this->cacheID, $scached);
                    unset($this->cacheID);
                }

                return $cached;
            } else {
                return $cached;
            }
        
        }

        /**
        * itemFetch
        * 
        * Attempts to fetch a cached version of the requested
        * item. Otherwise, it calls the parent function.
        *
        * @return string[]                  An associative array
        * @param integer    $itemID         The ID of the item
        * @author Claire Matthews <poeticdragon@stormblaze.net>
        */
        function itemFetch($itemID) {
        
            $this->cacheID = "i".md5($itemID);
            $cached = $this->cacheFetch($this->cacheID);

            if (!is_array($cached)) {
                $cached = parent::itemFetch($itemID);

                if ( $this->cacheID ) {
                    $scached = serialize($cached);
                    $this->cacheSave($this->cacheID, $scached);
                    unset($this->cacheID);
                }

                return $cached;
            }else{
                return $cached;
            }
        
        }

        /**
        * itemNameFetch
        * 
        * Attempts to fetch a cached version of the requested
        * item search. Otherwise, it calls the parent function.
        *
        * @return string[]                  An associative array
        * @param string     $item           The name of the item
        * @param string[]   $filter         Associative array of search parameters
        * @author Claire Matthews <poeticdragon@stormblaze.net>
        */
        function itemNameFetch($item, $filter = NULL) {
        
            if ($filter&&is_array($filter)) {
                $this->cacheID = "s".md5($item.implode('', $filter));
            } else {
                $this->cacheID = "s".md5($item);
            }
            $cached = $this->cacheFetch($this->cacheID);

            if (!is_array($cached)) {
                $cached = parent::itemNameFetch($item, $filter);

                if ( $this->cacheID ) {
                    $scached = serialize($cached);
                    $this->cacheSave($this->cacheID, $scached);
                    unset($this->cacheID);
                }

                return $cached;
            }else{
                return $cached;
            }
        
        }

        /**
        * xmlFetch
        * 
        * This fetches the XML data as normal by calling
        * the parent function. If a cache id is set, it will
        * save the XML data to the cache and then unset the id.
        *
        * @param string     $url            URL of the page to fetch data from
        * @param string     $userAgent      The user agent making the GET request
        * @param integer    $timeout        The connection timeout in seconds
        * @author Claire Matthews <poeticdragon@stormblaze.net>
        */
        function xmlFetch($url, $userAgent = NULL, $timeout = NULL) {

            $xml = parent::xmlFetch($url, $userAgent, $timeout);

            /*  disabled.
             * if ( $this->cacheID ) {
             *     $this->cacheSave($this->cacheID, $xml);
             *     unset($this->cacheID);
             * }
             */

            return $xml;

        }

        /**
        * cacheFetch
        * 
        * This function returns the unserialized XML data
        * for the requested cache id from the cache. It
        * will also remove old cached files/rows that have
        * not been updated since the update interval.
        *
        * @return string[]                  An associative array
        * @param string     $cacheID        The ID of the cached thing
        * @author Claire Matthews <poeticdragon@stormblaze.net>
        */
        function cacheFetch($cacheID) {

            global $wpdb;
                
            $table_name = $wpdb->prefix . $this->dataTable;

            $query = "SELECT cache_xml, UNIX_TIMESTAMP(cache_time) AS cache_time FROM `".$table_name."` WHERE cache_id = '".$wpdb->escape($cacheID)."'";
            $results = $wpdb->get_results( $query );

            if ($result) {
                if (time()-mysql_result($result, 0, 'cache_time') > $this->updateInterval) {
                    $query = "DELETE FROM `".$table_name."` WHERE cache_id = '".$wpdb->escape($cacheID)."'";
                    $results = $wpdb->query( $query );
                } else {
                    // Return the cached XML as an array
                    return $this->xmlToArray(mysql_result($result, 0, 'cache_xml'));
                }
            }
        }

        /**
        * cacheSave
        * 
        * This function saves the given XML data to the
        * cache by its cache id.
        *
        * @param string     $cacheID        The ID of the cached thing
        * @param string     $xml            The XML info to be saved
        * @author Claire Matthews <poeticdragon@stormblaze.net>
        */
        function cacheSave($cacheID, $xml) {

            global $wpdb;
                
            $table_name = $wpdb->prefix . $this->dataTable;

            if (get_magic_quotes_gpc()) $xml = stripslashes($xml);

            $query = "REPLACE INTO `".$table_name."` (cache_id, cache_xml) VALUES('".$wpdb->escape($cacheID)."','".$wpdb->escape(xml)."')";
            $results = $wpdb->query( $query );
            
        }
        
    }
}

if (!class_exists("WoWArmoryPlugin")) {
    class WoWArmoryPlugin {

        var $adminOptionsName = "WoWArmoryPluginAdminOptions";

        function WoWArmoryPlugin() { //constructor
        }

        /**
         * addHeaderCode()
         *
         * Add the scripts and styles used for WoW Armory to the WordPress
         * header.
         *
         * @author Daniel S. Reichenbach <shiendra@marenkay.com>
         */
        function addHeaderCode() {

            $url = get_bloginfo('wpurl');
?>
<script type="text/javascript" src="<?php echo $url; ?>/wp-content/plugins/wow-armory/js/overlib.js"></script>
<link rel="stylesheet" href="<?php echo $url; ?>/wp-content/plugins/wow-armory/css/wow-armory.css" type="text/css" />
        <?php
        }

        /**
         * getAdminOptions()
         *
         * Returns an array of admin options.
         *
         * @author Daniel S. Reichenbach <shiendra@marenkay.com>
         */
        function getAdminOptions() {
            $wowarmoryAdminOptions = array(
                'wowarmory_area'            => 'en',
                'wowarmory_modify_content'  => 'true',
                'wowarmory_modify_comment'  => 'true');

            $wowarmoryOptions = get_option($this->adminOptionsName);

            if (!empty($wowarmoryOptions)) {
                foreach ($wowarmoryOptions as $key => $option)
                    $wowarmoryAdminOptions[$key] = $option;
            }

            update_option($this->adminOptionsName, $wowarmoryAdminOptions);

            return $wowarmoryAdminOptions;
        }

        /**
         * init()
         *
         * Plugin initialization.
         *
         * @author Daniel S. Reichenbach <shiendra@marenkay.com>
         */
        function init() {
            $this->getAdminOptions();
        }

        /**
         * printAdminPage()
         *
         * Prints out the admin page
         *
         * @author Daniel S. Reichenbach <shiendra@marenkay.com>
         */
        function printAdminPage() {
            $wowarmoryOptions = $this->getAdminOptions();

            if (isset($_POST['update_WoWArmoryPluginSettings'])) {
                if (isset($_POST['wowarmory_area'])) {
                    $wowarmoryOptions['wowarmory_area'] = $_POST['wowarmory_area'];
                }
                if (isset($_POST['wowarmory_modify_content'])) {
                    $wowarmoryOptions['wowarmory_modify_content'] = $_POST['wowarmory_modify_content'];
                }
                if (isset($_POST['wowarmory_modify_comment'])) {
                    $wowarmoryOptions['wowarmory_modify_comment'] = $_POST['wowarmory_modify_comment'];
                }

                update_option($this->adminOptionsName, $wowarmoryOptions);

                if ( !empty($_POST ) ) : ?>
        <div id="message" class="updated fade"><p><?php _e('Options saved.', 'wow_armory_plugin') ?></p></div>
<?php
                endif;
            
            } ?>
        <div class="wrap">
            <h2 id="write-post"><?php _e("WoW Armory Options&hellip;",'wow_armory_plugin');?></h2>
            <p><?php _e('WoW Armory plugin will retrieve character, guild, and item data from the <a href="http://www.wowarmory.com/">World of Warcraft armory</a>.','wow_armory_plugin');?></p>

<?php
            if (!function_exists("curl_init")) {
?>
            <p style="padding: .5em; background-color: #d22; color: #fff; font-weight: bold;"><?php _e("WordPress armory plugin can not be used. You will need to install CURL support for your PHP installation.",'wow_armory_plugin');?></p>
<?
            } else {
?>
            <p style="padding: .5em; background-color: #2d2; color: #fff; font-weight: bold;"><?php _e("WordPress armory plugin is configured and ready to grab data.",'wow_armory_plugin');?></p>
<?
            }
?>

            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo basename(__FILE__); ?>">
                <?php wp_nonce_field('update-options'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e("Region:",'wow_armory_plugin'); ?></th>
                        <td>
                            <select name="wowarmory_area" id="wowarmory_area">
                                <option <?php if ($wowarmoryOptions['wowarmory_area'] == "us") { echo 'selected'; } ?> value="us"><?php _e("US armory",'wow_armory_plugin'); ?></option>
                                <option <?php if ($wowarmoryOptions['wowarmory_area'] == "eu") { echo 'selected'; } ?> value="eu"><?php _e("EU armory",'wow_armory_plugin'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e("Pages and Posts:",'wow_armory_plugin'); ?></th>
                        <td>
                            <p><label for="wowarmory_modify_content_yes"><input type="radio" id="wowarmory_modify_content_yes" name="wowarmory_modify_content" value="true" <?php if ($wowarmoryOptions['wowarmory_modify_content'] == "true") { echo 'checked="checked"'; } ?> /> <?php _e("Yes",'wow_armory_plugin'); ?></label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="wowarmory_modify_content_no"><input type="radio" id="wowarmory_modify_content_no" name="wowarmory_modify_content" value="false" <?php if ($wowarmoryOptions['wowarmory_modify_content'] == "false") { echo 'checked="checked"'; } ?>/> <?php _e("No",'wow_armory_plugin'); ?></label></p>
                            <p><?php _e('Selecting <em>No</em> will disable the display of character and item links for posts and pages.','wow_armory_plugin'); ?></p>
                        </td> 
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e("Comments:",'wow_armory_plugin'); ?></th>
                        <td>
                            <p><label for="wowarmory_modify_comment_yes"><input type="radio" id="wowarmory_modify_comment_yes" name="wowarmory_modify_comment" value="true" <?php if ($wowarmoryOptions['wowarmory_modify_comment'] == "true") { echo 'checked="checked"'; } ?> /> <?php _e("Yes",'wow_armory_plugin'); ?></label>&nbsp;&nbsp;&nbsp;&nbsp;<label for="wowarmory_modify_comment_no"><input type="radio" id="wowarmory_modify_comment_no" name="wowarmory_modify_comment" value="false" <?php if ($wowarmoryOptions['wowarmory_modify_comment'] == "false") { echo 'checked="checked"'; } ?>/> <?php _e("No",'wow_armory_plugin'); ?></label></p>
                            <p><?php _e('Selecting <em>No</em> will disable the display of character and item links for comments.','wow_armory_plugin'); ?></p>
                        </td> 
                    </tr>
                </table>      
                <div class="submit">
                    <input type="submit" name="update_WoWArmoryPluginSettings" value="<?php _e('Update Options »') ?>" />
                </div>
            </form>
        </div>
<?php
        } // End function printAdminPage()

        /**
         * modifyContent()
         *
         * Parse the supplied WordPress content and insert character and item
         * links pointing to the armory. The actual parsing is relayed to
         * doParse().
         *
         * @param string    $content    WordPress page/post conent
         * @author Daniel S. Reichenbach <shiendra@marenkay.com>
         */
        function modifyContent($content = '') {
        
            $content = $this->doParse($content);
            return $content;
        }

        /**
         * modifyComment()
         *
         * Parse the supplied WordPress comment and insert character and item
         * links pointing to the armory. The actual parsing is relayed to
         * doParse().
         *
         * @param string    $content    WordPress page/post content
         * @author Daniel S. Reichenbach <shiendra@marenkay.com>
         */
        function modifyComment($content = '') {
        
            $content = $this->doParse($content);
            return $content;
        }

        /**
         * doParse()
         *
         * Parse the supplied content variable and insert character and item
         * links pointing to the armory.
         *
         * @param string    $content    WordPress page/post content
         * @author Daniel S. Reichenbach <shiendra@marenkay.com>
         */
        function doParse($content = '') {
        
            $wowarmoryOptions = $this->getAdminOptions();

            $armoryFetch = new WordPressArmoryCache();
            
            $armoryFetch->setArea($wowarmoryOptions['wowarmory_area;']);

            $itemRarity = array (
                            0 => "#5b5b5b",
                            1 => "#ffffff",
                            2 => "#007200",
                            3 => "#004385",
                            4 => "#5d1f88",
                            5 => "#c24e00",
                            6 => "#9c884d"
                            );
            $content = utf8_decode($content);
            
            // parse the content variable for all [item]...[/item] occurrences
            while (preg_match('#\[(item)(=[0-5])?\](.+?)\[/item\]#s', $content, $match)) {

                $itemName = $match[3];
                $iconSize = $match[2];
                $itemType = $match[1];

                $itemName = html_entity_decode($itemName, ENT_QUOTES);

                $item = $armoryFetch->itemNameFetch($itemName);
                
                if ($item) { // armory supplied a valid item xml object
                    $itemQuality = $item['itemtooltips']['itemtooltip']['overallqualityid'];
                    
                    $itemHtml = '<a style="font-weight: bold; color: '.$itemRarity[$itemQuality].';" href="'.$wowarmoryOptions['wowarmory_url'].'item-info.xml?i='.$item['itemtooltips']['itemtooltip']['id'].'">';
                    $itemHtml = $itemHtml . '<span onmouseover="return overlib(\'<table cellpadding=\\\'0\\\' border=\\\'0\\\' class=\\\'tooltip_new\\\'><tr><td><center><img src=\\\''.get_bloginfo('wpurl').'/'.PLUGINDIR.'/'.dirname(plugin_basename (__FILE__)).'/images/ajax-loader.gif\\\' border=\\\'0\\\' align=\\\'Loading...\\\' /><br />Searching...please wait.</center></td></tr></table>\',VAUTO,HAUTO,FULLHTML);" onmouseout="return nd();">';
                    $itemHtml = $itemHtml . $item['itemtooltips']['itemtooltip']['name'];
                    $itemHtml = $itemHtml . '</span>';
                    $itemHtml = $itemHtml . '</a>';

                } else { // armory did not supply a valid xml object
                    $itemHtml = '<a style="font-weight: bold; color: '.$itemRarity[0].';" href="'.$wowarmoryOptions['wowarmory_url'].'search.xml?searchQuery='.$itemName.'&searchType=items">';
                    $itemHtml = $itemHtml . '<span onmouseover="return overlib(\'<table cellpadding=\\\'0\\\' border=\\\'0\\\' class=\\\'tooltip_new\\\'><tr><td><center><img src=\\\''.get_bloginfo('wpurl').'/'.PLUGINDIR.'/'.dirname(plugin_basename (__FILE__)).'/images/ajax-loader.gif\\\' border=\\\'0\\\' align=\\\'Loading...\\\' /><br />Item not found. Click to search the armory.</center></td></tr></table>\',VAUTO,HAUTO,FULLHTML);" onmouseout="return nd();">';
                    $itemHtml = $itemHtml . $itemName;
                    $itemHtml = $itemHtml . '</span>';
                    $itemHtml = $itemHtml . '</a>';

                }
                
                // replace the itemname with an armory link
                $content = str_replace($match[0], $itemHtml, $content);

            }

            return $content;
        }

        /**
         * itemTooltip()
         *
         * Parse the supplied item variable and generate a HTML tooltip.
         *
         * @param       array           $item       WordPress page/post conent
         * @return      string          $itemHtml   Generated HTML tooltip
         * @author Daniel S. Reichenbach <shiendra@marenkay.com>
         */
        function itemTooltip($item = array()) {
        
            return;
        }

    }

} //End Class WoWArmoryPlugin

if (class_exists("WoWArmoryPlugin")) {
    $wowarmory_plugin = new WoWArmoryPlugin();
}

//Initialize the admin panel
if (!function_exists("WoWArmoryPlugin_ap")) {
	function WoWArmoryPlugin_ap() {
		global $wowarmory_plugin;
		if (!isset($wowarmory_plugin)) {
			return;
		}
		if (function_exists('add_submenu_page')) {
            add_submenu_page('plugins.php', __('WoW Armory Settings'), __('WoW Armory'), 'manage_options', basename(__FILE__), array(&$wowarmory_plugin, 'printAdminPage'));
		}
	}	
}

//Actions and Filters
if (isset($wowarmory_plugin)) {
	//Actions
	add_action('admin_menu', 'WoWArmoryPlugin_ap');
	add_action('wp_head', array(&$wowarmory_plugin, 'addHeaderCode'), 1);
	add_action('activate_wow-armory/wow-armory.php',  array(&$wowarmory_plugin, 'init'));

	//Filters
	add_filter('the_content', array(&$wowarmory_plugin, 'modifyContent'));
	add_filter('comment_text',array(&$wowarmory_plugin, 'modifyComment'), 35);
}

?>
