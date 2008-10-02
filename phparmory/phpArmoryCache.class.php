<?php
/**
 * phpArmoryCache is an embeddable class to retrieve XML data from the WoW armory.
 * 
 * phpArmoryCache is an embeddable PHP5 class, which allow you to fetch XML data
 * from the World of Warcraft armory in order to display arena teams,
 * characters, guilds, and items on a web page. It can cache armory data in flat
 * files or in a MySQL database.
 * @author Michael Cotterell <mepcotterell@gmail.com>
 * @author Daniel S. Reichenbach <daniel.s.reichenbach@mac.com>
 * @copyright Copyright (c) 2007, Michael Cotterell
 * @copyright Copyright (c) 2008, Daniel S. Reichenbach
 * @license http://www.opensource.org/licenses/gpl-3.0.html GNU General Public License version 3
 * @link https://github.com/marenkay/phparmory/tree
 * @package phpArmory
 * @version 0.3.2
 */

/**
 * phpArmoryCache extends phpArmory, thus we require the base class file.
 */
require_once('phpArmory.class.php');

/**
 * phpArmoryCache class
 * 
 * A class to fetch and cache unserialized XML data from the World of Warcraft
 * armory site.
 * @package phpArmory
 * @subpackage classes
 */
class phpArmoryCache extends phpArmory {
    
    /**
     * Data storage format ("flat" or "mysql")
     *
     * @var string
     */
    var $dataStore = "mysql";

    /**
     * The path to the cache directory (must chmod 777)
     *
     * @var string
     */
    var $dataPath = "../cache";

    /**
     * The mysql connection string
     *
     * @var string
     */
    var $dataConn = "mysql://user:pass@localhost/test";

    /**
     * The mysql cache table
     *
     * @var string
     */
    var $dataTable = "armory_cache";

	/**
	 * Number of retries for downloading
	 *
	 * @var integer
	 */
	var $retries = 5;

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
    * @param string     $armory         URL of the Armory website
    * @param string     $dataStore      "flat" or "mysql"
    * @param string     $dataConn       e.g. "mysql://username:password@localhost/database"
    * @param string     $dataTable      Name of the database table to use
    * @param integer    $retries        Time (in seconds) between cache updates
    */
    function phpArmoryCache($armory = NULL, $dataStore = NULL, $dataConn = NULL, $dataTable = NULL, $retries = NULL) {
        
        if(($retries==NULL)&&($this->retries)){
            $retries = $this->retries;
        } else {
            $this->retries = $retries;
        }
        
        if(($dataStore==NULL)&&($this->dataStore)){
            $dataStore = $this->dataStore;
        } else {
            $this->dataStore = $dataStore;
        }
        
        if(($dataConn==NULL)&&($this->dataConn)){
            $dataConn = $this->dataConn;
        } else {
            $this->dataConn = $dataConn;
        }
        
        if(($dataTable==NULL)&&($this->dataTable)){
            $dataTable = $this->dataTable;
        } else {
            $this->dataTable = $dataTable;
        }
        
        switch($this->dataStore) {
        
            case 'flat':
                break;
            
            case 'mysql':
                $conn = @parse_url($this->dataConn);
                $this->dataConn = mysql_connect($conn['host'], $conn['user'], $conn['pass']) or die("Failed to connect to database");
                mysql_select_db(str_replace('/', '', $conn['path']), $this->dataConn) or die("Unable to select database table");

                $query = "CREATE TABLE IF NOT EXISTS `".$this->dataTable."` (
                            `cache_id` VARCHAR(100) NOT NULL DEFAULT '',
                            `cache_time` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                            `cache_xml` TEXT,
                            PRIMARY KEY `cache_id` (`cache_id`))";
                mysql_query($query, $this->dataConn) or die("Unable to create the cache table");
                
                break;
            
            default:
                die("Invalid dataStore defined."); 
                break;
        
        }
        
        $this->phpArmory($armory);
        
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
    function characterFetch($character = NULL, $realm = NULL){
        
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
    function guildFetch($guild = NULL, $realm = NULL){
    
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
    function itemFetch($itemID){
    
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
    function xmlFetch($url, $userAgent = NULL, $timeout = NULL){

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

        switch($this->dataStore){
        
            case "flat":
                $filename = $this->dataPath."/".$cacheID;
                if (file_exists($filename)) {
                    if (time()-filemtime($filename) > $this->updateInterval) {
                        // Cache is out of date, remove the old file
                        @unlink($filename);
                    } else {
                        // Return the cached XML as an array
                        $array = unserialize(file_get_contents($filename));
                        return $array;
                    }
                }
                break;
                
            case "mysql":
                $query = "SELECT cache_xml, UNIX_TIMESTAMP(cache_time) AS cache_time FROM `".$this->dataTable."` WHERE cache_id = '".$cacheID."'";
                $result = mysql_query($query, $this->dataConn) or die("Unable to select cache from database");
                if ($result && mysql_num_rows($result)) {
                    if (time()-mysql_result($result, 0, 'cache_time') > $this->updateInterval) {
                        $query = "DELETE FROM `".$this->dataTable."` WHERE cache_id = '".$cacheID."'";
                        mysql_query($query, $this->dataConn);
                    } else {
                        // Return the cached XML as an array
                        return $this->xmlToArray(mysql_result($result, 0, 'cache_xml'));
                    }
                }
                break;
        
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

        switch($this->dataStore){
        
            case "flat":
                $filename = $this->dataPath."/".$cacheID;
                $handle = fopen($filename, 'x') or die("Cannot open file ($filename)");
                fwrite($handle, $xml) or die("Cannot write to file ($filename)");
                fclose($handle);
                break;
                
            case "mysql":
                if (get_magic_quotes_gpc()) $xml = stripslashes($xml);
                $xml = mysql_escape_string($xml);
                $query = "REPLACE INTO `".$this->dataTable."` (cache_id, cache_xml) VALUES('".$cacheID."','".$xml."')";
                mysql_query($query, $this->dataConn) or die("Unable to save to database " . mysql_error());
                break;
        
        }
        
    }

    
    /**#@-*/
    
}

?>