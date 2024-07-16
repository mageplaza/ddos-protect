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

namespace Mageplaza\DDoSProtect\Helper;

use DateTime;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\DDoSProtect\Model\Request;
use Psr\Log\LoggerInterface;

/**
 * Class DDos
 * Mageplaza\DDoSProtect\Helper
 */
class DDos
{
    const MAX_REQUESTS       = 'ddos_protect/general/max_requests'; // Maximum number of requests allowed
    const TIME_WINDOW        = 'ddos_protect/general/time_window'; // Time window in seconds
    const XML_PATH_WHITELIST = 'ddos_protect/general/whitelist';
    const PATH               = 'ddos_protect/general/path';

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
        $this->cache        = $cache;
        $this->logger       = $logger;
        $this->storeManager = $storeManager;
        $this->resource     = $resource;
        $this->scopeConfig  = $scopeConfig;
    }

    /**
     * @return $this
     */
    public function saveIpAttackToDB()
    {
        $ipAttacks  = $this->getDataFromCache(Request::IP_ATTACK);
        $connection = $this->resource->getConnection();
        $tableName  = $this->resource->getTableName('mageplaza_ddos_protect');


        $dataToSaveDB = [];
        foreach ($ipAttacks as $ip => $ipAttack) {
            $dataToSaveDB[$ip] = [
                'ip_address'        => $ipAttack['ip_address'],
                'last_request_time' => $ipAttack['last_request_time']
            ];
        }
        if (!empty($dataToSaveDB)) {
            $connection->insertMultiple(
                $tableName,
                $dataToSaveDB
            );
        }

        return $this;
    }

    /**
     * Append data to existing cache key
     *
     * @param string $clientIP
     *
     * @return void
     */
    public function handleClientIp($clientIP)
    {
        $cacheKey    = Request::CLIENT_IP_CACHE_KEY;
        $clientsInfo = $this->getDataFromCache($cacheKey);
        if (!empty($clientsInfo)) {
            foreach ($clientsInfo as $ip => $clientData) {
                if ($clientIP === $ip) {
                    if ($this->isDDoSAttack($clientIP, $clientsInfo)) {
                        $this->appendAttackIpToCache($clientIP, $clientData);
                    }
                    break;
                } else {

                    $clientsInfo[$clientIP] = [
                        'request_count'     => 1,
                        'last_request_time' => (new DateTime())->format('Y-m-d H:i:s')
                    ];

                }
            }
        } else {
            //for none cache
            $clientsInfo = [
                $clientIP => [
                    'request_count'     => 1,
                    'last_request_time' => (new DateTime())->format('Y-m-d H:i:s')
                ]
            ];
        }

        $this->saveDataToCache($clientsInfo, $cacheKey);

    }


    /**
     * Check if the request is part of a DDoS attack
     *
     * @param string $ipAddress
     * @param array $clientsInfo
     *
     * @return bool
     */
    protected function isDDoSAttack($ipAddress, &$clientsInfo)
    {
        // Check if the IP is in the whitelist
        $whitelist = $this->getWhitelist();
        if (in_array($ipAddress, $whitelist)) {
            return false;
        }

        // Calculate the time 15 minutes ago

        $item_time = new DateTime($clientsInfo[$ipAddress]['last_request_time']);
        $requestCount = $clientsInfo[$ipAddress]['request_count'];
        if ((time() - $item_time->getTimestamp()) <= $this->getTimeWindow()) {
            if ($requestCount >= $this->getMaxRequests()) {
                return true;
            } else {
                $clientData['request_count']     = $clientsInfo[$ipAddress]['request_count']++;
                $clientData['ip_address']        = $ipAddress;
                $clientData['last_request_time'] = (new DateTime())->format('Y-m-d H:i:s');
            }
        } else {
            // Reset Count Request after more time have no request 60 second | self::TIME_WINDOW
            $clientsInfo = [
                'request_count'     => 1,
                'ip_address'        => $ipAddress,
                'last_request_time' => (new DateTime())->format('Y-m-d H:i:s')
            ];
        }

        return false;
    }


    /**
     * Get data from cache
     *
     * @param string $cacheKey
     *
     * @return array|string|null
     */
    public function getDataFromCache($cacheKey, $withArr = true)
    {
        $cachedData = $this->cache->load($cacheKey);

        if ($cachedData) {
            return $withArr ? json_decode($cachedData, true) : $cachedData;
        } else {
            return null;
        }
    }

    /**
     * Append data to existing cache key
     *
     * @param string $attackIP
     * @param array $clientInfo
     *
     * @return void
     */
    public function appendAttackIpToCache($attackIP, $clientInfo)
    {
        $clientsInfoCache            = $this->getDataFromCache(Request::IP_ATTACK) ?: [];
        $clientsInfoCache[$attackIP] = $clientInfo;
        $this->saveDataToCache($clientsInfoCache, Request::IP_ATTACK);
    }

    /**
     * @return array
     */
    public function loadIpAttackCache()
    {
        $ipAttacksFromCache = $this->getDataFromCache(Request::IP_ATTACK);
        if (!$ipAttacksFromCache) {
            $ipAttacksFromCache = $this->getIpAttacksFormDB();
            $this->saveDataToCache($ipAttacksFromCache, Request::IP_ATTACK);
        }
        $whitelist = $this->getWhitelist();
        if (!empty($whitelist)) {
            foreach ($ipAttacksFromCache as $key => $ipAttack) {
                if (in_array($ipAttack, $whitelist)) {
                    unset($ipAttacksFromCache[$key]);
                }
            }
        }

        return $ipAttacksFromCache;
    }

    /**
     * @param array $data
     * @param string $cacheKey
     */
    public function saveDataToCache($data, $cacheKey)
    {
        $serializedData = json_encode($data);
        $this->cache->save($serializedData, $cacheKey);
    }

    /**
     * @return array
     */
    public function getIpAttacksFormDB()
    {
        $connection = $this->resource->getConnection();
        $select     = $connection->select()
            ->from('mageplaza_ddos_protect', ['ip_address', 'last_request_time']);
        $ipAddress  = $connection->fetchAll($select);
        $ipAttack   = [];
        foreach ($ipAddress as $ip) {
            $ipAttack[$ip['ip_address']] = [
                'ip_address'        => $ip['ip_address'],
                'last_request_time' => $ip['last_request_time'],
            ];
        }

        return $ipAttack;
    }

    /**
     * Get the IP whitelist from configuration
     *
     * @return array
     */
    protected function getWhitelist()
    {

        $whitelist = $this->scopeConfig->getValue(self::XML_PATH_WHITELIST);

        return $whitelist ? array_map('trim', explode(',', $whitelist)) : [];
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->scopeConfig->getValue(self::PATH);
    }

    /**
     * @return int
     */
    protected function getTimeWindow()
    {
        return (int) $this->scopeConfig->getValue(self::TIME_WINDOW);
    }

    /**
     * @return int
     */
    protected function getMaxRequests()
    {
        return (int) $this->scopeConfig->getValue(self::MAX_REQUESTS);
    }
}
