<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_DDoSProtect
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\DDoSProtect\Cron;

use DateTime;
use Exception;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\DDoSProtect\Model\Request;
use Psr\Log\LoggerInterface;

class CheckIP
{
    const MAX_REQUESTS       = 'ddos_protect/general/max_requests'; // Maximum number of requests allowed
    const TIME_WINDOW        = 'ddos_protect/general/time_window'; // Time window in seconds
    const XML_PATH_WHITELIST = 'ddos_protect/general/whitelist';

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * CheckIP constructor.
     *
     * @param CacheInterface $cache
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resource
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        CacheInterface $cache,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        ResourceConnection $resource,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->cache         = $cache;
        $this->logger        = $logger;
        $this->storeManager  = $storeManager;
        $this->resource      = $resource;
        $this->scopeConfig   = $scopeConfig;
    }

    /**
     * @return $this
     */
    public function execute()
    {
        $cachedData  = $this->cache->load(Request::CLIENT_IP_CACHE_KEY);
        $checkAttack = [];
        if ($cachedData) {
            $cachedData = json_decode($cachedData, true);
            $cachedData = array_unique($cachedData);
            foreach ($cachedData as $ip) {
                if ($this->isDDoSAttack($ip)) {
                    $checkAttack[] = $ip;
                }
            }
        }
        $this->cache->save(json_encode($checkAttack), 'ip_attack', [], 3600);

        return $this;
    }

    /**
     * Check if the request is part of a DDoS attack
     *
     * @return bool
     */
    protected function isDDoSAttack($ipAddress)
    {
        // Check if the IP is in the whitelist
        $whitelist = $this->getWhitelist();
        if (in_array($ipAddress, $whitelist)) {
            return false;
        }

        $connection = $this->resource->getConnection();
        $tableName  = $this->resource->getTableName('mageplaza_ddos_protect');
        // Calculate the time 15 minutes ago
        $currentTime = time();
        $startTime   = $currentTime - 900; // 900 seconds = 15 minutes

        $select = $connection->select()
            ->from($tableName)
            ->where('ip_address = ?', $ipAddress)
            ->where('last_request_time >= ?', date('Y-m-d H:i:s', $startTime));

        $result = $connection->fetchRow($select);

        if ($result) {
            $requestCount    = (int) $result['request_count'];
            $lastRequestTime = strtotime($result['last_request_time']);

            if ((time() - $lastRequestTime) <= $this->getTimeWindow()) {
                if ($requestCount >= $this->getMaxRequests()) {
                    return true;
                } else {
                    $connection->update(
                        $tableName,
                        ['request_count' => $requestCount + 1],
                        ['entity_id = ?' => $result['entity_id']]
                    );
                }
            } else {
                // Reset Count Request after more time have no request 60 second | self::TIME_WINDOW
                $connection->update(
                    $tableName,
                    ['request_count' => 1, 'last_request_time' => (new DateTime())->format('Y-m-d H:i:s')],
                    ['entity_id = ?' => $result['entity_id']]
                );
            }
        } else {
            $connection->insert(
                $tableName,
                [
                    'ip_address'        => $ipAddress,
                    'request_count'     => 1,
                    'last_request_time' => (new DateTime())->format('Y-m-d H:i:s')
                ]
            );
        }

        return false;
    }

    /**
     * Get the IP whitelist from configuration
     *
     * @return array
     */
    protected function getWhitelist()
    {
        $whitelist = $this->getConfig(self::XML_PATH_WHITELIST);

        return $whitelist ? array_map('trim', explode(',', $whitelist)) : [];
    }

    /**
     * @return int
     */
    protected function getTimeWindow()
    {
        $this->getConfig(self::TIME_WINDOW);
    }

    /**
     * @return int
     */
    protected function getMaxRequests()
    {
        return $this->getConfig(self::MAX_REQUESTS);
    }

    /**
     * Get Config
     *
     * @param string $configName
     *
     * @return int
     */
    protected function getConfig(string $configName)
    {
        return (int) $this->scopeConfig->getValue($configName);
    }
}
