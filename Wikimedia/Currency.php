<?php
/**
 * Contains the Currency object.
 *
 * @author Matthew Kosolofski
 * @package Website
 */

/**
 * Contains methods for dealing with and maintaining currency.
 *
 * @package Website
 */
class Currency
{
    /**#@+
     * Constants for connection to currency site that contains the new exchange rates. 
     */
    const SOURCE_SITE = 'https://www.rates.com/cgi-bin/xml?currency=all';
    const CONNECTION_TIMEOUT = 5;
    /**#@-*/

    /**
     * Updates the exchange_rates table with the latest exchange rates.
     * 
     * @throws Exception Failed to update exchange_rates table.
     */
    public function updateExchangeRates()
    {
        $sqlData = array();
        foreach($this->_getNewExchangeRates() as $exchangeRate) {
            $sqlData[] = '("' . mysql_real_escape_string($exchangeRate[1]) . '", "' .
                mysql_real_escape_string($exchangeRate[2]) . '")';
        }
        
        // Assumes currency is a primary key in the exchange_rates table.
        $sql = 'INSERT INTO exchange_rates (`currency`, `rate`) VALUES ' . implode(',', $sqlData) .
            ' ON DUPLICATE KEY UPDATE `currency` = VALUES(`currency`), `rate` = VALUES(`rate`)';
        
        if (mysql_query($sql) === false) {
            throw new Exception(__FUNCTION__ . ' - Failed to update exchange_rates table. Error: ' . mysql_error());
        }
    }

    /**
     * Converts a given currency to USD.
     * 
     * @param string $currency The currency to convert.
     * @throws Exception Failed to convert currency.
     * @return string The converted currency.
     */
    public function convertSingle($currency)
    {
        if (!is_string($currency)) {
            throw new Exception(__FUNCTION__ . ' - Invalid $currency parameter. Expected a string.');
        }
        
        list($currencyName, $currencyAmount) = explode(' ', $currency);
        if (!is_string($currencyName) || !is_numeric($currencyAmount)) {
            throw new Exception(__FUNCTION__ . ' - Invalid currency ' . $currency);
        }

        if ($currencyName == 'USD') {
            return $currency;
        }

        $result = mysql_query(
            'SELECT
                `rate`
             FROM
                `exchange_rates`
             WHERE
                `currency` = "' . mysql_real_escape_string($currencyName) . '"'
        );
        if ($result === false) {
            throw new Exception(__FUNCTION__ . ' - Failed to get exchange rate. Error: ' . mysql_error());
        }
        
        $row = mysql_fetch_object($result);
        if ($row === false) {
            throw new Exception(__FUNCTION__ . ' - Exchange rate was not found for given currency ' . $currency);
        }
        return 'USD ' . ($row->rate * $currencyAmount);
    }

    /**
     * Converts an array of currencies to USD.
     *
     * @param array $currencies A numerically indexed array of currencies to convert.
     * @throws Exception Failed to convert currencies.
     * @return array The converted currencies.
     */
    public function convertMultiple($currencies)
    {
        if (!is_array($currencies)) {
            throw new Exception(__FUNCTION__ . ' - Invalid $currencies parameter. Expected an array.');
        }

        $converted = array();
        foreach($currencies as $currency) {
            $converted[] = $this->convertSingle($currency);
        }
        
        return $converted;
    }

    /**
     * Gets the latest exchange rates from self::SOURCE_SITE
     * Example return:
     * <pre>
     *     array(
     *         array(
     *             '<currency>BGN</currency> <rate>0.6707</rate>'
     *             'BGN',
     *             '0.6707'
     *         ),
     *         array(
     *             '<currency>CZK</currency> <rate>0.05190</rate>',
     *             'CZK',
     *             '0.05190'
     *         )
     *     )
     * </pre> 
     *
     * @throws Exception Failed to retreive exchange rates.
     * @return array The new exchange rates.
     */
    protected function _getNewExchangeRates()
    {
        // Request the new exchange rates.
        $curlHandle = curl_init();
        curl_setopt_array(
            $curlHandle,
            array(
                CURLOPT_URL => self::SOURCE_SITE,    
                CURLOPT_CONNECTTIMEOUT => self::CONNECTION_TIMEOUT,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_HEADER => false,
                CURLOPT_FORBID_REUSE => true,
                CURLOPT_RETURNTRANSFER => true
            ) 
        );
        
        $curlResult = curl_exec($curlHandle);
        if ($curlResult === false) {
            throw new Exception(
                __FUNCTION__ . ' - Failed to connect to exchange rate site: ' .
                self::SOURCE_SITE . '. Error: ' . curl_error()
            );
        }
 
        // Parse exchange rates into an array.
        $totalNewRates = preg_match_all(
            '/<currency>(.*)<\/currency>[.\n\r]*<rate>(.*)<\/rate>/',
            $curlResult,
            $newRates,
            PREG_SET_ORDER
        );
        
        // Fail if preg_match_all failed or no exchange rates were found.
        if ($totalNewRates == 0) {
            throw new Exception(__FUNCTION__ . ' - Failed to gather new exchange rates.');
        }
        
        return $newRates;
    }
}
