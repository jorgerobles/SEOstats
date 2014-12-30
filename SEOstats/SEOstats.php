<?php
namespace SEOstats;

use SEOstats\Common\SEOstatsException as E;
use SEOstats\Config as Config;
use SEOstats\Helper as Helper;
use SEOstats\Services as Service;

/** SEOstats
 *  ================================================================================
 *  PHP library to request a bunch of SEO-relevant metrics, such as looking up the
 *  visibilty of a URL within organic search results, Pagespeed analysis, the
 *  Google Toolbar PageRank, Page-Authority, Backlink-Details, Traffic Statistics,
 *  social media relevance, comparing competing websites and a lot more.
 *  ================================================================================
 *  @package     SEOstats
 *  @author      Stephan Schmitz <eyecatchup@gmail.com>
 *  @copyright   Copyright (c) 2010 - present Stephan Schmitz
 *  @license     http://eyecatchup.mit-license.org
 *  @version     CVS: $Id: SEOstats.php, v2.5.2 Rev 31 2013/08/14 13:57:17 ssc Exp $
 *  @link        https://github.com/eyecatchup/SEOstats/
 *  ================================================================================
 *  LICENSE: Permission is hereby granted, free of charge, to any person obtaining
 *  a copy of this software and associated documentation files (the "Software'),
 *  to deal in the Software without restriction, including without limitation the
 *  rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *    The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY
 *  WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 *  CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *  ================================================================================
 */

/**
 * Check required PHP settings.
 */
if (!function_exists('curl_init')) {
    throw new E('SEOstats requires the PHP CURL extension.');
    exit();
}

if (1 == ini_get('safe_mode') || 'on' === strtolower(ini_get('safe_mode'))) {
    throw new E('Because some SEOstats functions require the CURLOPT_FOLLOWLOCATION flag, ' .
        'you must not run PHP in safe mode! (This flag can not be set in safe mode.)');
    exit();
}

/**
 * Starting point for the SEOstats library. Example Usage:
 *
 * <code>
 * ...
 * $url = 'http://www.domain.tld';
 *
 * // Get the Google Toolbar PageRank value.
 * $result = \SEOstats\Services\Google::getPageRank($url);
 *
 * // Get the first 100 results for a Google search for 'query string'.
 * $result = \SEOstats\Services\Google::getSerps('query string');
 *
 * // Get the first 500 results for a Google search for 'query string'.
 * $result = \SEOstats\Services\Google::getSerps('query string', 500);
 *
 * // Check the first 500 results for a Google search for 'query string' for
 * // occurrences of the given domain name and return an array of matching
 * // URL's and their position within the serps.
 * $result = \SEOstats\Services\Google::getSerps('query string', 500, $url);
 * ...
 * </code>
 *
 */
class SEOstats
{
    const BUILD_NO = Config\Package::VERSION_CODE;

    protected static $_url,
                     $_host,
                     $_lastHtml,
                     $_lastLoadedUrl
                     = false;

    // To acquire an API key, visit Google's APIs Console here:
    //      https://code.google.com/apis/console
    // In the Services pane, activate the "PageSpeed Insights API" (not the service!).
    // Next, go to the API Access pane. The API key is near the bottom of that pane,
    // in the section titled "Simple API Access.".
    public static $google_simple_api_access_key;

    // To acquire a Mozscape (f.k.a. SEOmoz) API key, visit:
    //      https://moz.com/products/api/keys
    public static $mozscape_access_id;
    public static $mozscape_secret_key;

    // to acquire a sistrix api key, visit:
    //      http://www.sistrix.de
    public static $sistrix_api_access_key;

    public function __construct($url = false, $google_simple_api_access_key='', $mozscape_access_id='', $mozscape_secret_key='', $sistrix_api_access_key='')
    {
        if (false !== $url) {
            self::setUrl($url);
        }
        self::$google_simple_api_access_key = $google_simple_api_access_key;
        self::$mozscape_access_id = $mozscape_access_id;
        self::$mozscape_secret_key = $mozscape_secret_key;
        self::$sistrix_api_access_key = $sistrix_api_access_key;
    }

    public function Alexa()
    {
        return new Service\Alexa;
    }

    public function Google()
    {
        return new Service\Google;
    }

    public function Mozscape()
    {
        return new Service\Mozscape;
    }

    public function OpenSiteExplorer()
    {
        return new Service\OpenSiteExplorer;
    }

    public function SEMRush()
    {
        return new Service\SemRush;
    }

    public function Sistrix()
    {
        return new Service\Sistrix;
    }

    public function Social()
    {
        return new Service\Social;
    }

    public static function getGoogleSimpleApiAccessKey()
    {
        return self::$google_simple_api_access_key;
    }

    public static function getMozscapeAccessId()
    {
        return self::$mozscape_access_id;
    }

    public static function getMozscapeSecretKey()
    {
        return self::$mozscape_secret_key;
    }

    public static function getSistrixApiAccessKey()
    {
        return self::$sistrix_api_access_key;
    }

    public static function getLastLoadedHtml()
    {
        return self::$_lastHtml;
    }

    public static function getLastLoadedUrl()
    {
        return self::$_lastLoadedUrl;
    }

    /**
     * Ensure the URL is set, return default otherwise
     * @return string
     */
    public static function getUrl($url = false)
    {
        $url = false !== $url ? $url : self::$_url;
        return $url;
    }

    public function setUrl($url)
    {
        if (false !== Helper\Url::isRfc($url)) {
            self::$_url  = $url;
            self::$_host = Helper\Url::parseHost($url);
        }
        else {
            throw new E('Invalid URL!');
            exit();
        }
        return true;
    }

    public static function getHost($url = false)
    {
        return Helper\Url::parseHost(self::getUrl($url));
    }
        
    public static function getDomain($url = false)
    {
        return 'http://' . self::getHost($url = false);
    }

    /**
     * @return DOMDocument
     */
    protected static function _getDOMDocument($html) {
        $doc = new \DOMDocument;
        @$doc->loadHtml($html);
        return $doc;
    }

    /**
     * @return DOMXPath
     */
    protected static function _getDOMXPath($doc) {
        $xpath = new \DOMXPath($doc);
        return $xpath;
    }

    /**
     * @return HTML string
     */
    protected static function _getPage($url) {
        $url = self::getUrl($url);
        if (self::getLastLoadedUrl() == $url) {
            return self::getLastLoadedHtml();
        }

        $html = Helper\HttpRequest::sendRequest($url);
        if ($html) {
            self::$_lastLoadedUrl = $url;
            self::_setHtml($html);
            return $html;
        }
        else {
            self::noDataDefaultValue();
        }
    }

    protected static function _setHtml($str)
    {
        self::$_lastHtml = $str;
    }

    protected static function noDataDefaultValue()
    {
        return Config\DefaultSettings::DEFAULT_RETURN_NO_DATA;
    }
}
